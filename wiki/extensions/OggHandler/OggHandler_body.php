<?php

// @todo Fix core printable stylesheet. Descendant selectors suck.

class OggHandler extends MediaHandler {
	const OGG_METADATA_VERSION = 2;

	/**
	 * @return bool
	 */
	function isEnabled() {
		return true;
	}

	/**
	 * @return array
	 */
	function getParamMap() {
		return array(
			'img_width' => 'width',
			'ogg_noplayer' => 'noplayer',
			'ogg_noicon' => 'noicon',
			'ogg_thumbtime' => 'thumbtime',
		);
	}

	/**
	 * @param $name string
	 * @param $value string
	 * @return bool
	 */
	function validateParam( $name, $value ) {
		if ( in_array( $name, array( 'width', 'height' ) ) ) {
			return $value > 0;
		}
		if ( $name == 'thumbtime' ) {
			$time = $this->parseTimeString( $value );
			if ( $time === false || $time <= 0 ) {
				return false;
			}
			return true;
		}
		return $name == 'noicon';
	}

	/**
	 * @param $seekString
	 * @param $length int|bool
	 * @return bool|float|int
	 */
	function parseTimeString( $seekString, $length = false ) {
		$parts = explode( ':', $seekString );
		$time = 0;
		$multiplier = 1;
		for ( $i = count( $parts ) - 1; $i >= 0; $i--, $multiplier *= 60 ) {
			if ( !is_numeric( $parts[$i] ) ) {
				return false;
			}
			$time +=  $parts[$i] * $multiplier;
		}

		if ( $time < 0 ) {
			wfDebug( __METHOD__.": specified negative time, using zero\n" );
			$time = 0;
		} elseif ( $length !== false && $time > $length - 1 ) {
			wfDebug( __METHOD__.": specified near-end or past-the-end time {$time}s, using end minus 1s\n" );
			$time = $length - 1;
		}
		// Round to nearest 0.1s
		$time = round( $time, 1 );
		return $time;
	}

	/**
	 * @param $params array
	 * @return string
	 */
	function makeParamString( $params ) {
		if ( isset( $params['thumbtime'] ) ) {
			$time = $this->parseTimeString( $params['thumbtime'] );
			if ( $time !== false ) {
				$s = sprintf( "%.1f", $time );
				if ( substr( $s, -2 ) == '.0' ) {
					$s = substr( $s, 0, -2 );
				}
				return 'seek=' . $s;
			}
		}
		return 'mid';
	}

	/**
	 * @param $str string
	 * @return array
	 */
	function parseParamString( $str ) {
		$m = false;
		if ( preg_match( '/^seek=(\d+)$/', $str, $m ) ) {
			return array( 'thumbtime' => $m[0] );
		}
		return array();
	}

	/**
	 * @param $image File
	 * @param $params array
	 * @return bool
	 */
	function normaliseParams( $image, &$params ) {
		$srcWidth = $image->getWidth();
		$srcHeight = $image->getHeight();
		$params['playertype'] = "video";

		if( $srcWidth == 0 || $srcHeight == 0 ) {
			// We presume this is an audio clip
			$params['playertype'] = "audio";
			$params['height'] = empty( $params['height'] ) ? 20 : $params['height'];
			$params['width'] = empty( $params['width'] ) ? 200 : $params['width'];
		} else {
			// Check for height param and adjust width accordingly
			if ( isset( $params['height'] ) && $params['height'] != -1 ) {
				if( $params['width'] * $srcHeight > $params['height'] * $srcWidth ) {
					$params['width'] = self::fitBoxWidth( $srcWidth, $srcHeight, $params['height'] );
				}
			}

			// Make it no wider than the original
			//if( $params['width'] > $srcWidth ) {
			//	$params['width'] = $srcWidth;
			//}

			// Calculate the corresponding height based on the adjusted width
			$params['height'] = File::scaleHeight( $srcWidth, $srcHeight, $params['width'] );
		}

		if ( isset( $params['thumbtime'] ) ) {
			$length = $this->getLength( $image );
			$time = $this->parseTimeString( $params['thumbtime'] );
			if ( $time === false ) {
				return false;
			} elseif ( $time > $length - 1 ) {
				$params['thumbtime'] = $length - 1;
			} elseif ( $time <= 0 ) {
				$params['thumbtime'] = 0;
			}
		}

		return true;
	}

