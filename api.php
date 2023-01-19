<?php
/**
 * The web entry point for all %Action API queries, handled by ApiMain
 * and ApiBase subclasses.
 *
 * This is used by bots to fetch content and information about the site,
 * its pages, and its users.
 *
 * It begins by constructing a new ApiMain using the parameter passed to it
 * as an argument in the URL ('?action='). It then invokes "execute()" on the
 * ApiMain object instance, which produces output in the format specified in
 * the URL.
 *
 * @file
 * @ingroup entrypoint
 * @ingroup API
 */

# So extensions (and other code) can check whether they're running in API mode
define( 'MW_API', true );
define( 'MW_ENTRY_POINT', 'api' );

require __DIR__ . '/includes/webstart/WebStart.php';

wfApiMain();

function wfApiMain() {
	global $wgRequest, $wgTitle, $wgAPIRequestLog;

	$starttime = microtime( true );

	// PATH_INFO can be used for stupid things. We don't support it for api.php at
	// all, so error out if it's present. (T128209)
	if ( isset( $_SERVER['PATH_INFO'] ) && $_SERVER['PATH_INFO'] != '' ) {
		$correctUrl = wfAppendQuery( wfScript( 'api' ), $wgRequest->getQueryValuesOnly() );
		$correctUrl = wfExpandUrl( $correctUrl, PROTO_CANONICAL );
		header( "Location: $correctUrl", true, 301 );
		echo 'This endpoint does not support "path info", i.e. extra text between "api.php"'
			. 'and the "?". Remove any such text and try again.';
		die( 1 );
	}

	// Set a dummy $wgTitle, because $wgTitle == null breaks various things
	// In a perfect world this wouldn't be necessary
	$wgTitle = Title::makeTitle( NS_SPECIAL, 'Badtitle/dummy title for API calls set in api.php' );

	// RequestContext will read from $wgTitle, but it will also whine about it.
	// In a perfect world this wouldn't be necessary either.
	RequestContext::getMain()->setTitle( $wgTitle );

	try {
		// Construct an ApiMain with the arguments passed via the URL. What we get back
		// is some form of an ApiMain, possibly even one that produces an error message,
		// but we don't care here, as that is handled by the constructor.
		$processor = new ApiMain( RequestContext::getMain(), true );

		// Last chance hook before executing the API
		Hooks::runner()->onApiBeforeMain( $processor );
		if ( !$processor instanceof ApiMain ) {
			throw new MWException( 'ApiBeforeMain hook set $processor to a non-ApiMain class' );
		}
	} catch ( Throwable $e ) {
		// Crap. Try to report the exception in API format to be friendly to clients.
		ApiMain::handleApiBeforeMainException( $e );
		$processor = false;
	}

	// Process data & print results
	if ( $processor ) {
		$processor->execute();
	}

	// Log what the user did, for book-keeping purposes.
	$endtime = microtime( true );

	// Log the request
	if ( $wgAPIRequestLog ) {
		$items = [
			wfTimestamp( TS_MW ),
			$endtime - $starttime,
			$wgRequest->getIP(),
			$wgRequest->getHeader( 'User-agent' )
		];
		$items[] = $wgRequest->wasPosted() ? 'POST' : 'GET';
		if ( $processor ) {
			try {
				$manager = $processor->getModuleManager();
				$module = $manager->getModule( $wgRequest->getRawVal( 'action' ), 'action' );
			} catch ( Throwable $ex ) {
				$module = null;
			}
			if ( !$module || $module->mustBePosted() ) {
				$items[] = "action=" . $wgRequest->getRawVal( 'action' );
			} else {
				$items[] = wfArrayToCgi( $wgRequest->getValues() );
			}
		} else {
			$items[] = "failed in ApiBeforeMain";
		}
		LegacyLogger::emit( implode( ',', $items ) . "\n", $wgAPIRequestLog );
		wfDebug( "Logged API request to $wgAPIRequestLog" );
	}

	$mediawiki = new MediaWiki();
	$mediawiki->doPostOutputShutdown();
}
