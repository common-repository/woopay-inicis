<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooPayInicisCard' ) ) {
	class WooPayInicisCard extends WooPayInicisPayment {
		public function __construct() {
			parent::__construct();

			$this->method_init();
		}

		function method_init() {
			$this->id						= 'inicis_card';
			$this->section					= 'woopayiniciscard';
			$this->method 					= 'onlycard';
			$this->method_title 			= __( 'Inicis Credit Card', $this->woopay_domain );
			$this->title_default 			= __( 'Credit Card', $this->woopay_domain );
			$this->desc_default  			= __( 'Payment via credit card.', $this->woopay_domain );
			$this->allowed_currency			= array( 'KRW' );
			$this->default_checkout_img		= 'card';
			$this->supports					= array( 'products', 'refunds' );
			$this->has_fields				= false;
			$this->allow_testmode			= true;
		}
	}

	function add_inicis_card( $methods ) {
		$methods[] = 'WooPayInicisCard';
		return $methods;
	}
	add_filter( 'woocommerce_payment_gateways', 'add_inicis_card' );
}