	/**
	 * @param $file File
	 * @param $path string
	 * @param $metadata bool
	 * @return array|bool
	 */
	function getImageSize( $file, $path, $metadata = false ) {
		global $wgOggVideoTypes;
		// Just return the size of the first video stream
		if ( $metadata === false ) {
			$metadata = $file->getMetadata();
		}
		$metadata = $this->unpackMetadata( $metadata );
		if ( isset( $metadata['error'] ) || !isset( $metadata['streams'] ) ) {
			return false;
		}
		foreach ( $metadata['streams'] as $stream ) {
			if ( in_array( $stream['type'], $wgOggVideoTypes ) ) {
				$pictureWidth = $stream['header']['PICW'];
				$parNumerator = $stream['header']['PARN'];
				$parDenominator = $stream['header']['PARD'];
				if( $parNumerator && $parDenominator ) {
					// Compensate for non-square pixel aspect ratios
					$pictureWidth = $pictureWidth * $parNumerator / $parDenominator;
				}
				return array(
					intval( $pictureWidth ),
					intval( $stream['header']['PICH'] )
				);
			}
		}
		return array( false, false );
	}

	/**
	 * @param $image File
	 * @param $path string
	 * @return string
	 */
	function getMetadata( $image, $path ) {
		$metadata = array( 'version' => self::OGG_METADATA_VERSION );

		if ( !class_exists( 'File_Ogg' ) ) {
			require( 'File/Ogg.php' );
		}
		try {
			$f = new File_Ogg( $path );
			$streams = array();
			foreach ( $f->listStreams() as $streamIDs ) {
				foreach ( $streamIDs as $streamID ) {
					$stream = $f->getStream( $streamID );
					$streams[$streamID] = array(
						'serial' => $stream->getSerial(),
						'group' => $stream->getGroup(),
						'type' => $stream->getType(),
						'vendor' => $stream->getVendor(),
						'length' => $stream->getLength(),
						'size' => $stream->getSize(),
						'header' => $stream->getHeader(),
						'comments' => $stream->getComments()
					);
				}
			}
			$metadata['streams'] = $streams;
			$metadata['length'] = $f->getLength();
		} catch ( PEAR_Exception $e ) {
			// File not found, invalid stream, etc.
			$metadata['error'] = array(
				'message' => $e->getMessage(),
				'code' => $e->getCode()
			);
		}
		return serialize( $metadata );
	}

	/**
	 * @param $metadata
	 * @return bool|mixed
	 */
	function unpackMetadata( $metadata ) {
		$unser = @unserialize( $metadata );
		if ( isset( $unser['version'] ) && $unser['version'] == self::OGG_METADATA_VERSION ) {
			return $unser;
		} else {
			return false;
		}
	}

	/**
	 * @param $image
	 * @return string
	 */
	function getMetadataType( $image ) {
		return 'ogg';
	}

	/**
	 * @param $image
	 * @param $metadata
	 * @return bool
	 */
	function isMetadataValid( $image, $metadata ) {
		return $this->unpackMetadata( $metadata ) !== false;
	}

	/**
	 * @param $ext
	 * @param $mime
	 * @param null $params
	 * @return array
	 */
	function getThumbType( $ext, $mime, $params = null ) {
		return array( 'jpg', 'image/jpeg' );
	}

