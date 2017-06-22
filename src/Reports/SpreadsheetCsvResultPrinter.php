<?php

namespace DIQA\Reports;

use Sanitizer;
use SMW\FileExportPrinter;
use SMWQuery;
use SMWQueryProcessor;
use SMWQueryResult;
use SMWWikiPageValue;
use Title;

/**
 * Result printer to print results in a CSV (deliminter separated value) format
 * that is compatible with Excel:
 *
 * @author Michael
 */
class SpreadsheetCsvResultPrinter extends FileExportPrinter {

	static public function setup() {
		global $smwgResultFormats;
		$smwgResultFormats['spreadsheetcsv'] = 'DIQA\Reports\SpreadsheetCsvResultPrinter';
	}

	protected $separator = ';';
	protected $fileName = 'result.csv';
	protected $multiValueSeparator = "; ";

	/**
	 *
	 * @see SMWResultPrinter::handleParameters
	 *
	 * @param array $params
	 * @param $outputmode
	 */
	protected function handleParameters(array $params, $outputmode) {
		parent::handleParameters ( $params, $outputmode );

		if (array_key_exists ( 'separator', $params )) {
			$separator = trim ( $params ['separator'] );
			$separator = str_replace('\\t', "\t", $separator);
			$separator = str_replace('\\n', "\n", $separator);
			$this->separator = $separator;
		}

		if (array_key_exists ( 'filename', $params )) {
			$this->fileName = str_replace ( ' ', '_', $params ['filename'] );
		}

		if (array_key_exists ( 'multivalueseparator', $params )) {
			$multiValueSeparator = $params ['multivalueseparator'];
			$multiValueSeparator = str_replace('\\t', "\t", $multiValueSeparator);
			$multiValueSeparator = str_replace('\\n', "\n", $multiValueSeparator);
			$this->multiValueSeparator = $multiValueSeparator;
		}
	}

	/**
	 * @see SMWIExportPrinter::getMimeType
	 * @param SMWQueryResult $queryResult
	 * @return string
	 */
	public function getMimeType( SMWQueryResult $queryResult ) {
		return 'text/csv';
	}

	/**
	 * @see SMWIExportPrinter::getFileName
	 * @param SMWQueryResult $queryResult
	 * @return string
	 */
	public function getFileName( SMWQueryResult $queryResult ) {
		return $this->fileName;
	}

	public function getQueryMode( $context ) {
		return $context == SMWQueryProcessor::SPECIAL_PAGE ? SMWQuery::MODE_INSTANCES : SMWQuery::MODE_NONE;
	}

	public function getName() {
		return 'Export (Excel-CSV)';
	}

	/**
	 * @see SMWResultPrinter::getParamDefinitions
	 * @param ParamDefinition[] $definitions
	 * @return array
	 */
	public function getParamDefinitions( array $definitions ) {
		$params = parent::getParamDefinitions( $definitions );

		$params['searchlabel']->setDefault('CSV-Datei');

		$params['limit']->setDefault( 100000 );

		$params[] = array(
			'name' => 'separator',
			'message' => 'odb-paramdesc-excelcsv-separator',
			'default' => $this->separator,
			'aliases' => 'sep'
		);

		$params[] = array(
			'name' => 'filename',
			'message' => 'odb-paramdesc-excelcsv-filename',
			'default' => $this->fileName
		);

		$params[] = array(
			'name' => 'multivalueseparator',
			'message' => 'odb-paramdesc-excelcsv-multivalueseparator',
			'default' => $this->multiValueSeparator,
			'aliases' => array ('multivaluesep', 'multisep', 'multiValueSeparator', 'multiValueSep')
		);

		return $params;
	}

	protected function getResultText( SMWQueryResult $res, $outputMode ) {
		if ( $outputMode == SMW_OUTPUT_FILE ) {
			// Make the CSV file.
			return $this->getResultFileContents( $res );
		}else {
			// Create a link pointing to the CSV file.
			return $this->getLinkToFile( $res, $outputMode );
		}
	}

	/**
	 * Returns html for a link to a query that returns the CSV file.
	 *
	 * @param SMWQueryResult $res
	 * @param $outputMode
	 *
	 * @return string
	 */
	private function getLinkToFile( SMWQueryResult $res, $outputMode ) {
		// yes, our code can be viewed as HTML if requested, no more parsing needed
		$this->isHTML = ( $outputMode == SMW_OUTPUT_HTML );
		return $this->getLink( $res, $outputMode )->getText( $outputMode, $this->mLinker );
	}

