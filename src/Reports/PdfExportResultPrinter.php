<?php

namespace DIQA\Reports;

use Exception;
use mikehaertl\wkhtmlto\Pdf;
use DIQA\Reports\Utils\PDFUtils;

use SMW\FileExportPrinter;
use SMW\ResultPrinter;
use SMWQueryResult;
use Title;
use DIQA\Util\LoggerUtils;
use DIQA\Util\RequestUtils;


/**
 * Result printer to print results as a PDF or ZIP file.
 */
class PdfExportResultPrinter extends FileExportPrinter {
    private $format = 'pdfexport';

    /**
     * PDF or ZIP; default is pdf; see $this->getParamDefinitions()
     */
    protected $downloadas = 'pdf';
	protected $logger = null;
	
    private $renderpage = '';
    private $idparam = '';

    /**
     * Date for PDF and ZIP file name
     */
    private $filedate = '';
    private $exportVonBildern = true;
    private $exportVonGeschuetztenTextteilen = true;
    private $statusse = [];

    /**
     * Setup for the PDF Export Result Printer.
     * Add new result format to the list of result formats.
     *
     * @param void
     * @return void
     */
    static public function setup(){
         global $smwgResultFormats;

         $smwgResultFormats['pdfexport'] = 'DIQA\Reports\PdfExportResultPrinter';
     }

     public function __construct( $format, $inline = true, $useValidator = false ) {
     	parent::__construct($format, $inline, $useValidator);
     	$this->logger = new LoggerUtils('PdfExportResultPrinter', 'Reports');
     }
     
    /**
     * Get the mime type for the files.
     *
     * @see \SMW\ExportPrinter::getMimeType()
     * @param SMWQueryResult $queryResult the query result
     * @return string the mime type
     */
    public function getMimeType(SMWQueryResult $queryResult) {
        return '';
    }

    /**
     * If $outputMode equels SMW_OUTPUT_FILE then send the PDF or ZIP file to browser.
     * Otherwise output link to this result builder.
     * The file name is e.g. odbwiki_export_20160512_150020.pdf.
     * The file name is e.g. odbwiki_export_20160512_150020.zip\odbwiki_export_20160512_150020.pdf.
     *
     * @see \SMW\ResultPrinter::getResultText()
     * @param SMWQueryResult $queryResult the query result
     * @param int $outputMode the output mode
     * @return string link to this result builder
     */
    protected function getResultText(SMWQueryResult $queryResult, $outputMode) {
        if ($outputMode == SMW_OUTPUT_FILE) {
            if (strcmp($this->downloadas, 'pdf') === 0)
            {
                $result = $this->getResultFileContents($queryResult);
               
                header ( "Content-type: application/pdf" );
                header ( sprintf ( "Content-Disposition: attachment; filename=\"odbwiki_export_%s.pdf\"", $this->filedate ) );
                header ( "Pragma: no-cache" );
                header ( "Expires: 0" );

                ob_end_clean();
                $output = fopen ( "php://output", "w" );
                fwrite ( $output, $result);
                fclose ( $output );

                exit;
            }
            else // zip
            {
                $result = $this->getResultFileContents($queryResult);

                header ( "Content-type: application/zip" );
                header ( sprintf ( "Content-Disposition: attachment; filename=\"odbwiki_export_%s.zip\"", $this->filedate ) );
                header ( "Pragma: no-cache" );
                header ( "Expires: 0" );

                ob_end_clean();
                $output = fopen ( "php://output", "w" );
                fwrite ( $output, $result);
                fclose ( $output );

                exit;
            }
        } else {
            return $this->getLinkToFile($queryResult, $outputMode);
        }
    }

    /**
     * Create link to this result builder.
     *
     * @param SMWQueryResult $queryResult the query result
     * @param int $outputMode the output mode
     * @return string the link to this result builder
     */
    private function getLinkToFile(SMWQueryResult $queryResult, $outputMode) {
        $this->isHTML = ($outputMode == SMW_OUTPUT_HTML);
        return $this->getLink($queryResult, $outputMode)->getText($outputMode, $this->mLinker);
    }