	/**
	 * @param $file File
	 * @param $dstPath string
	 * @param $dstUrl string
	 * @param $params array
	 * @param $flags int
	 * @return MediaTransformError|OggAudioDisplay|OggVideoDisplay|ThumbnailImage|TransformParameterError
	 */
	function doTransform( $file, $dstPath, $dstUrl, $params, $flags = 0 ) {
		if ( !$this->normaliseParams( $file, $params ) ) {
			return new TransformParameterError( $params );
		}

		$width = $params['width'];
		$height = $params['height'];

		$length = $this->getLength( $file );
		$noPlayer = isset( $params['noplayer'] );
		$noIcon = isset( $params['noicon'] );
		$targetFileUrl = $file->getURL();

		$mp4File = false;
		$fileName = $file->getTitle()->getText();
		if ( preg_match( '/\.ogv$/', $fileName ) ) {
			$mp4FileName = preg_replace( '/\.ogv$/', '.mp4', $fileName );
			$mp4File = wfFindFile( $mp4FileName );
			if ( $mp4File === false )
				$mp4File = wfLocalFile( $mp4FileName );
		}

		if ( !$noPlayer ) {
			// Hack for miscellaneous callers
			global $wgOut;
			$this->setHeaders( $wgOut );
		}

		if ( $params['playertype'] == "audio" ) {
			// Make audio player
			if ( $noPlayer ) {
				if ( $height > 100 ) {
					global $wgStylePath;
					$iconUrl = "$wgStylePath/common/images/icons/fileicon-ogg.png";
					return new ThumbnailImage( $file, $iconUrl, 120, 120 );
				} else {
					$scriptPath = self::getMyScriptPath();
					$iconUrl = "$scriptPath/info.png";
					return new ThumbnailImage( $file, $iconUrl, 22, 22 );
				}
			}
			return new OggAudioDisplay( $file, $targetFileUrl, $width, $height, $length, $dstPath, $noIcon );
		}

		// Video thumbnail only
		if ( $noPlayer ) {
			return new ThumbnailImage( $file, $dstUrl, $width, $height, $dstPath, $noIcon );
		}

		if ( $flags & self::TRANSFORM_LATER ) {
			return new OggVideoDisplay( $file, $targetFileUrl, $mp4File === false ? false : $mp4File->getURL(), $dstUrl, $width, $height, $length, $dstPath, $noIcon );
		}

		$thumbTime = false;
		if ( isset( $params['thumbtime'] ) ) {
			$thumbTime = $this->parseTimeString( $params['thumbtime'], $length );
		}
		if ( $thumbTime === false ) {
			# Seek to midpoint by default, it tends to be more interesting than the start
			$thumbTime = $length / 2;
		}

		wfMkdirParents( dirname( $dstPath ), null, __METHOD__ );

		global $wgOggThumbLocation;
		if ( $wgOggThumbLocation !== false ) {
			$status = $this->runOggThumb( $file->getLocalRefPath(), $dstPath, $thumbTime );
		} else {
			$status = $this->runFFmpeg( $file->getLocalRefPath(), $dstPath, $thumbTime );
		}
		if ( $status === true ) {
			if ( $mp4File !== false ) {
				$mp4Path = $mp4File->getLocalRefPath();
				$lockPath = $mp4Path . '.lock.mp4';
				if ( !file_exists( $mp4Path ) && !file_exists( $lockPath ) ) {
					wfMkdirParents( dirname( $lockPath ), null, __METHOD__ );

					$status = $this->runFFmpeg( $file->getLocalRefPath(), $lockPath, 'mp4' );
					if ( $status !== true ) {
						if ( strstr( $status, 'Cannot allocate' ) >= 0 )
							$status .= "\nMaybe you need to increase \$wgMaxShellMemory?";
						return new MediaTransformError( 'thumbnail_error', $width, $height, $status );
					}
					$mp4File->upload($lockPath,
						"Generated from $fileName",
						"Original file: [[:File:$fileName]]",
						File::DELETE_SOURCE,
						null, false, $GLOBALS['wgUser']);
				}
			}
			return new OggVideoDisplay( $file, $file->getURL(), $mp4File ? $mp4File->getURL() : false, $dstUrl, $width, $height,
				$length, $dstPath );
		} else {
			return new MediaTransformError( 'thumbnail_error', $width, $height, $status );
		}
	}

