var testmode = woopay_string.testmode;
var checkoutURL = woopay_string.checkout_url;
var responseURL = woopay_string.response_url;
var inicis_method = woopay_string.inicis_method;
var payForm = document.getElementById( 'form_inicis' );

var features = '';
window.name = 'BTPG_CLIENT';

function startInicis() {
	document.charset = 'euc-kr';
	payForm.submit(); 
}

function returnToCheckout() {
	payForm.action = checkoutURL;
	payForm.submit();
}

function startWooPay() {
	if ( testmode ) {
		if ( ! confirm( woopay_string.testmode_msg ) ) {
			returnToCheckout();
		} else {
			startInicis();
		}
	} else {
		startInicis();
	}
}

jQuery( document ).ready(function() {
	setTimeout( 'startWooPay();', 500 );
});