/* jshint unused:vars, undef:true, jquery:true, browser:true */

(function() {
'use strict';

$(window).on('beforeunload', function() {
	$('form').off('submit').removeAttr('onsubmit').on('submit', function(e) {
		e.preventDefault();
		return false;
	});
});

})();