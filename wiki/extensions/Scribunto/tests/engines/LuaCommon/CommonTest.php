<?php

class Scribunto_LuaCommonTests extends Scribunto_LuaEngineTestBase {
	protected static $moduleName = 'CommonTests';

	private static $allowedGlobals = array(
		// Functions
		'assert',
		'error',
		'getfenv',
		'getmetatable',
		'ipairs',
		'next',
		'pairs',
		'pcall',
		'rawequal',
		'rawget',
		'rawset',
		'require',
		'select',
		'setfenv',
		'setmetatable',
		'tonumber',
		'tostring',
		'type',
		'unpack',
		'xpcall',

		// Packages
		'_G',
		'debug',
		'math',
		'mw',
		'os',
		'package',
		'string',
		'table',

		// Misc
		'_VERSION',
	);

	function setUp() {
		parent::setUp();

		// Note this depends on every iteration of the data provider running with a clean parser
		$this->getEngine()->getParser()->getOptions()->setExpensiveParserFunctionLimit( 10 );

		// Some of the tests need this
		$interpreter = $this->getEngine()->getInterpreter();
		$interpreter->callFunction(
			$interpreter->loadString( 'mw.makeProtectedEnvFuncsForTest = mw.makeProtectedEnvFuncs', 'fortest' )
		);
	}

	function getTestModules() {
		return parent::getTestModules() + array(
			'CommonTests' => __DIR__ . '/CommonTests.lua',
			'CommonTests-data' => __DIR__ . '/CommonTests-data.lua',
			'CommonTests-data-fail1' => __DIR__ . '/CommonTests-data-fail1.lua',
			'CommonTests-data-fail2' => __DIR__ . '/CommonTests-data-fail2.lua',
			'CommonTests-data-fail3' => __DIR__ . '/CommonTests-data-fail3.lua',
			'CommonTests-data-fail4' => __DIR__ . '/CommonTests-data-fail4.lua',
			'CommonTests-data-fail5' => __DIR__ . '/CommonTests-data-fail5.lua',
		);
	}

	function testNoLeakedGlobals() {
		$interpreter = $this->getEngine()->getInterpreter();

		list( $actualGlobals ) = $interpreter->callFunction(
			$interpreter->loadString(
				'local t = {} for k in pairs( _G ) do t[#t+1] = k end return t',
				'getglobals'
			)
		);

		$leakedGlobals = array_diff( $actualGlobals, self::$allowedGlobals );
		$this->assertEquals( 0, count( $leakedGlobals ),
			'The following globals are leaked: ' . join( ' ', $leakedGlobals )
		);
	}