    /**
     * Get the content of the PDF or ZIP file.
     *
     * @param SMWQueryResult $queryResult the query result
     * @return string the content of the PDF or ZIP file
     */
    private function getResultFileContents(SMWQueryResult $queryResult) {
        $result = '';

        $filedate = date ( "Ymd_His" );
        $this->filedate = $filedate;
        
        $pdfUrls = $this->makePdfUrls($queryResult);

        if (strcmp($this->downloadas, 'pdf') === 0) {
            // PDF
            $pdfUrl = $this->makePdf($pdfUrls, $filedate);

            if (is_file($pdfUrl) === true)
            {
                $result = file_get_contents($pdfUrl);
            }
        }
        else {
            // ZIP
            $zipUrl = $this->makeZip($pdfUrls, $filedate);

            if (is_file($zipUrl) === true)
            {
                $result = file_get_contents($zipUrl);
            }
        }

        return $result;
    }

    /**
     * Get the paths to the PDF files.
     * The file is e.g. /tmp/pdf_573499370832c.pdf.
     * The file will not be deleted.
     *
     * @param SMWQueryResult $queryResult the query result
     * @return string the path to the PDF file
     */
    private function makePdfUrls(SMWQueryResult $queryResult) {
        $mediawikiUrls = [];
        $pdfUrls = [];
        $exportVonBildern = '';
        $exportVonGeschuetztenTextteilen = '';

        $exportVonBildern = $this->getExportVonBildern($queryResult);
        $this->exportVonBildern = $exportVonBildern;
        $exportVonGeschuetztenTextteilen = $this->getExportVonGeschuetztenTextteilen($queryResult);
        $this->exportVonGeschuetztenTextteilen = $exportVonGeschuetztenTextteilen;
        $statusse = $this->getStatusse($queryResult);
        $this->statusse = $statusse;
        $mediawikiUrls = $this->getMediawikiUrls($queryResult, $exportVonBildern, $exportVonGeschuetztenTextteilen);

        $pdfUrls = $this->makePdfFromUrls($mediawikiUrls);

        return $pdfUrls;
    }

    /**
     * Get the path to the PDF file.
     * The file is e.g. /tmp/odbwiki_export_20160524_145840.zip.
     * The file will not be deleted.
     *
     * @param array $pdfUrls path to the PDF files
     * @param string $filedate the date
     * @return string $pdfUrl path to the resuting PDF file
     */
    private function makePdf($pdfUrls, $filedate) {
        global $pdftkBin;

        if (! isset ( $pdftkBin ) || ! file_exists ( $pdftkBin )) {
            throw new Exception("PDFTK not found: $pdftkBin");
        }

        $pdfUrl = '/tmp/odbwiki_export_' . $filedate . '.pdf';

        $cmd = $pdftkBin . ' ' . implode(' ',  array_values($pdfUrls)) . ' cat output ' . $pdfUrl;

        $output = null;
        $returnvar = null;
        exec(escapeshellcmd($cmd), $output, $returnvar);

        return $pdfUrl;
    }

    /**
     * Get the path to the ZIP file.
     * The file is e.g. /tmp/odbwiki_export_20160524_145840.zip.
     * The file will not be deleted.
     *
     * @param array $pdfUrls path to the PDF files
     * @param string $filedate the date
     * @return string $zipUrl path to the ZIP file
     */
    private function makeZip($pdfUrls, $filedate) {
        $zipUrl = '/tmp/odbwiki_export_' . $filedate . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipUrl, \ZipArchive::CREATE);

        foreach ($pdfUrls as $name => $path) {
            $pdfFilename = str_replace(':', '_', $name . '.pdf');

            $zip->addFile($path, $pdfFilename);
        }

