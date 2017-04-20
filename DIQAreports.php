<?php

use DIQA\Reports\SpreadsheetCsvResultPrinter;
use DIQA\Reports\CIDOC_CRM\SpecialExportCidoc;
use DIQA\Reports\OneOffixx\SpecialExportOneOffixx;

use DIQA\Reports\PdfExportResultPrinter;
use DIQA\Reports\PdfExportResultPrinterAsync;

/**
 * DIQAreports
 *
 * @defgroup DIQA Reports
 *
 * @author Kai Kühn
 *
 * @version 0.1
 */

/**
 * The main file of the ODB Reports extension
 *
 * @file
 * @ingroup ODB Reports
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This file is part of a MediaWiki extension, it is not a valid entry point.' );
}

if ( defined( 'DIQA_REPORTS_VERSION' ) ) {
	// Do not load more than once
	return 1;
}

define( 'DIQA_REPORTS_VERSION', '0.1' );

global $wgVersion;
global $wgExtensionCredits;
global $wgExtensionMessagesFiles;
global $wgMessagesDirs;
global $wgHooks;
global $wgResourceModules;
global $wgActions;
global $wgJobClasses;

// register extension
$wgExtensionCredits[ 'odb' ][] = array(
	'path' => __FILE__,
	'name' => 'DIQA Reports',
	'author' => array( 'DIQA Projektmanagement GmbH' ),
	'license-name' => 'GPL-2.0+',
	'url' => 'http://www.diqa-pm.com',
	'descriptionmsg' => 'diqareports-desc',
	'version' => DIQA_REPORTS_VERSION,
);

$dir = dirname( __FILE__ );


$wgExtensionMessagesFiles['DIQAreports'] = $dir . '/DIQAreports.i18n.php';


$wgHooks['ParserFirstCallInit'][] = 'wfDIQAreportsSetup';
$wgHooks['ParserFirstCallInit'][] = 'wfDIQAreportsRegisterModules';
$wgHooks['LoadExtensionSchemaUpdates'][] = 'wfDIQAreportsDBUpdate';

$GLOBALS ['wgAPIModules'] ['diqa-download'] = 'DIQA\Reports\DownloadReportAPI';

$wgResourceModules['ext.reports.core'] = array(
		'localBasePath' => $dir,
		'remoteExtPath' => 'DIQAreports',
		'scripts' => array('scripts/reports.js'),
		'dependencies' => array(  )
);

$wgActions['exportpdf'] = 'DIQA\Reports\ExportPDFAction';
$wgJobClasses['ExportPDFJob'] = 'DIQA\Reports\Jobs\ExportPDFJob';
$wgJobClasses['ExportZipJob'] = 'DIQA\Reports\Jobs\ExportZipJob';

SpreadsheetCsvResultPrinter::setup();

PdfExportResultPrinter::setup();
PdfExportResultPrinterAsync::setup();

/**
 * Setup the extension
 */
function wfDIQAreportsSetup(&$parser) {
	global $wgOut, $wgODBReportRenderTimeout;

	$script = "";
	if (!isset($wgODBReportRenderTimeout)) {
		$wgODBReportRenderTimeout = 120;
	}
	$script .= "\nvar WG_ODB_RENDER_TIMEOUT=$wgODBReportRenderTimeout;";
	$wgOut->addScript(
		'<script type="text/javascript">'.$script.'</script>'
	);
	

	$wgOut->addModules('ext.reports.core');

	return true;
}

function wfDIQAreportsRegisterModules() {
	return true;
}

function wfDIQAreportsDBUpdate() {
	require_once('maintenance/setup.php');
	$setup = new Setup();
	$setup->execute();
}