	function testModuleStringExtend() {
		$engine = $this->getEngine();
		$interpreter = $engine->getInterpreter();

		$interpreter->callFunction(
			$interpreter->loadString( 'string.testModuleStringExtend = "ok"', 'extendstring' )
		);
		$ret = $interpreter->callFunction(
			$interpreter->loadString( 'return ("").testModuleStringExtend', 'teststring1' )
		);
		$this->assertSame( array( 'ok' ), $ret, 'string can be extended' );

		$this->extraModules['Module:testModuleStringExtend'] = '
			return {
				test = function() return ("").testModuleStringExtend end
			}
			';
		$module = $engine->fetchModuleFromParser(
			Title::makeTitle( NS_MODULE, 'testModuleStringExtend' )
		);
		$ext = $module->execute();
		$ret = $interpreter->callFunction( $ext['test'] );
		$this->assertSame( array( 'ok' ), $ret, 'string extension can be used from module' );

		$this->extraModules['Module:testModuleStringExtend2'] = '
			return {
				test = function()
					string.testModuleStringExtend = "fail"
					return ("").testModuleStringExtend
				end
			}
			';
		$module = $engine->fetchModuleFromParser(
			Title::makeTitle( NS_MODULE, 'testModuleStringExtend2' )
		);
		$ext = $module->execute();
		$ret = $interpreter->callFunction( $ext['test'] );
		$this->assertSame( array( 'ok' ), $ret, 'string extension cannot be modified from module' );
		$ret = $interpreter->callFunction(
			$interpreter->loadString( 'return string.testModuleStringExtend', 'teststring2' )
		);
		$this->assertSame( array( 'ok' ), $ret, 'string extension cannot be modified from module' );

		$ret = $engine->runConsole( array(
			'prevQuestions' => array(),
			'question' => '=("").testModuleStringExtend',
			'content' => 'return {}',
			'title' => Title::makeTitle( NS_MODULE, 'dummy' ),
		) );
		$this->assertSame( 'ok', $ret['return'], 'string extension can be used from console' );

		$ret = $engine->runConsole( array(
			'prevQuestions' => array( 'string.fail = "fail"' ),
			'question' => '=("").fail',
			'content' => 'return {}',
			'title' => Title::makeTitle( NS_MODULE, 'dummy' ),
		) );
		$this->assertSame( 'nil', $ret['return'], 'string cannot be extended from console' );

		$ret = $engine->runConsole( array(
			'prevQuestions' => array( 'string.testModuleStringExtend = "fail"' ),
			'question' => '=("").testModuleStringExtend',
			'content' => 'return {}',
			'title' => Title::makeTitle( NS_MODULE, 'dummy' ),
		) );
		$this->assertSame( 'ok', $ret['return'], 'string extension cannot be modified from console' );
		$ret = $interpreter->callFunction(
			$interpreter->loadString( 'return string.testModuleStringExtend', 'teststring3' )
		);
		$this->assertSame( array( 'ok' ), $ret, 'string extension cannot be modified from console' );

		$interpreter->callFunction(
			$interpreter->loadString( 'string.testModuleStringExtend = nil', 'unextendstring' )
		);
	}

	function testLoadDataLoadedOnce() {
		$engine = $this->getEngine();
		$interpreter = $engine->getInterpreter();
		$frame = $engine->getParser()->getPreprocessor()->newFrame();

		$loadcount = 0;
		$interpreter->callFunction(
			$interpreter->loadString( 'mw.markLoaded = ...', 'fortest' ),
			$interpreter->wrapPHPFunction( function () use (&$loadcount) {
				$loadcount++;
			} )
		);
		$this->extraModules['Module:TestLoadDataLoadedOnce-data'] = '
			mw.markLoaded()
			return {}
		';
		$this->extraModules['Module:TestLoadDataLoadedOnce'] = '
			local data = mw.loadData( "Module:TestLoadDataLoadedOnce-data" )
			return {
				foo = function() end,
				bar = function()
					return tostring( package.loaded["Module:TestLoadDataLoadedOnce-data"] )
				end,
			}
		';

		// Make sure data module isn't parsed twice. Simulate several {{#invoke:}}s
		$title = Title::makeTitle( NS_MODULE, 'TestLoadDataLoadedOnce' );
		for ( $i = 0; $i < 10; $i++ ) {
			$module = $engine->fetchModuleFromParser( $title );
			$module->invoke( 'foo', $frame->newChild() );
		}
		$this->assertSame( 1, $loadcount, 'data module was loaded more than once' );

		// Make sure data module isn't in package.loaded
		$this->assertSame( 'nil', $module->invoke( 'bar', $frame ),
			'data module was stored in module\'s package.loaded'
		);
		$this->assertSame( array( 'nil' ),
			$interpreter->callFunction( $interpreter->loadString(
				'return tostring( package.loaded["Module:TestLoadDataLoadedOnce-data"] )', 'getLoaded'
			) ),
			'data module was stored in top level\'s package.loaded'
		);
	}

