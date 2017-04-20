<?php
namespace DIQA\Reports;

use Exception;
use JobQueueGroup;

use DIQA\Reports\Jobs\ExportPDFJob;
use DIQA\Reports\Jobs\ExportZipJob;
use DIQA\Reports\PdfExportResultPrinter;
use SMWQueryResult;
use Title;
use DIQA\Util\LoggerUtils;

class PdfExportResultPrinterAsync extends PdfExportResultPrinter {

	private $callback = '';

	/**
	 * Setup for the PDF Export Result Printer.
	 * Add new result format to the list of result formats.
	 *
	 * @param void
	 * @return void
	 */
	static public function setup() {
		global $smwgResultFormats;

		$smwgResultFormats['pdfexportasync'] = 'DIQA\Reports\PdfExportResultPrinterAsync';
	}


	public function __construct( $format, $inline = true, $useValidator = false ) {
		parent::__construct($format, $inline, $useValidator);
		$this->logger = new LoggerUtils('PdfExportResultPrinterAsync', 'DIQAreports');
	}

	/* (non-PHPdoc)
	 * @see \SMW\ResultPrinter::getResultText()
	 */
	protected function getResultText(SMWQueryResult $res, $outputMode) {

		if ($outputMode == SMW_OUTPUT_FILE) {
			$this->createJob($this->downloadas, $res);

			ob_end_clean();
			global $wgServer, $wgScriptPath;
			header("Location: $wgServer$wgScriptPath/index.php/Spezial:ODBJobs");

        } else {
        	return parent::getResultText($res, $outputMode);
        }
	}


	/**
	 * @param string $type "pdf" or "zip"
	 * @throws Exception if type is not supported
	 */
	private function createJob($type, SMWQueryResult $res) {
		global $wgUser;

		$makeWikiURLs = [];

		$tmpFile= sys_get_temp_dir () . '/' . $type . '_' . uniqid (). '.' . $type;
		$makeWikiURLs = $this->makeWikiURLs($res);

		$this->logger->log("Erzeuge Job für den $type-Export von ".count($makeWikiURLs)." Wikiseiten.");

		$jobParams = array();
		$jobParams['makeWikiURLs'] = $makeWikiURLs;
		$jobParams['tmpFile'] = $tmpFile;
		$jobParams['userId'] = $wgUser->getId();

		$title = Title::makeTitle(NS_SPECIAL, 'Report');
		if($type == 'pdf') {
			$job = new ExportPDFJob( $title, $jobParams );
		} else if($type == 'zip') {
			$job = new ExportZipJob( $title, $jobParams );
		} else {
			throw new Exception("Typ $type wird nicht unterstützt.");
		}

		JobQueueGroup::singleton()->push( $job );
	}


	private function makeWikiURLs($queryResult) {
		$mediawikiUrls = [];

		$exportVonBildern = '';
		$exportVonGeschuetztenTextteilen = '';

		$exportVonBildern = $this->getExportVonBildern($queryResult);
		$this->exportVonBildern = $exportVonBildern;

		$exportVonGeschuetztenTextteilen = $this->getExportVonGeschuetztenTextteilen($queryResult);
		$this->exportVonGeschuetztenTextteilen = $exportVonGeschuetztenTextteilen;

		$statusse = $this->getStatusse($queryResult);
		$this->statusse = $statusse;

		$mediawikiUrls = $this->getMediawikiUrls($queryResult, $exportVonBildern, $exportVonGeschuetztenTextteilen);
		return $mediawikiUrls;
	}

	/* (non-PHPdoc)
	 * @see \SMW\ExportPrinter::getMimeType()
	 */
    public function getMimeType(SMWQueryResult $queryResult) {
        return '';
    }

    protected function handleParameters(array $params, $outputmode) {
    	parent::handleParameters($params, $outputmode);
    	if (array_key_exists ( 'callback', $params )) {
    		$callback = trim ( $params ['callback'] );
    		$callback = str_replace('\\t', "\t", $callback);
    		$callback = str_replace('\\n', "\n", $callback);
    		$this->callback = $callback;
    	}
    }

    public function getParamDefinitions( array $definitions ) {
    	$params = parent::getParamDefinitions( $definitions );

    	$params[] = array(
    			'name' => 'callback',
    			'message' => 'callback',
    			'default' => '',
    			'aliases' => 'callback'
    	);

    	return $params;
    }

}