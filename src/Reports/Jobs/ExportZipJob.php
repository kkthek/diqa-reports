<?php
namespace DIQA\Reports\Jobs;

/**
 * Exports pages as a zip file.
 *
 * @author Kai
 *
 */
class ExportZipJob extends ExportJob {


	/**
	 * @param Title $title
	 * @param array $params job parameters (timestamp)
	 */
	function __construct( $title, $params ) {
		parent::__construct( 'ExportZipJob', $title, $params );
	}

	/**
	 * implementation of the actual job
	 *
	 * {@inheritDoc}
	 * @see Job::run()
	 */
	public function run() {
		$wikiURLs = $this->params['makeWikiURLs'];
		$tmpFile = $this->params['tmpFile'];

		$pdfFiles = $this->makePdfFromUrls($wikiURLs);

		$this->logger->log("Erzeuge ein großes ZIP-File: $tmpFile");
		$this->makeZip($pdfFiles, $tmpFile);
	}

	/**
     * Get the path to the ZIP file.
     * The file is e.g. /tmp/odbwiki_export_20160524_145840.zip.
     * The file will not be deleted.
     *
     * @param array $pdfUrls path to the PDF files
     * @param string $tmpFile the output file
     *
     */
    private function makeZip($pdfUrls, $tmpFile)
    {
        $zip = new \ZipArchive();
        $zip->open($tmpFile, \ZipArchive::CREATE);

        foreach ($pdfUrls as $name => $path)
        {
            $pdfFilename = str_replace(':', '_', $name . '.pdf');

            $zip->addFile($path, $pdfFilename);
        }

        $zip->close();

        // delete temporary files which have been zipped
        foreach ($pdfUrls as $name => $path) {
        	@unlink($path);
        }
    }

}