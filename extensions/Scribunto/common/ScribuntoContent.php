<?php
/**
 * Scribunto Content Model
 *
 * @file
 * @ingroup Extensions
 * @ingroup Scribunto
 *
 * @author Brad Jorsch <bjorsch@wikimedia.org>
 */


/**
 * Represents the content of a Scribunto script page
 */
class ScribuntoContent extends TextContent {

	function __construct( $text ) {
		parent::__construct( $text, 'Scribunto' );
	}

	/**
	 * Parse the Content object and generate a ParserOutput from the result.
	 *
	 * @param $title Title The page title to use as a context for rendering
	 * @param $revId null|int The revision being rendered (optional)
	 * @param $options null|ParserOptions Any parser options
	 * @param $generateHtml boolean Whether to generate HTML (default: true).
	 * @return ParserOutput
	 */
	public function getParserOutput( Title $title, $revId = null, ParserOptions $options = null, $generateHtml = true ) {
		global $wgParser, $wgScribuntoUseGeSHi;

		$text = $this->getNativeData();
		$output = null;

		// Get documentation, if any
		$output = new ParserOutput();
		$doc = Scribunto::getDocPage( $title );
		if ( $doc ) {
			$msg = wfMessage(
				$doc->exists() ? 'scribunto-doc-page-show' : 'scribunto-doc-page-does-not-exist',
				$doc->getPrefixedText()
			)->inContentLanguage();
			if ( !$msg->isDisabled() ) {
				// We need the ParserOutput for categories and such, so we
				// can't use $msg->parse().
				$docViewLang = $doc->getPageViewLanguage();
				$docWikitext = '<div lang="' . htmlspecialchars( $docViewLang->getHtmlCode() ) . '"'
					. ' dir="' . $docViewLang->getDir() . "\">\n" . $msg->plain() . "\n</div>";
				if ( !$options ) {
					// NOTE: use canonical options per default to produce cacheable output
					$options = ContentHandler::getForTitle( $doc )->makeParserOptions( 'canonical' );
				} else {
					if ( $options->getTargetLanguage() === null ) {
						$options->setTargetLanguage( $doc->getPageLanguage() );
					}
				}
				$output = $wgParser->parse( $docWikitext, $title, $options, true, true, $revId );
			}

			// Mark the doc page as a transclusion, so we get purged when it
			// changes.
			$output->addTemplate( $doc, $doc->getArticleID(), $doc->getLatestRevID() );
		}

		// Validate the script, and include an error message and tracking
		// category if it's invalid
		$engine = Scribunto::newDefaultEngine();
		$engine->setTitle( $title );
		$status = $engine->validate( $text, $title->getPrefixedDBkey() );
		if( !$status->isOK() ) {
			$output->setText( $output->getText() .
				Html::rawElement( 'div', array( 'class' => 'errorbox' ),
					$status->getHTML( 'scribunto-error-short', 'scribunto-error-long' )
				)
			);
			$catmsg = wfMessage( 'scribunto-module-with-errors-category' )
				->title( $title )->inContentLanguage();
			if ( !$catmsg->isDisabled() ) {
				$cat = Title::makeTitleSafe( NS_CATEGORY, $catmsg->text() );
				if ( $cat ) {
					$sort = (string)$output->getProperty( 'defaultsort' );
					$output->addCategory( $cat->getDBkey(), $sort );
				} else {
					wfDebug( __METHOD__ . ": [[MediaWiki:scribunto-module-with-errors-category]] " .
						"is not a valid title!\n"
					);
				}
			}
		}

		if ( !$generateHtml ) {
			// We don't need the actual HTML
			$output->setText( '' );
			return $output;
		}

		// Add HTML for the actual script
		$language = $engine->getGeSHiLanguage();
		if( $wgScribuntoUseGeSHi && $language ) {
			$geshi = SyntaxHighlight_GeSHi::prepare( $text, $language );
			$geshi->set_language( $language );
			if( $geshi instanceof GeSHi && !$geshi->error() ) {
				$code = $geshi->parse_code();
				if( $code ) {
					$output->addHeadItem( SyntaxHighlight_GeSHi::buildHeadItem( $geshi ), "source-{$language}" );
					$output->setText( $output->getText() . $code );
					return $output;
				}
			}
		}

		// No GeSHi, or GeSHi can't parse it, use plain <pre>
		$output->setText( $output->getText() .
			"<pre class=\"mw-code mw-script\" dir=\"ltr\">\n" .
			htmlspecialchars( $text ) .
			"\n</pre>\n"
		);

		return $output;
	}

	/**
	 * Returns a Content object with pre-save transformations applied (or this
	 * object if no transformations apply).
	 *
	 * @param $title Title
	 * @param $user User
	 * @param $parserOptions null|ParserOptions
	 * @return Content
	 */
	public function preSaveTransform( Title $title, User $user, ParserOptions $parserOptions ) {
		return $this;
	}
}
