<?php
namespace DIQA\Reports;

class DownloadReportAPI extends \ApiBase {

	public function __construct($query, $moduleName) {
		parent::__construct ( $query, $moduleName );
		
	}

	public function execute() {
		$params = $this->extractRequestParams ();

		$this->download ( $params );
	}

	

	private function download($params) {
		
		$tmpDir= sys_get_temp_dir ();

		$filepath = $params['download'];
		$filepath = $tmpDir . "/$filepath";
		$filepath = realpath($filepath);
		
		if ($filepath === false) {
			throw new \Exception("Invalid access");
		}
		
		if (strpos($filepath, $tmpDir) !== 0) {
			throw new \Exception("Invalid access");
		}
		
	    $fileinfo = pathinfo($filepath);
	    $sendname = $fileinfo['filename'] . '.' . strtoupper($fileinfo['extension']);
	
	    header('Content-Type: application/'.$fileinfo['extension']);
	    header("Content-Disposition: attachment; filename=\"$sendname\"");
	    header('Content-Length: ' . filesize($filepath));
	    echo file_get_contents($filepath);
	    die();
	}

	protected function getAllowedParams() {
		return array (
				'download' => null,
				
		);
	}
	protected function getParamDescription() {
		return array (
				'download' => 'Download-File',
			
		);
	}
	protected function getDescription() {
		return 'DownloadAction';
	}
	protected function getExamples() {
		return array (
				'api.php?action=diqa-download&download=pdf_57b6f97287051.pdf'
		);
	}
	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}
}