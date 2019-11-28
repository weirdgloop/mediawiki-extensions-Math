<?php

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\Store\LanguageFallbackLabelDescriptionLookupFactory;

/**
 * A config class for the MathWikibaseConnector to connect with Wikibase
 * @see MathWikibaseConnector
 */
class MathWikibaseConfig {
	/**
	 * @var EntityIdParser
	 */
	private $idParser;

	/**
	 * @var EntityRevisionLookup
	 */
	private $entityRevisionLookup;

	/**
	 * @var LanguageFallbackLabelDescriptionLookupFactory
	 */
	private $labelLookupFactory;

	/**
	 * @var Site
	 */
	private $site;

	/**
	 * @var PropertyId
	 */
	private $propertyIdHasPart;

	/**
	 * @var PropertyId
	 */
	private $propertyIdQuantitySymbol;

	/**
	 * @var PropertyId
	 */
	private $propertyIdDefiningFormula;

	/**
	 * @var MathWikibaseConfig
	 */
	private static $defaultConfig;

	/**
	 * MathWikibaseConfig constructor.
	 * @param EntityIdParser $entityIdParser
	 * @param EntityRevisionLookup $entityRevisionLookup
	 * @param LanguageFallbackLabelDescriptionLookupFactory $labelDescriptionLookupFactory
	 * @param Site $site
	 */
	public function __construct(
		EntityIdParser $entityIdParser,
		EntityRevisionLookup $entityRevisionLookup,
		LanguageFallbackLabelDescriptionLookupFactory $labelDescriptionLookupFactory,
		Site $site
	) {
		$this->idParser = $entityIdParser;
		$this->entityRevisionLookup = $entityRevisionLookup;
		$this->labelLookupFactory = $labelDescriptionLookupFactory;
		$this->site = $site;

		$config = MediaWikiServices::getInstance()->getMainConfig();
		$this->propertyIdHasPart = $this->idParser->parse(
			$config->get( "MathWikibasePropertyIdHasPart" )
		);
		$this->propertyIdDefiningFormula = $this->idParser->parse(
			$config->get( "MathWikibasePropertyIdDefiningFormula" )
		);
		$this->propertyIdQuantitySymbol = $this->idParser->parse(
			$config->get( "MathWikibasePropertyIdQuantitySymbol" )
		);
	}

	/**
	 * @return EntityIdParser
	 */
	public function getIdParser() : EntityIdParser {
		return $this->idParser;
	}

	/**
	 * @return EntityRevisionLookup
	 */
	public function getEntityRevisionLookup() : EntityRevisionLookup {
		return $this->entityRevisionLookup;
	}

	/**
	 * @return LanguageFallbackLabelDescriptionLookupFactory
	 */
	public function getLabelLookupFactory() : LanguageFallbackLabelDescriptionLookupFactory {
		return $this->labelLookupFactory;
	}

	/**
	 * @return Site
	 */
	public function getSite() : Site {
		return $this->site;
	}

	/**
	 * @return bool
	 */
	public function hasSite() {
		return !is_null( $this->site );
	}

	/**
	 * @return PropertyId
	 */
	public function getPropertyIdHasPart() : PropertyId {
		return $this->propertyIdHasPart;
	}

	/**
	 * @return PropertyId
	 */
	public function getPropertyIdQuantitySymbol() : PropertyId {
		return $this->propertyIdQuantitySymbol;
	}

	/**
	 * @return PropertyId
	 */
	public function getPropertyIdDefiningFormula() : PropertyId {
		return $this->propertyIdDefiningFormula;
	}

	/**
	 * @return MathWikibaseConfig default config
	 */
	public static function getDefaultMathWikibaseConfig() : MathWikibaseConfig {
		if ( !self::$defaultConfig ) {
			$wikibaseClient = WikibaseClient::getDefaultInstance();

			$site = null;
			try {
				$site = $wikibaseClient->getSite();
			} catch ( MWException $e ) {
				$logger = LoggerFactory::getInstance( 'Math' );
				$logger->warning( "Cannot get Site handler: " . $e->getMessage() );
			}

			self::$defaultConfig = new MathWikibaseConfig(
				$wikibaseClient->getEntityIdParser(),
				$wikibaseClient->getStore()->getEntityRevisionLookup(),
				$wikibaseClient->getLanguageFallbackLabelDescriptionLookupFactory(),
				$site
			);
		}
		return self::$defaultConfig;
	}
}