	/**
	 * Run FFmpeg to generate a still image from a video file, using a frame close
	 * to the given number of seconds from the start.
	 *
	 * If the given time is 'mp4', generate an MP4 file instead.
	 *
	 * @param $videoPath string
	 * @param $dstPath string
	 * @param $time integer
	 * @return bool|string Returns true on success, or an error message on failure.
	 */
	function runFFmpeg( $videoPath, $dstPath, $time ) {
		global $wgFFmpegLocation;
		wfDebug( __METHOD__." creating thumbnail at $dstPath\n" );
		$cmd = wfEscapeShellArg( $wgFFmpegLocation ) . ' -y ';
		/*
		This is a workaround until ffmpegs ogg demuxer properly seeks to keyframes.
		Seek 2 seconds before offset and seek in decoded stream after that.
		 -ss before input seeks without decode
		 -ss after input seeks in decoded stream

		if $time < 2 seconds, decode from beginning
		*/
		if ( $time === 'mp4' ) {
			$cmd .= ' -i ' . wfEscapeShellArg( $videoPath ) .
				' -vcodec libx264 -acodec aac -ac 2 -ar 48000 -strict experimental ';
			if ( preg_match( '/ffmpg$/', $wgFFmpegLocation ) )
				$cmd .= ' -vpre slow -vpre ipod640 -sameq ';
			else
				//$cmd .= ' -pre libx264-slow -pre libx264-ipod640 -same_quant ';
				$cmd .= ' -same_quant ';
		} else {
			if ( $time > 2 ) {
				$cmd .= ' -ss ' . intval( $time - 2 ) . ' ';
				$time = 2;
			}
			$cmd .= ' -i ' . wfEscapeShellArg( $videoPath ) .
				' -ss ' . intval( $time ) . ' ' .
				# MJPEG, that's the same as JPEG except it's supported ffmpeg
				# No audio, one frame
				' -f mjpeg -an -vframes 1 ';
		}
		$cmd .= wfEscapeShellArg( $dstPath ) . ' 2>&1';

		$retval = 0;
		$returnText = wfShellExec( $cmd, $retval );

		if ( $this->removeBadFile( $dstPath, $retval ) || $retval ) {
			// Filter nonsense
			$lines = explode( "\n", str_replace( "\r\n", "\n", $returnText ) );
			if ( substr( $lines[0], 0, 6 ) == 'FFmpeg' ) {
				for ( $i = 1; $i < count( $lines ); $i++ ) {
					if ( substr( $lines[$i], 0, 2 ) != '  ' ) {
						break;
					}
				}
				$lines = array_slice( $lines, $i );
			}
			// Return error message
			return implode( "\n", $lines );
		}
		// Success
		return true;
	}

	/**
	 * Run oggThumb to generate a still image from a video file, using a frame
	 * close to the given number of seconds from the start.
	 *
	 * @param $videoPath string
	 * @param $dstPath string
	 * @param $time
	 * @return bool|String Returns true on success, or an error message on failure.
	 */
	function runOggThumb( $videoPath, $dstPath, $time ) {
		global $wgOggThumbLocation;
		wfDebug( __METHOD__." creating thumbnail at $dstPath\n" );
		$cmd = wfEscapeShellArg( $wgOggThumbLocation ) .
			' -t ' . floatval( $time ) .
			' -n ' . wfEscapeShellArg( $dstPath ) .
			' ' . wfEscapeShellArg( $videoPath ) . ' 2>&1';
		$retval = 0;
		$returnText = wfShellExec( $cmd, $retval );

		if ( $this->removeBadFile( $dstPath, $retval ) || $retval ) {
			// oggThumb spams both stderr and stdout with useless progress
			// messages, and then often forgets to output anything when
			// something actually does go wrong. So interpreting its output is
			// a challenge.
			$lines = explode( "\n", str_replace( "\r\n", "\n", $returnText ) );
			if ( count( $lines ) > 0
				&& preg_match( '/invalid option -- \'n\'$/', $lines[0] ) )
			{
				return wfMessage( 'ogg-oggThumb-version', '0.9' )->inContentLanguage()->text();
			} else {
				return wfMessage( 'ogg-oggThumb-failed' )->inContentLanguage()->text();
			}
		}
		return true;
	}

