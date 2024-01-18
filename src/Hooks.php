<?php
/**
 * MediaWiki math extension
 *
 * @copyright 2002-2015 various MediaWiki contributors
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\Math;

// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

use ConfigException;
use ExtensionRegistry;
use Maintenance;
use MediaWiki\Hook\MaintenanceRefreshLinksInitHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;
use RequestContext;

class Hooks implements
	SpecialPage_initListHook,
	MaintenanceRefreshLinksInitHook
{

	/**
	 * MaintenanceRefreshLinksInit handler; optimize settings for refreshLinks batch job.
	 *
	 * @param Maintenance $maint
	 */
	public function onMaintenanceRefreshLinksInit( $maint ) {
		$user = RequestContext::getMain()->getUser();

		// Don't parse LaTeX to improve performance
		MediaWikiServices::getInstance()->getUserOptionsManager()
			->setOption( $user, 'math', MathConfig::MODE_SOURCE );
	}

	/**
	 * Remove Special:MathWikibase if the Wikibase client extension isn't loaded
	 *
	 * @param array &$list
	 */
	public function onSpecialPage_initList( &$list ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'WikibaseClient' ) ) {
			unset( $list['MathWikibase'] );
		}
	}

}
