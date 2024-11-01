var testmode = woopay_string.testmode; //document.getElementById( 'testmode' ).value;
var checkoutURL = woopay_string.checkout_url; //document.getElementById( 'checkout_url' ).value;
var responseURL = woopay_string.response_url; //document.getElementById( 'response_url' ).value;
var inicis_method = woopay_string.inicis_method; //document.getElementById( 'inicis_method' ).value;

var payForm = document.getElementById( 'ini' );
var installPlugin = false;

function pay( frm ) {
	if ( payForm.clickcontrol.value == 'enable' ) {
		if ( ( navigator.userAgent.indexOf('MSIE') >= 0 || navigator.appName == 'Microsoft Internet Explorer' ) && ( document.INIpay == null || document.INIpay.object == null ) ) {
			installPlugin = true;
			alert( woopay_string.refresh_msg );
			return false;
		} else {
			if ( MakePayMessage( frm ) ) {
				disable_click();
				return true;
			} else {
				if( IsPluginModule() ) {
					alert( woopay_string.cancel_msg );
					returnToCheckout();
				}
			}
		}
	} else {
		return false;
	}
}

function enable_click() {
	payForm.clickcontrol.value = 'enable';
}

function disable_click() {
	payForm.clickcontrol.value = 'disable';
}

function startInicis() {
	payForm.action = responseURL;
	pay( payForm );
	inicisSubmit();
}

function isFlashActive() {
	if ( thisMovie('INIFlash') == undefined ) {
		ini_IsUseFlash = false;
		inicisSubmit();
	}
}

function inicisSubmit() {
	if ( ini_IsUseFlash ) {
		setInterval( 'isFlashActive();', 2000 );
	} else {
		if ( ! installPlugin ) {
			payForm.action = responseURL;
			payForm.submit();
		}
	}
}

function returnToCheckout() {
	payForm.target = '_self';
	payForm.action = checkoutURL;
	payForm.submit();
}

function startWooPay() {
	if ( testmode ) {
		if ( ! confirm( woopay_string.testmode_msg ) ) {
			returnToCheckout();
		} else {
			if ( inicis_method == 'tx' ) {
				startInicis();
			} else {
				INIStdPay.pay( 'ini' );
			}
		}
	} else {
		if ( inicis_method == 'tx' ) {
			startInicis();
		} else {
			INIStdPay.pay( 'ini' );
		}
	}
}

if ( inicis_method == 'tx' ) {
	StartSmartUpdate();
}

jQuery( document ).ready(function() {
	setTimeout( 'startWooPay();', 500 );
});