	/**
	 * @param $file
	 * @return bool
	 */
	function canRender( $file ) { return true; }

	/**
	 * @param $file
	 * @return bool
	 */
	function mustRender( $file ) { return true; }

	/**
	 * @param $file File
	 * @return int
	 */
	function getLength( $file ) {
		$metadata = $this->unpackMetadata( $file->getMetadata() );
		if ( !$metadata || isset( $metadata['error'] ) ) {
			return 0;
		} else {
			return $metadata['length'];
		}
	}

	/**
	 * @param $file File
	 * @return array|bool
	 */
	function getStreamTypes( $file ) {
		$streamTypes = array();
		$metadata = $this->unpackMetadata( $file->getMetadata() );
		if ( !$metadata || isset( $metadata['error'] ) ) {
			return false;
		}
		foreach ( $metadata['streams'] as $stream ) {
			$streamTypes[] = $stream['type'];
		}
		return array_unique( $streamTypes );
	}

	/**
	 * @param $file File
	 * @return String
	 */
	function getShortDesc( $file ) {
		global $wgLang, $wgOggAudioTypes, $wgOggVideoTypes;
		$streamTypes = $this->getStreamTypes( $file );
		if ( !$streamTypes ) {
			return parent::getShortDesc( $file );
		}
		if ( array_intersect( $streamTypes, $wgOggVideoTypes ) ) {
			// Count multiplexed audio/video as video for short descriptions
			$msg = 'ogg-short-video';
		} elseif ( array_intersect( $streamTypes, $wgOggAudioTypes ) ) {
			$msg = 'ogg-short-audio';
		} else {
			$msg = 'ogg-short-general';
		}
		return wfMessage( $msg, implode( '/', $streamTypes ),
			$wgLang->formatTimePeriod( $this->getLength( $file ) ) )->text();
	}

	/**
	 * @param $file File
	 * @return String
	 */
	function getLongDesc( $file ) {
		global $wgLang, $wgOggVideoTypes, $wgOggAudioTypes;

		$streamTypes = $this->getStreamTypes( $file );
		if ( !$streamTypes ) {
			$unpacked = $this->unpackMetadata( $file->getMetadata() );
			return wfMessage( 'ogg-long-error', $unpacked['error']['message'] )->text();
		}
		if ( array_intersect( $streamTypes, $wgOggVideoTypes ) ) {
			if ( array_intersect( $streamTypes, $wgOggAudioTypes ) ) {
				$msg = 'ogg-long-multiplexed';
			} else {
				$msg = 'ogg-long-video';
			}
		} elseif ( array_intersect( $streamTypes, $wgOggAudioTypes ) ) {
			$msg = 'ogg-long-audio';
		} else {
			$msg = 'ogg-long-general';
		}
		$size = 0;
		$unpacked = $this->unpackMetadata( $file->getMetadata() );
		if ( !$unpacked || isset( $metadata['error'] ) ) {
			$length = 0;
		} else {
			$length = $this->getLength( $file );
			foreach ( $unpacked['streams'] as $stream ) {
				if( isset( $stream['size'] ) )
					$size += $stream['size'];
			}
		}
		$bitrate = $length == 0 ? 0 : $size / $length * 8;
		return wfMessage(
			$msg,
			implode( '/', $streamTypes ),
			$wgLang->formatTimePeriod( $length ),
			$wgLang->formatBitrate( $bitrate )
		)->numParams(
			$file->getWidth(),
			$file->getHeight()
		)->text();
	}