	function testFrames() {
		$engine = $this->getEngine();

		$ret = $engine->runConsole( array(
			'prevQuestions' => array(),
			'question' => '=mw.getCurrentFrame()',
			'content' => 'return {}',
			'title' => Title::makeTitle( NS_MODULE, 'dummy' ),
		) );
		$this->assertSame( 'table', $ret['return'], 'frames can be used in the console' );

		$ret = $engine->runConsole( array(
			'prevQuestions' => array(),
			'question' => '=mw.getCurrentFrame():newChild{}',
			'content' => 'return {}',
			'title' => Title::makeTitle( NS_MODULE, 'dummy' ),
		) );
		$this->assertSame( 'table', $ret['return'], 'child frames can be created' );

		$ret = $engine->runConsole( array(
			'prevQuestions' => array(
				'f = mw.getCurrentFrame():newChild{ args = { "ok" } }',
				'f2 = f:newChild{ args = {} }'
			),
			'question' => '=f2:getParent().args[1], f2:getParent():getParent()',
			'content' => 'return {}',
			'title' => Title::makeTitle( NS_MODULE, 'dummy' ),
		) );
		$this->assertSame( "ok\ttable", $ret['return'], 'child frames have correct parents' );
	}

	function testCallParserFunction() {
		global $wgContLang;

		$engine = $this->getEngine();
		$parser = $engine->getParser();

		$args = array(
			'prevQuestions' => array(),
			'content' => 'return {}',
			'title' => Title::makeTitle( NS_MODULE, 'dummy' ),
		);

		// Test argument calling conventions
		$ret = $engine->runConsole( array(
			'question' => '=mw.getCurrentFrame():callParserFunction{
				name = "urlencode", args = { "x x", "wiki" }
			}',
		) + $args );
		$this->assertSame( "x_x", $ret['return'],
			'callParserFunction works for {{urlencode:x x|wiki}} (named args w/table)'
		);

