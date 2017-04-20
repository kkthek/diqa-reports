<?php
namespace DIQA\Reports\Jobs;

/**
 * Exports pages as one big PDF file.
 *
 * @author Kai
 *
 */
class ExportPDFJob extends ExportJob {

	/**
	 * @param Title $title
	 * @param array $params job parameters (timestamp)
	 */
	function __construct( $title, $params ) {
		parent::__construct( 'ExportPDFJob', $title, $params );
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

		$this->logger->log("Erzeuge ein großes PDF-File: $tmpFile");
		$this->makePdf($pdfFiles, $tmpFile);
	}


	/**
	 * Get the path to the PDF file.
	 * The file is e.g. /tmp/odbwiki_export_20160524_145840.zip.
	 * The file will not be deleted.
	 *
	 * @param array $pdfUrls path to the PDF files
	 * @param string $tmpFile the output file
	 * @return string $pdfUrl path to the resuting PDF file
	 */
	private function makePdf($pdfUrls, $tmpFile) {
		global $pdftkBin;

		if (! isset ( $pdftkBin ) || ! file_exists ( $pdftkBin )) {
			throw new \Exception("PDFTK not found: $pdftkBin");
		}

		$cmd = $pdftkBin . ' ' . implode(' ',  array_values($pdfUrls)) . ' cat output ' . $tmpFile;
		$output = null;
		$returnvar = null;
		exec(escapeshellcmd($cmd), $output, $returnvar);

		// delete temporary files which have been merged into one PDF
		foreach ($pdfUrls as $name => $path) {
			@unlink($path);
		}

		return $tmpFile;
	}

}