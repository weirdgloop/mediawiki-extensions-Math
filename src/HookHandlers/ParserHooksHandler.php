<?php

namespace MediaWiki\Extension\Math\HookHandlers;

use MediaWiki\Extension\Math\Hooks\HookRunner;
use MediaWiki\Extension\Math\MathConfig;
use MediaWiki\Extension\Math\MathMathML;
use MediaWiki\Extension\Math\MathMathMLCli;
use MediaWiki\Extension\Math\MathRenderer;
use MediaWiki\Extension\Math\Render\RendererFactory;
use MediaWiki\Hook\ParserAfterTidyHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Hook\ParserOptionsRegisterHook;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\User\UserOptionsLookup;
use Parser;
use ParserOptions;

/**
 * Hook handler for Parser hooks
 */
class ParserHooksHandler implements
	ParserFirstCallInitHook,
	ParserAfterTidyHook,
	ParserOptionsRegisterHook
{

	/** @var int */
	private $mathTagCounter = 1;

	/** @var array[] renders delayed to be done as a batch [ MathRenderer, Parser ] */
	private $mathLazyRenderBatch = [];

	/** @var RendererFactory */
	private $rendererFactory;

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/** @var HookRunner */
	private $hookRunner;

	/**
	 * @param RendererFactory $rendererFactory
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param HookContainer $hookContainer
	 */
	public function __construct(
		RendererFactory $rendererFactory,
		UserOptionsLookup $userOptionsLookup,
		HookContainer $hookContainer
	) {
		$this->rendererFactory = $rendererFactory;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->hookRunner = new HookRunner( $hookContainer );
	}

	/**
	 * Register the <math> tag with the Parser.
	 *
	 * @param Parser $parser
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setHook( 'math', [ $this, 'mathTagHook' ] );
		// @deprecated the ce tag is deprecated in favour of chem cf. T153606
		$parser->setHook( 'ce', [ $this, 'chemTagHook' ] );
		$parser->setHook( 'chem', [ $this, 'chemTagHook' ] );
	}

	/**
	 * Callback function for the <math> parser hook.
	 *
	 * @param ?string $content (the LaTeX input)
	 * @param array $attributes
	 * @param Parser $parser
	 * @return array|string
	 */
	public function mathTagHook( ?string $content, array $attributes, Parser $parser ) {
		$mode = $parser->getOptions()->getOption( 'math' );
		$renderer = $this->rendererFactory->getRenderer( $content ?? '', $attributes, $mode );

		$parser->getOutput()->addModuleStyles( [ 'ext.math.styles' ] );
		if ( array_key_exists( "qid", $attributes ) ) {
			$parser->getOutput()->addModules( [ 'ext.math.popup' ] );
		}
		if ( $mode == MathConfig::MODE_MATHML ) {
			$marker = Parser::MARKER_PREFIX .
				'-postMath-' . sprintf( '%08X', $this->mathTagCounter++ ) .
				Parser::MARKER_SUFFIX;
			$this->mathLazyRenderBatch[$marker] = [ $renderer, $parser ];
			return $marker;
		}
		return [ $this->mathPostTagHook( $renderer, $parser ), 'markerType' => 'nowiki' ];
	}

	/**
	 * Callback function for the <ce> parser hook.
	 *
	 * @param ?string $content (the LaTeX input)
	 * @param array $attributes
	 * @param Parser $parser
	 * @return array|string
	 */
	public function chemTagHook( ?string $content, array $attributes, Parser $parser ) {
		$attributes['chem'] = true;
		return $this->mathTagHook( '\ce{' . $content . '}', $attributes, $parser );
	}

	/**
	 * Callback function for the <math> parser hook.
	 *
	 * @param MathRenderer $renderer
	 * @param Parser $parser
	 * @return string
	 */
	private function mathPostTagHook( MathRenderer $renderer, Parser $parser ) {
		$checkResult = $renderer->checkTeX();

		if ( $checkResult !== true ) {
			$renderer->addTrackingCategories( $parser );
			return $renderer->getLastError();
		}

		if ( $renderer->render() ) {
			LoggerFactory::getInstance( 'Math' )->debug( "Rendering successful. Writing output" );
			$renderedMath = $renderer->getHtmlOutput();
			$renderer->addTrackingCategories( $parser );
		} else {
			LoggerFactory::getInstance( 'Math' )->warning(
				"Rendering failed. Printing error message." );
			// Set a short parser cache time (10 minutes) after encountering
			// render issues, but not syntax issues.
			$parser->getOutput()->updateCacheExpiry( 600 );
			$renderer->addTrackingCategories( $parser );
			return $renderer->getLastError();
		}
		$this->hookRunner->onMathFormulaPostRender(
			$parser, $renderer, $renderedMath
		); // Enables indexing of math formula

		// Writes cache if rendering was successful
		$renderer->writeCache();

		return $renderedMath;
	}

	/**
	 * @param Parser $parser
	 * @param string &$text
	 */
	public function onParserAfterTidy( $parser, &$text ) {
		global $wgMathoidCli;

		// WGL change - Check that math tags are present in the code before doing batchEvaluate
		$currentMathLazyRenderBatch = [];
		foreach ( $this->mathLazyRenderBatch as $key => [ $renderer, $renderParser ] ) {
			if ( substr_count( $text, $key ) > 0 ) {
				$currentMathLazyRenderBatch[ $key ] = [ $renderer, $renderParser ];
			};
		};
		// End WGL change

		$renderers = array_column( $currentMathLazyRenderBatch, 0 ); // WGL change
		if ( $wgMathoidCli ) {
			MathMathMLCli::batchEvaluate( $renderers );
		} else {
			MathMathML::batchEvaluate( $renderers );
		}
		foreach ( $currentMathLazyRenderBatch as $key => [ $renderer, $renderParser ] ) { // WGL change
			$value = $this->mathPostTagHook( $renderer, $renderParser );
			$count = 0;
			$text = str_replace( $key, $value, $text, $count );
		}
	}

	public function onParserOptionsRegister( &$defaults, &$inCacheKey, &$lazyLoad ) {
		$defaults['math'] = $this->userOptionsLookup->getDefaultOption( 'math' );
		$inCacheKey['math'] = true;
		$lazyLoad['math'] = function ( ParserOptions $options ) {
			return MathConfig::normalizeRenderingMode(
				$this->userOptionsLookup->getOption( $options->getUserIdentity(), 'math' )
			);
		};
	}
}