		$ret = $engine->runConsole( array(
			'question' => '=mw.getCurrentFrame():callParserFunction{
				name = "urlencode", args = "x x"
			}',
		) + $args );
		$this->assertSame( "x+x", $ret['return'],
			'callParserFunction works for {{urlencode:x x}} (named args w/scalar)'
		);

		$ret = $engine->runConsole( array(
			'question' => '=mw.getCurrentFrame():callParserFunction( "urlencode", { "x x", "wiki" } )',
		) + $args );
		$this->assertSame( "x_x", $ret['return'],
			'callParserFunction works for {{urlencode:x x|wiki}} (positional args w/table)'
		);

		$ret = $engine->runConsole( array(
			'question' => '=mw.getCurrentFrame():callParserFunction( "urlencode", "x x", "wiki" )',
		) + $args );
		$this->assertSame( "x_x", $ret['return'],
			'callParserFunction works for {{urlencode:x x|wiki}} (positional args w/scalars)'
		);

		$ret = $engine->runConsole( array(
			'question' => '=mw.getCurrentFrame():callParserFunction{
				name = "urlencode:x x", args = { "wiki" }
			}',
		) + $args );
		$this->assertSame( "x_x", $ret['return'],
			'callParserFunction works for {{urlencode:x x|wiki}} (colon in name, named args w/table)'
		);

		$ret = $engine->runConsole( array(
			'question' => '=mw.getCurrentFrame():callParserFunction{
				name = "urlencode:x x", args = "wiki"
			}',
		) + $args );
		$this->assertSame( "x_x", $ret['return'],
			'callParserFunction works for {{urlencode:x x|wiki}} (colon in name, named args w/scalar)'
		);

		$ret = $engine->runConsole( array(
			'question' => '=mw.getCurrentFrame():callParserFunction( "urlencode:x x", { "wiki" } )',
		) + $args );
		$this->assertSame( "x_x", $ret['return'],
			'callParserFunction works for {{urlencode:x x|wiki}} (colon in name, positional args w/table)'
		);

		$ret = $engine->runConsole( array(
			'question' => '=mw.getCurrentFrame():callParserFunction( "urlencode:x x", "wiki" )',
		) + $args );
		$this->assertSame( "x_x", $ret['return'],
			'callParserFunction works for {{urlencode:x x|wiki}} (colon in name, positional args w/scalars)'
		);

		// Test named args to the parser function
		$ret = $engine->runConsole( array(
			'question' => '=mw.getCurrentFrame():callParserFunction( "#tag:pre",
				{ "foo", style = "margin-left: 1.6em" }
			)',
		) + $args );
		$this->assertSame(
			'<pre style="margin-left: 1.6em">foo</pre>',
			$parser->mStripState->unstripBoth( $ret['return'] ),
			'callParserFunction works for {{#tag:pre|foo|style=margin-left: 1.6em}}'
		);

		// Test extensionTag
		$ret = $engine->runConsole( array(
			'question' => '=mw.getCurrentFrame():extensionTag( "pre", "foo",
				{ style = "margin-left: 1.6em" }
			)',
		) + $args );
		$this->assertSame(
			'<pre style="margin-left: 1.6em">foo</pre>',
			$parser->mStripState->unstripBoth( $ret['return'] ),
			'extensionTag works for {{#tag:pre|foo|style=margin-left: 1.6em}}'
		);

		$ret = $engine->runConsole( array(
			'question' => '=mw.getCurrentFrame():extensionTag{ name = "pre", content = "foo",
				args = { style = "margin-left: 1.6em" }
			}',
		) + $args );
		$this->assertSame(
			'<pre style="margin-left: 1.6em">foo</pre>',
			$parser->mStripState->unstripBoth( $ret['return'] ),
			'extensionTag works for {{#tag:pre|foo|style=margin-left: 1.6em}}'
		);

		// Test calling a non-existent function
		try {
			$ret = $engine->runConsole( array(
				'question' => '=mw.getCurrentFrame():callParserFunction{
					name = "thisDoesNotExist", arg1 = ""
				}',
			) + $args );
			$this->fail( "Expected LuaError not thrown for nonexistent parser function" );
		} catch ( Scribunto_LuaError $err ) {
			$this->assertSame(
				'Lua error: callParserFunction: function "thisDoesNotExist" was not found.',
				$err->getMessage(),
				'callParserFunction correctly errors for nonexistent function'
			);
		}
	}

	function testBug62291() {
		$engine = $this->getEngine();
		$frame = $engine->getParser()->getPreprocessor()->newFrame();

		$this->extraModules['Module:Bug62291'] = '
			local p = {}
			function p.foo()
				return table.concat( {
					math.random(), math.random(), math.random(), math.random(), math.random()
				}, ", " )
			end
			function p.bar()
				local t = {}
				t[1] = p.foo()
				t[2] = mw.getCurrentFrame():preprocess( "{{#invoke:Bug62291|bar2}}" )
				t[3] = p.foo()
				return table.concat( t, "; " )
			end
			function p.bar2()
				return "bar2 called"
			end
			return p
		';

		$title = Title::makeTitle( NS_MODULE, 'Bug62291' );
		$module = $engine->fetchModuleFromParser( $title );

		// Make sure multiple invokes return the same text
		$r1 = $module->invoke( 'foo', $frame->newChild() );
		$r2 = $module->invoke( 'foo', $frame->newChild() );
		$this->assertSame( $r1, $r2, 'Multiple invokes returned different sets of random numbers' );

		// Make sure a recursive invoke doesn't reset the PRNG
		$r1 = $module->invoke( 'bar', $frame->newChild() );
		$r = explode( '; ', $r1 );
		$this->assertNotSame( $r[0], $r[2], 'Recursive invoke reset PRNG' );
		$this->assertSame( 'bar2 called', $r[1], 'Sanity check failed' );

		// But a second invoke does
		$r2 = $module->invoke( 'bar', $frame->newChild() );
		$this->assertSame( $r1, $r2, 'Multiple invokes with recursive invoke returned different sets of random numbers' );
	}
}