	/**
	 * Returns the query result in CSV.
	 *
	 * @param SMWQueryResult $res
	 *
	 * @return string
	 */
	private function getResultFileContents( SMWQueryResult $queryResult ) {
		$lines = $this->getHeader ( $queryResult );

		// Loop over the result objects (pages).
		$row = $queryResult->getNext();
		while ( $row !== false ) {
			$rowItems = array();

			// Loop over all fields (properties)
			// SMWResultArray $field
			foreach ( $row as $field ) {
				$itemSegments = array();

				$printRequestLabel = $field->getPrintRequest()->getLabel();
				
				// Loop over all values for the property
				$value = $field->getNextDataValue();
				while ( $value !== false ) {
					if($value instanceof SMWWikiPageValue && $printRequestLabel != 'ODB-ID') {
						$itemSegments[] = $this->getSemanticTitle($value);
					} else {
						$itemSegments[] = Sanitizer::decodeCharReferences( $value->getWikiValue() );
					}
					$value = $field->getNextDataValue();
				}

				$rowItems[] = implode($this->multiValueSeparator, $itemSegments);
			}

			$lines[] = $this->getLine( $rowItems );

			$row = $queryResult->getNext();
		}

		$result = implode( "\n", $lines );
		$result = mb_convert_encoding($result, 'latin1', 'auto');
		return $result;
	}

	/**
	 * @param queryResult
	 * @param headerItems
	 * @return array with one or no line with the column headers (labels)
	 */
	private function getHeader($queryResult) {
		$lines = array();

		if ( $this->mShowHeaders ) {
			$headerItems = array();

			foreach ( $queryResult->getPrintRequests() as $printRequest ) {
				$headerItems[] = $printRequest->getLabel();
			}

			$lines[] = $this->getLine( $headerItems );
		}

		return $lines;
	}

	/**
	 * Returns the semantic title for a wiki page, if it exists, otherwise its normal title
	 * @param SMWWikiPageValue $wikiPageValue
	 * @return string
	 */
	private function getSemanticTitle(SMWWikiPageValue $wikiPageValue) {
		$title = $wikiPageValue->getTitle();
		$semanticTitle = self::getDisplayTitle( $title );
		if($semanticTitle) {
			return Sanitizer::decodeCharReferences($semanticTitle);
		} else {
			return Sanitizer::decodeCharReferences($title->getText());
		}
	}

	/**
	 * adapted from DisplayTitleHook:
	 * 
	 * Get displaytitle page property text.
	 *
	 * @param Title $title the Title object for the page
	 * @return string display title, if set, otherwise prefixedText
	 */
	private static function getDisplayTitle( Title $title ) {
	    $pagetitle = $title->getPrefixedText();
	    // remove fragment
	    $title = Title::newFromText( $pagetitle );
	    
        $values = \PageProps::getInstance()->getProperties( $title, 'displaytitle' );
        $id = $title->getArticleID();
        if ( array_key_exists( $id, $values ) ) {
            $value = $values[$id];
            if ( trim( str_replace( '&#160;', '', strip_tags( $value ) ) ) !== '' ) {
                return $value;
            }
        }
        
        // no display title found
        return $title->getPrefixedText();
	}
	
	/**
	 * Returns a single line.
	 * @param array $fields
	 * @return string
	 */
	private function getLine( array $fields ) {
		return implode( $this->separator, array_map( array( $this, 'spreadsheetEncode' ), $fields ) );
	}

	/**
	 * Encodes a single value so that it can be interpreted by Excel properly
	 * 	* Zellen, die ein Semikolon, einen Zeilenvorschub oder Tabulator verwenden, werden mittels doppelter Anführungszeichen geklammert.
	 *  * Falls die Zelle ein doppeltes Anführungszeichen verwendet, wird es für den Export verdoppelt und damit escaped,
	 *
	 * @param string $value
	 * @return string
	 */
	private function spreadsheetEncode( $value ) {
		$value = str_replace('"', '""', $value); // replace one double-quote with two double-quotes

		if(str_contains($value, array($this->separator, '"', "\n", "\r", "\t", "\f"))) {
			// if an illegal character is present enclose the whole value within double-quotes
			// no further escaping required
			$value = '"' . $value . '"';
		}

		return $value;
	}

}
