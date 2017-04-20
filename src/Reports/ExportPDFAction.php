<?php

namespace DIQA\Reports;

use Action;
use CurlHttpRequest;
use Exception;
use DIQA\Util\RequestUtils;
use DIQA\Reports\Utils\PDFUtils;


class ExportPDFAction extends Action {
	public function show() {
	    try {
	    	
            global $wgTitle;
	    	$additionalParameters = [
	    			'action' => 'purge',
	    			'mode' => 'exportpdf'
	    	];
	    	
            $paramStr = $this->getRequest()->appendQueryArray($additionalParameters);
            $fullURL = $wgTitle->getFullURL( $paramStr );
            
            $fileName = self::exportPDFAction($fullURL);

            $output = fopen ( "php://output", "w" );
            fwrite ( $output, file_get_contents ( $fileName ) );
            fclose ( $output );

            exit;
        } catch (Exception $e) {
	        $this->getOutput()->addHTML('<p>'.$e->getMessage().'</p>' );
	    }
	}
	
	/**
	 * turns the page identified by the request to PDF and stores it in a temporary file
	 * returns the filename of the resulting PDF document
	 *
	 * @param String $url
	 * @param boolean Set HTTP header fields if true
	 * @return String pointing to the filename of the created PDF document
	 * @throws Exception
	 */
	private static function exportPDFAction($url, $setHeader = true) {
		global $wgServer;
		global $wgServerHTTP;
	
		global $wgODBTechnicalUser;
		global $wgODBTechnicalUserPassword;
	
		$sessionCookie = RequestUtils::getSessionCookieForExportUser( $wgODBTechnicalUser, $wgODBTechnicalUserPassword );
	
		if ($setHeader) {
			header ( "Content-type: application/pdf" );
			header ( sprintf ( "Content-Disposition: attachment; filename=\"odbwiki_export_%s.pdf\"", date ( "Ymd_His" ) ) );
			header ( "Pragma: no-cache" );
			header ( "Expires: 0" );
		}
	
		$fullUrl = str_replace($wgServer, $wgServerHTTP, $url);
		return  PDFUtils::createPDF($fullUrl, $sessionCookie);
	}

	/*
	 * (non-PHPdoc) @see Action::getName()
	 */
	public function getName() {
		return "exportpdf";
	}

	/*
	 * (non-PHPdoc) @see Action::execute()
	 */
	public function execute() {
		// do nothing
	}


}