	/**
	 * @param $file File
	 * @return String
	 */
	function getDimensionsString( $file ) {
		global $wgLang;
		if ( $file->getWidth() ) {
			return wfMessage(
				'video-dims',
				$wgLang->formatTimePeriod( $this->getLength( $file ) )
			)->numParams(
				$file->getWidth(),
				$file->getHeight()
			)->text();
		} else {
			return $wgLang->formatTimePeriod( $this->getLength( $file ) );
		}
	}

	/**
	 * @return string
	 */
	static function getMyScriptPath() {
		global $wgExtensionAssetsPath;
		return "$wgExtensionAssetsPath/OggHandler";
	}

	/**
	 * @param $out OutputPage
	 */
	function setHeaders( $out ) {
		if ( $out->hasHeadItem( 'OggHandlerScript' ) && $out->hasHeadItem( 'OggHandlerInlineScript' ) &&
			$out->hasHeadItem( 'OggHandlerInlineCSS' ) ) {
			return;
		}
		global $wgOggScriptVersion, $wgCortadoJarFile, $wgLang;

		$msgNames = array( 'ogg-play', 'ogg-pause', 'ogg-stop', 'ogg-no-player',
			'ogg-player-videoElement', 'ogg-player-oggPlugin', 'ogg-player-cortado', 'ogg-player-vlc-mozilla',
			'ogg-player-vlc-activex', 'ogg-player-quicktime-mozilla', 'ogg-player-quicktime-activex',
			'ogg-player-totem', 'ogg-player-kaffeine', 'ogg-player-kmplayer', 'ogg-player-mplayerplug-in',
			'ogg-player-thumbnail', 'ogg-player-selected', 'ogg-use-player', 'ogg-more', 'ogg-download',
			'ogg-desc-link', 'ogg-dismiss', 'ogg-player-soundthumb', 'ogg-no-xiphqt' );
		$msgs = array();
		foreach ( $msgNames as $msg ) {
			$msgs[$msg] = wfMessage( $msg )->text();
		}
		$jsMsgs = Xml::encodeJsVar( (object)$msgs );
		$cortadoUrl = $wgCortadoJarFile;
		$scriptPath = self::getMyScriptPath();
		if( substr( $cortadoUrl, 0, 1 ) != '/'
				&& substr( $cortadoUrl, 0, 4 ) != 'http' ) {
			$cortadoUrl = wfExpandUrl( "$scriptPath/$cortadoUrl", PROTO_CURRENT );
		}
		$encCortadoUrl = Xml::encodeJsVar( $cortadoUrl );
		$encExtPathUrl = Xml::encodeJsVar( $scriptPath );
		$alignStart = $wgLang->alignStart();

		$out->addHeadItem( 'OggHandlerScript' , Html::linkedScript( "{$scriptPath}/OggPlayer.js?$wgOggScriptVersion" ) );

		$out->addHeadItem( 'OggHandlerInlineScript',  Html::inlineScript( <<<EOT

wgOggPlayer.msg = $jsMsgs;
wgOggPlayer.cortadoUrl = $encCortadoUrl;
wgOggPlayer.extPathUrl = $encExtPathUrl;

EOT
) );
		$out->addHeadItem( 'OggHandlerInlineCSS', Html::inlineStyle( <<<EOT

.ogg-player-options {
	border: solid 1px #ccc;
	padding: 2pt;
	text-align: $alignStart;
	font-size: 10pt;
}

.center .ogg-player-options ul {
	margin: 0.3em 0px 0px 1.5em;
}

EOT
) );
	}

	/**
	 * @param $parser Parser
	 * @param $file File
	 */
	function parserTransformHook( $parser, $file ) {
		if ( isset( $parser->getOutput()->hasOggTransform ) ) {
			return;
		}
		$parser->getOutput()->hasOggTransform = true;
		$parser->getOutput()->addOutputHook( 'OggHandler' );
	}

	/**
	 * @param $outputPage OutputPage
	 * @param $parserOutput ParserOutput
	 * @param $data
	 */
	static function outputHook( $outputPage, $parserOutput, $data ) {
		$instance = MediaHandler::getHandler( 'application/ogg' );
		if ( $instance ) {
			$instance->setHeaders( $outputPage );
		}
	}

