<?php # For broken web servers: ><pre>

# If you are reading this in your web browser, your server is probably
# not configured correctly to run PHP applications!
#
# See the README, INSTALL, and UPGRADE files for basic setup instructions
# and pointers to the online documentation.
#
# https://www.mediawiki.org/wiki/Special:MyLanguage/MediaWiki
#
# -------------------------------------------------

/**
 * The main web entry point for web browser navigations, usually via an
 * Action or SpecialPage subclass.
 *
 * @file
 * @ingroup entrypoint
 */

define( 'MW_ENTRY_POINT', 'index' );

require __DIR__ . '/includes/webstart/WebStart.php';

wfIndexMain();

function wfIndexMain() {
	$tkugers = new TKUGERS();
	$tkugers->run();
}
