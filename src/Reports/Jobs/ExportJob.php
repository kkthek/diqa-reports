<?php
namespace DIQA\Reports\Jobs;

use Exception;
use Job;
use mikehaertl\wkhtmlto\Pdf;
use DIQA\Reports\Utils\ProgressUtils;

use Title;
use DIQA\Reports\Utils\PDFUtils;
use DIQA\Util\LoggerUtils;
use DIQA\Util\RequestUtils;


/**
 * Job including basic functionality for PDF-Exports
 *
 * @author Kai
 *
 */
abstract class ExportJob extends Job {

	protected $logger = null;

	/**
	 * @param Title $title
	 * @param array $params job parameters (timestamp)
	 */
	function __construct( $jobName, $title, $params ) {
		parent::__construct( $jobName, $title, $params );
		$this->logger = new LoggerUtils($jobName, 'Reports');
	}

	protected function makePdfFromUrls($urls) {
		global $wgServer;
		global $wgServerHTTP;
		global $wgODBTechnicalUser;
		global $wgODBTechnicalUserPassword;
		global $wgScriptPath;

		$user = $this->params['userId'];
		$baseFile = basename($this->params['tmpFile']);

		$sessionCookie = RequestUtils::getSessionCookieForExportUser( $wgODBTechnicalUser, $wgODBTechnicalUserPassword );

		$this->logger->log("Erzeuge PDF-Files für ".count($urls)." Wikiseiten.");
		ProgressUtils::setJobProgress(
				$user, $baseFile, get_class ( $this ), 0, count($urls),
				'Starte PDF-Erzeugungsprozess'
				);

		$i = 0;
		$tmpFiles = [];
		foreach ($urls as $nsTitle => $mediawikiUrl) {
			$this->logger->debug("Erzeuge PDF für $nsTitle.");
			ProgressUtils::setJobProgress(
					$user, $baseFile, get_class ( $this ), $i, count($urls),
					"Erzeuge PDF für $nsTitle"
				);
			$i++;

			if ($this->isFestgesetztePDF($nsTitle) === true) {
				$tmpFiles[$nsTitle] = $this->getPathFestgesetztePDF($nsTitle);
			} else {
				$fullUrl = str_replace($wgServer, $wgServerHTTP, $mediawikiUrl);
				$tmpFile = PDFUtils::createPDF($fullUrl, $sessionCookie);
				$tmpFiles[$nsTitle] = $tmpFile;
			}
		}

		ProgressUtils::setJobProgress(
			$user,
			$baseFile,
			get_class ( $this ),
			count($urls),
			count($urls),
			sprintf('<a href="%s/api.php?action=diqa-download&download=%s">Download</a>', $wgScriptPath, urlencode($baseFile))
		);

		return $tmpFiles;
	}

	protected function isFestgesetztePDF($nsTitle) {
		$isInFileNamespace = false;

		if (strpos($nsTitle, 'Datei:') !== false) {
			$isInFileNamespace = true;
		}

		return $isInFileNamespace;
	}

	protected function getPathFestgesetztePDF($nsTitle) {
		$localRefPath = '';

		$filePage = wfLocalFile(Title::newFromText($nsTitle));
		$localRefPath = $filePage->getLocalRefPath();

		return $localRefPath;
	}

}