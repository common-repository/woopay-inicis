<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooPayInicisVirtual' ) ) {
	class WooPayInicisVirtual extends WooPayInicisPayment {
		public function __construct() {
			parent::__construct();

			$this->method_init();
		}

		function method_init() {
			$this->id						= 'inicis_virtual';
			$this->section					= 'woopayinicisvirtual';
			$this->method 					= 'onlyvbank';
			$this->method_title 			= __( 'Inicis Virtual Account', $this->woopay_domain );
			$this->title_default 			= __( 'Virtual Account', $this->woopay_domain );
			$this->desc_default  			= __( 'Payment via virtual account.', $this->woopay_domain );
			$this->allowed_currency			= array( 'KRW' );
			$this->default_checkout_img		= 'bank';
			$this->supports					= array( 'products' );
			$this->has_fields				= false;
			$this->allow_testmode			= true;
		}
	}

	function add_inicis_virtual( $methods ) {
		$methods[] = 'WooPayInicisVirtual';
		return $methods;
	}
	add_filter( 'woocommerce_payment_gateways', 'add_inicis_virtual' );
}