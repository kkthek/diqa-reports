<?php
namespace DIQA\Reports\Utils;

use Exception;
use mikehaertl\wkhtmlto\Pdf;
use DIQA\Util\LoggerUtils;

class PDFUtils {

    /**
     * Creates a PDF from the page specified by $fullUrl
     *
     * @param string $fullUrl
     * @param array $sessionCookie
     *             [ 'cookieprefix' => '...', 'sessionId' => '...' ]
     *             see RequestUtils::getSessionCookieForExportUser
     * @param string $mulitStampFile
     *         if $mulitStampFile equals null: don't add the multistamp PDF file 
     *
     * @throws Exception
     * @return string The (temporary) pdf file
     */
    public static function createPDF($fullUrl, $sessionCookie, $mulitStampFile = null) {
        global $wkhtmltopdfBin;
        global $wgExportMargins;

        global $wgPdfViewportSize;

        if (! isset ( $wkhtmltopdfBin ) || ! file_exists ( $wkhtmltopdfBin )) {
            static::logError("WKHTMLTOPDF not found: $wkhtmltopdfBin");
            throw new Exception("WKHTMLTOPDF not found: $wkhtmltopdfBin");
        }

        if (! isset ($wgPdfViewportSize)) {
            $wgPdfViewportSize = '1280x1024';
        }

        $pdf = new Pdf();

        $options = array (
                'viewport-size' => $wgPdfViewportSize,

                // 100 is a magic number indicating that a certain time has lapsed
                'window-status' => 100,

                //Use print media-type instead of screen
                'print-media-type',

                //cookie information for this session
                'cookie' => [
                    $sessionCookie ['cookieprefix'] . '_session' => $sessionCookie ['sessionId'],
        			$sessionCookie ['cookieprefix'] . 'UserID' => $sessionCookie['lguserid'],
        			$sessionCookie ['cookieprefix'] . 'UserName' => $sessionCookie['lgusername'],
                ]
        );
        if ($mulitStampFile !== null)
        {
            $options['margin-top'] = $wgExportMargins['top'];
            $options['margin-right'] = $wgExportMargins['right'];
            $options['margin-bottom'] = $wgExportMargins['bottom'];
            $options['margin-left'] = $wgExportMargins['left'];
        }
        $pdf->setOptions($options);

        $pdf->binary = $wkhtmltopdfBin;

        $pdf->addPage ( $fullUrl );

        $tmpFile = sys_get_temp_dir () . '/pdf_' . uniqid (). '.pdf';

        session_write_close();
        if (! $pdf->saveAs ( $tmpFile )) {
            static::logError("The following URL could not be exported: " . $fullUrl);
            static::logError("PDF error: " . $pdf->getError());
            throw new Exception("Problem in PdfUtils::createPDF('$fullUrl'):\n" . $pdf->getError());
        }

        if ($mulitStampFile !== null)
        {
            
            $tmpFile = self::addMulitStamp ( $tmpFile, $mulitStampFile );
        }

        return $tmpFile;
    }

    /**
     * @param $inputFileName
     * @throws Exception
     * @return string The (temporary) pdf file
     */
    private static function addMulitStamp($inputFileName, $mulitStampFile) {
        global $pdftkBin;

        if (! isset ( $pdftkBin ) || ! file_exists ( $pdftkBin )) {
            static::logError("PDFTK not found: $pdftkBin");
            return $inputFileName;
        }

        try {
            $msFile = sys_get_temp_dir () . '/pdf_ms_' . uniqid (). '.pdf';
            $cmdMultistamp = $pdftkBin . ' ' . $inputFileName . ' multistamp ' . $mulitStampFile . ' output ' . $msFile;

            exec(escapeshellcmd($cmdMultistamp), $output, $returnvar);

            if($returnvar !== 0) {
                static::logError('Error in PDFTK: ' . $returnvar . ':' . implode("\n", $output));
            }

        } catch (Exception $e) {
            static::logError('Error in PDFTK: ' . $e->getCode() . ':' . $e->getMessage());
            return $inputFileName;
        }
        return $msFile;
    }

    private static function logError($msg) {
        $logger = new LoggerUtils('createPDF', 'Reports');
        $logger->error($msg);
    }

    private static function logDebug($msg) {
        $logger = new LoggerUtils('createPDF', 'Reports');
        $logger->debug($msg);
    }

}