	/**
	 * Handler for the ExtractThumbParameters hook
	 *
	 * @param $thumbname string URL-decoded basename of URI
	 * @param &$params Array Currently parsed thumbnail params
	 * @return bool
	 */
	public static function onExtractThumbParameters( $thumbname, array &$params ) {
		if ( !preg_match( '/\.(?:ogg|ogv|oga)$/i', $params['f'] ) ) {
			return true; // not an ogg file
		}
		// Check if the parameters can be extracted from the thumbnail name...
		if ( preg_match( '!^(mid|seek=[0-9.]+)-[^/]*$!', $thumbname, $m ) ) {
			list( /* all */, $timeFull ) = $m;
			if ( $timeFull != 'mid' ) {
				list( $seek, $thumbtime ) = explode( '=', $timeFull, 2 );
				$params['thumbtime'] = $thumbtime;
			}
			return false; // valid thumbnail URL
		}
		return true; // pass through to next handler
	}
}

/**
 * Generic player (not MediaHandler dependent) for "Score" extension support
 */
class OggHandlerPlayer {
	static $serial = 0;

	var $params;

	/**
	 * @param $params Associative array of parameters:
	 *    - type: "audio" or "video"
	 *    - defaultAlt: The default "alt" attribute, when not overridden by
	 *      $options pased to toHTML()
	 *    - videoUrl: The Ogg file URL
	 *    - mp4Url: The MP4 file URL
	 *    - thumbUrl: The URL of the thumbnail (or false)
	 *    - width: The width of the player
	 *    - height: The height of the player (zero for audio)
	 *    - length: The length in seconds of the Ogg file
	 *    - showIcon: Set to true to show a description icon
	 */
	function __construct( $params ) {
		$this->params = $params;
	}

