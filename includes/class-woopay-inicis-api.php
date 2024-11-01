<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooPayInicisApi' ) ) {
	class WooPayInicisApi extends WooPayInicis {
		public function __construct() {
			parent::__construct();

			$this->api_init();
		}

		private function api_init() {
			// WC-API Hook
			$api_events = array(
				'check_api',
				'response',
				'mobile_next',
				'mobile_notification',
				'mobile_return',
				'cas_response',
				'refund_request',
				'return',
				'close',
				'popup',
				'escrow_request',
				'delete_log'
			);

			foreach ( $api_events as $key => $api_event ) {
				add_action( 'woocommerce_api_' . $this->woopay_api_name . '_' . $api_event, array( $this, 'api_' . $api_event ) );
			}

			$this->payment = new WooPayInicisActions();
		}

		function api_check_api() {
			$this->payment->api_action( 'check_api' );
			exit;
		}

		function api_response() {
			$this->payment->api_action( 'response' );
			exit;
		}

		function api_mobile_next() {
			$this->payment->api_action( 'mobile_next' );
			exit;
		}

		function api_mobile_notification() {
			$this->payment->api_action( 'mobile_notification' );
			exit;
		}

		function api_mobile_return() {
			$this->payment->api_action( 'mobile_return' );
			exit;
		}

		function api_cas_response() {
			$this->payment->api_action( 'cas_response' );
			exit;
		}

		function api_refund_request() {
			$this->payment->api_action( 'refund_request' );
			exit;
		}

		function api_return() {
			$this->payment->api_action( 'return' );
			exit;
		}

		function api_close() {
			$this->payment->api_action( 'close' );
			exit;
		}

		function api_popup() {
			$this->payment->api_action( 'popup' );
			exit;
		}

		function api_escrow_request() {
			$this->payment->api_action( 'escrow_request' );
			exit;
		}

		function api_delete_log() {
			$this->payment->api_action( 'delete_log' );
			exit;
		}
	}

	return new WooPayInicisApi();
}