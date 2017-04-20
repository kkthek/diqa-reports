/**
 * @author: Kai Kühn
 */
(function($) {
	
	if (DIQA && DIQA.Reports && DIQA.Reports.waitForWkHtmlToPdf) {
		DIQA.Reports.waitForWkHtmlToPdf();
	} else {
		window.status = 100;
	}
	
})(jQuery);