        $zip->close();

        return $zipUrl;
    }

    /**
     * Get value of form field 'Bildanhang exportieren' (ExportVonGeschuetztenTextteilen).
     *
     * @param SMWQueryResult $queryResult the query result
     * @return boolean If "Bildanhang exportieren" is selected, result is true otherwise false.
     */
    protected function getExportVonBildern($queryResult) {
        $resultString = 'Ja';
        $resultBoolean = true;

        $queryString = $queryResult->getQueryString ();
        preg_match ( '/\[\[ExportVonBildern::(.{1,})\]\]/', $queryString, $matchExportVonBildern );
        if (array_key_exists ( 1, $matchExportVonBildern ) === true) {
            $resultString = $matchExportVonBildern [1];
        }
        if (strcmp ( $resultString, 'Ja' ) === 0) {
            $resultBoolean = true;
        } else {
            $resultBoolean = false;
        }

        return $resultBoolean;
    }

    /**
     * Get value of form field 'Schutzziel exportieren' (ExportVonBildern).
     *
     * @param SMWQueryResult $queryResult the query result
     * @return boolean If "Bildanhang exportieren" is selected, result is true otherwise false.
     */
    protected function getExportVonGeschuetztenTextteilen($queryResult) {
        $resultString = 'Ja';
        $resultBoolean = true;

        $queryString = $queryResult->getQueryString ();
        preg_match ( '/\[\[ExportVonGeschuetztenTextteilen::(.{1,})\]\]/', $queryString, $matchExportVonGeschuetztenTextteilen );
        if (array_key_exists ( 1, $matchExportVonGeschuetztenTextteilen ) === true) {
            $resultString = $matchExportVonGeschuetztenTextteilen [1];
        }
        if (strcmp ( $resultString, 'Ja' ) === 0) {
            $resultBoolean = true;
        } else {
            $resultBoolean = false;
        }

        return $resultBoolean;
    }

    /**
     * Get selected values of form filed 'Status'.
     *
     * @param SMWQueryResult $queryResultthe query result
     * @return array string the selected values from filed 'Status'.
     */
    protected function getStatusse($queryResult) {
        $result = [ ];

        $queryString = $queryResult->getQueryString ();
        preg_match ( '/\[\[Status::(.{1,})\]\]/', $queryString, $matchStatusse );
        if (array_key_exists ( 1, $matchStatusse ) === true) {
            $result = explode ( '||', $matchStatusse [1] );
        }

        return $result;
    }

    /**
     * Get the mediawiki URLs.
     * e.g. http://odbwiki.localhost/mediawiki/index.php/Test:Inventar_1_3?printable=yes&ExportVonBildern=Nein&ExportVonGeschuetztenTextteilen=Ja
     *
     *
     * @param SMWQueryResult $queryResult the query result
     * @param string $exportVonBildern 'Ja' or 'Nein'
     * @param string $exportVonGeschuetztenTextteilen 'Ja' or 'Nein'
     * @retrun array string the mediawiki URLs
     */
    protected function getMediawikiUrls($queryResult, $exportVonBildern, $exportVonGeschuetztenTextteilen) {
        global $wgServer, $wgScriptPath;
        $mediawikiUrls = [ ];
        $mediawikiUrl = '';

        $SMWDIWikiPages = $queryResult->getResults ();
        foreach ( $SMWDIWikiPages as $SMWDIWikiPage ) {

            $title = $SMWDIWikiPage->getTitle ()->getText ();
            $namespaceId = $SMWDIWikiPage->getNamespace ();
            $namespaces = $this->getContext ()->getLanguage ()->getNamespaces ();
            $namespaceName = $namespaces [$namespaceId];

            $mediawikiUrl = $wgServer . $wgScriptPath . '/index.php/';

            if ((empty ( $this->renderpage ) !== true) && (empty ( $this->idparam ) !== true)) {
                $mediawikiUrl .= $this->renderpage . '?' . $this->idparam . '=';

                // Main Namespace
                if ($namespaceId == 0) {
                    $nsTitle = str_replace ( ' ', '_', $title );
                    $mediawikiUrl .= $nsTitle;
                } else {
                    $nsTitle = str_replace ( ' ', '_', $namespaceName . ':' . $title );
                    $mediawikiUrl .= $nsTitle;
                }

                $mediawikiUrl .= '&mode=exportpdf&action=purge&printable=yes';
                $mediawikiUrls [$nsTitle] = $mediawikiUrl;
            } else {
                // Main Namespace
                if ($namespaceId == 0) {
                    $nsTitle = str_replace ( ' ', '_', $title );
                    $mediawikiUrl .= $nsTitle;
                } else {
                    $nsTitle = str_replace ( ' ', '_', $namespaceName . ':' . $title );
                    $mediawikiUrl .= $nsTitle;
                }

                if ($exportVonBildern == true) {
                    $exportVonBildern = 'wahr';
                } else {
                    $exportVonBildern = '0';
                }
                if ($exportVonGeschuetztenTextteilen == true) {
                    $exportVonGeschuetztenTextteilen = 'wahr';
                } else {
                    $exportVonGeschuetztenTextteilen = '0';
                }

                $mediawikiUrl .= '?mode=exportpdf&action=purge&printable=yes&ExportVonBildern=' . $exportVonBildern . '&ExportVonGeschuetztenTextteilen=' . $exportVonGeschuetztenTextteilen;
                $mediawikiUrls [$nsTitle] = $mediawikiUrl;
            }
        }

        return $mediawikiUrls;
    }

    /**
     * Create the PDF file from the mediawiki urls.
     * Create the path to the PDF file.
     * The file is e.g. /tmp/pdf_573499370832c.pdf.
     * The file will not be deleted.
     *
     * @param array string $urls the mediawiki urls
     * @return string the path the PDF file
     * @throws Exception
     */
    private function makePdfFromUrls($urls) {
        global $wgServer;
        global $wgServerHTTP;
        global $wgODBTechnicalUser;
        global $wgODBTechnicalUserPassword;
        global $wgExportDokumentrahmen;

        $tmpFiles = [];

        $sessionCookie = RequestUtils::getSessionCookieForExportUser( $wgODBTechnicalUser, $wgODBTechnicalUserPassword );

        $addMulitStamp = false;
        if (array_key_exists($this->renderpage, $wgExportDokumentrahmen)) {
            $addMulitStamp = true;
            $mulitStampFile = empty($wgExportDokumentrahmen[$this->renderpage]) ? null : $wgExportDokumentrahmen[$this->renderpage];
            if ($mulitStampFile === null) {
                $addMulitStamp = false;
            }
        }

        foreach ($urls as $nsTitle => $mediawikiUrl) {
            if ($this->isFestgesetztePDF($nsTitle) === true) {
                $tmpFiles[$nsTitle] = $this->getPathFestgesetztePDF($nsTitle);
            } else {
                $fullUrl = str_replace($wgServer, $wgServerHTTP, $mediawikiUrl);
                
                if ($addMulitStamp === true) {
                       $tmpFile = PDFUtils::createPDF($fullUrl, $sessionCookie, $mulitStampFile);
                }
                else {
                    $tmpFile = PDFUtils::createPDF($fullUrl, $sessionCookie, null);
                }
                
                $tmpFiles[$nsTitle] = $tmpFile;
            }
        }

        return $tmpFiles;
    }

    /**
     * (non-PHPdoc)
     * @see \SMW\ResultPrinter::handleParameters()
     * @param array $params the params
     * @param int $outputmode the output mode
     */
    protected function handleParameters(array $params, $outputmode) {
        parent::handleParameters ( $params, $outputmode );

        if (array_key_exists ( 'format', $params )) {
            $format = trim ( $params ['format'] );
            $format = str_replace('\\t', "\t", $format);
            $format = str_replace('\\n', "\n", $format);
            $this->format = $format;
        }

        if (array_key_exists ( 'downloadas', $params )) {
            $downloadas = trim ( $params ['downloadas'] );
            $downloadas = str_replace('\\t', "\t", $downloadas);
            $downloadas = str_replace('\\n', "\n", $downloadas);
            $this->downloadas = $downloadas;
        }

        if (array_key_exists ( 'renderpage', $params )) {
            $renderpage = trim ( $params ['renderpage'] );
            $renderpage = str_replace('\\t', "\t", $renderpage);
            $renderpage = str_replace('\\n', "\n", $renderpage);
            $this->renderpage = $renderpage;
        }

        if (array_key_exists ( 'idparam', $params )) {
            $idparam = trim ( $params ['idparam'] );
            $idparam = str_replace('\\t', "\t", $idparam);
            $idparam = str_replace('\\n', "\n", $idparam);
            $this->idparam = $idparam;
        }
    }

    /**
     * (non-PHPdoc)
     * @see \SMW\ResultPrinter::getParamDefinitions()
     * @param array $definitions
     * @return array $params
     */
    public function getParamDefinitions( array $definitions ) {
        $params = parent::getParamDefinitions( $definitions );

        $params[] = array(
            'name' => 'downloadas',
            'message' => 'downloadas',
            'default' => 'pdf',
            'aliases' => 'downloadas'
        );

        $params[] = array(
            'name' => 'renderpage',
            'message' => 'renderpage',
            'default' => '',
            'aliases' => 'renderpage'
        );

        $params[] = array(
            'name' => 'idparam',
            'message' => 'idparam',
            'default' => '',
            'aliases' => 'idparam'
        );

        return $params;
    }

    /**
     * Check if Title has Festgesetzte PDF.
     *
     * @param unknown $nsTitle
     * @param string $ExportVonGeschuetztenTextteilen
     * @param string $ExportVonBildern
     * @return boolean wether
     */
    private function isFestgesetztePDF($nsTitle) {
        $isInFileNamespace = false;

        if (strpos($nsTitle, 'Datei:') !== false) {
            $isInFileNamespace = true;
        }

        return $isInFileNamespace;
    }

    /**
     * Get path to Festgesetzte PDF.
     *
     * @param unknown $nsTitle
     * @param string $ExportVonGeschuetztenTextteilen
     * @param string $ExportVonBildern
     * @return string the path to  Festgesetzte PDF
     */
    private function getPathFestgesetztePDF($nsTitle) {
        $localRefPath = '';

        $filePage = wfLocalFile(\Title::newFromText($nsTitle));
        $localRefPath = $filePage->getLocalRefPath();
		if ($localRefPath === false) {
			$msg = sprintf('%s has no file attached.', $nsTitle);
			$this->logger->warn($msg);
			return $this->createText2Pdf($msg);
		}
        return $localRefPath;
    }
    
    /**
     * Create a PDF file from a text.
     * 
     * @param string $text
     * @throws Exception
     * @return string Path to PDF file
     */
    private function createText2Pdf($text) {
    	
    	$tmpFileText = sys_get_temp_dir () . '/text_' . uniqid (). '.txt';
    	$tmpFilePdf = sys_get_temp_dir () . '/pdf_' . uniqid (). '.pdf';
    	
    	$handle = fopen($tmpFileText, 'w');
    	fwrite($handle, $text);
    	fclose($handle);
    	
    	exec ( sprintf ( 'convert %s %s', $tmpFileText, $tmpFilePdf ), $output, $ret );
    	if ($ret !== 0) {
    		throw new Exception ( 'Error on converting text file to pdf (imagemagick missing?)');
    	}
    	
    	return $tmpFilePdf;
    }
}
