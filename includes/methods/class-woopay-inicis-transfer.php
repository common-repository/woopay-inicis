<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooPayInicisTransfer' ) ) {
	class WooPayInicisTransfer extends WooPayInicisPayment {
		public function __construct() {
			parent::__construct();

			$this->method_init();
		}

		function method_init() {
			$this->id						= 'inicis_transfer';
			$this->section					= 'woopayinicistransfer';
			$this->method 					= 'onlydbank';
			$this->method_title 			= __( 'Inicis Account Transfer', $this->woopay_domain );
			$this->title_default 			= __( 'Account Transfer', $this->woopay_domain );
			$this->desc_default  			= __( 'Payment via account transfer.', $this->woopay_domain );
			$this->allowed_currency			= array( 'KRW' );
			$this->default_checkout_img		= 'bank';
			$this->supports					= array( 'products', 'refunds' );
			$this->has_fields				= false;
			$this->allow_testmode			= true;
		}
	}

	function add_inicis_transfer( $methods ) {
		$methods[] = 'WooPayInicisTransfer';
		return $methods;
	}
	add_filter( 'woocommerce_payment_gateways', 'add_inicis_transfer' );
}