	/**
	 * @param $options array
	 * @return string
	 * @throws MWException
	 */
	function toHtml( $options = array() ) {
		if ( count( func_get_args() ) == 2 ) {
			throw new MWException( __METHOD__ .' called in the old style' );
		}

		self::$serial++;

		$url = wfExpandUrl( $this->params['videoUrl'], PROTO_RELATIVE );
		$mp4Url = $this->params['mp4Url'] ? wfExpandUrl( $this->params['mp4Url'], PROTO_RELATIVE ) : false;
		// Normalize values
		$length = floatval( $this->params['length'] );
		$width = intval( $this->params['width'] );
		$height = intval( $this->params['height'] );

		$alt = isset( $options['alt'] ) ? $options['alt'] : '';
		$scriptPath = OggHandler::getMyScriptPath();
		$showDescIcon = false;

		if ( $this->params['type'] === 'video' ) {
			$msgStartPlayer = wfMessage( 'ogg-play-video' )->text();
			$imgAttribs = array(
				'src' => $this->params['thumbUrl'],
				'width' => $width,
				'height' => $height,
				'alt' => $alt );
			$playerHeight = $height;
		} elseif ( $this->params['type'] === 'audio' ) {
			// Sound file
			if ( $height > 100 ) {
				// Use a big file icon
				global $wgStylePath;
				$imgAttribs = array(
					'src' => "$wgStylePath/common/images/icons/fileicon-ogg.png",
					'width' => 125,
					'height' => 125,
					'alt' => $alt,
				);
			} else {
				 // Make an icon later if necessary
				$imgAttribs = false;
				$showDescIcon = $this->params['showIcon'];
				//$thumbDivAttribs = array( 'style' => 'text-align: right;' );
			}
			$msgStartPlayer = wfMessage( 'ogg-play-sound' )->text();
			$playerHeight = 35;
		} else {
			throw new MWException( __CLASS__.': invalid type parameter, must be audio or video' );
		}

		// Set $thumb to the thumbnail img tag, or the thing that goes where
		// the thumbnail usually goes
		$descIcon = false;
		if ( !empty( $options['desc-link'] ) ) {
			$linkAttribs = $options['desc-link-attribs'];
			if ( $showDescIcon ) {
				// Make image description icon link
				$imgAttribs = array(
					'src' => "$scriptPath/info.png",
					'width' => 22,
					'height' => 22,
					'alt' => $alt,
				);
				$linkAttribs['title'] = wfMessage( 'ogg-desc-link' )->text();
				$descIcon = Xml::tags( 'a', $linkAttribs,
					Xml::element( 'img', $imgAttribs ) );
				$thumb = '';
			} elseif ( $imgAttribs ) {
				$thumb = Xml::tags( 'a', $linkAttribs,
					Xml::element( 'img', $imgAttribs ) );
			} else {
				$thumb = '';
			}
			$linkUrl = $linkAttribs['href'];
		} else {
			// We don't respect the file-link option, click-through to download is not appropriate
			$linkUrl = false;
			if ( $imgAttribs ) {
				$thumb = Xml::element( 'img', $imgAttribs );
			} else {
				$thumb = '';
			}
		}

		$id = "ogg_player_" . self::$serial;

		$playerParams = Xml::encodeJsVar( (object)array(
			'id' => $id,
			'videoUrl' => $url,
			'mp4Url' => $mp4Url,
			'width' => $width,
			'height' => $playerHeight,
			'length' => $length,
			'linkUrl' => $linkUrl,
			'isVideo' => $this->params['type'] === 'video' ) );

		$s = Xml::tags( 'div',
			array( 'id' => $id ),
			( $thumb ? Xml::tags( 'div', array(), $thumb ) : '' ) .
			Xml::tags( 'div', array(),
				Xml::tags( 'button',
					array(
						'onclick' => "if (typeof(wgOggPlayer) != 'undefined') wgOggPlayer.init(false, $playerParams);",
						'style' => "width: {$width}px; text-align: center",
						'title' => $msgStartPlayer,
					),
					Xml::element( 'img',
						array(
							'src' => "$scriptPath/play.png",
							'width' => 22,
							'height' => 22,
							'alt' => $msgStartPlayer
						)
					)
				)
			) .
			( $descIcon ? Xml::tags( 'div', array(), $descIcon ) : '' )
		);
		return $s;
	}
}

class OggTransformOutput extends MediaTransformOutput {
	var $player;

	function __construct(
		$file, $videoUrl, $mp4Url, $thumbUrl, $width, $height, $length, $isVideo, $path, $noIcon
	) {
		// Variables used by the parent class
		$this->file = $file;
		$this->path = $path;
		$this->width = $width;
		$this->height = $height;
		$this->url = $thumbUrl;

		// Variable used by this class
		$this->player = new OggHandlerPlayer( array(
			'defaultAlt' => $file->getTitle()->getText(),
			'videoUrl' => $videoUrl,
			'mp4Url' => $mp4Url,
			'thumbUrl' => $thumbUrl,
			'width' => $width,
			'height' => $height,
			'length' => $length,
			'type' => $isVideo ? 'video' : 'audio',
			'showIcon' => !$noIcon
		) );
	}

	function toHtml( $options = array() ) {
		$alt = empty( $options['alt'] ) ? $this->file->getTitle()->getText() : $options['alt'];
		if ( !empty( $options['desc-link'] ) ) {
			$options['desc-link-attribs'] = $this->getDescLinkAttribs( $alt );
		}
		return $this->player->toHtml( $options );
	}
}

class OggVideoDisplay extends OggTransformOutput {
	function __construct( $file, $videoUrl, $mp4Url, $thumbUrl, $width, $height, $length, $path, $noIcon=false ) {
		parent::__construct( $file, $videoUrl, $mp4Url, $thumbUrl, $width, $height, $length, true, $path, false );
	}
}

class OggAudioDisplay extends OggTransformOutput {
	function __construct( $file, $videoUrl, $width, $height, $length, $path, $noIcon = false ) {
		parent::__construct( $file, $videoUrl, false, false, $width, $height, $length, false, $path, $noIcon );
	}
}
