<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooPayInicisRefund' ) ) {
	class WooPayInicisRefund extends WooPayInicis {
		public function __construct() {
			parent::__construct();

			$this->init_refund();
		}

		function init_refund() {
			// For Customer Refund
			add_filter( 'woocommerce_my_account_my_orders_actions',  array( $this, 'add_customer_refund' ), 10, 2 );
		}

		public function do_refund( $orderid, $amount = null, $reason = '', $rcvtid = null, $type = null, $acctname = null, $bankcode= null, $banknum = null ) {
			$order			= wc_get_order( $orderid );

			if ( $order == null ) {
				$message = __( 'Refund request received, but order does not exist.', $this->woopay_domain );
				$this->log( $message );

				return array(
					'result' 	=> 'failure',
					'message'	=> $message
				);
			}

			$this->id		= $this->get_payment_method( $orderid );
			$this->init_settings();

			$this->get_woopay_settings();

			$this->log( __( 'Starting refund process.', $this->woopay_domain ), $orderid );

			if ( $amount == null ) {
				$amount = $order->get_total();
			}

			$tid = get_post_meta( $orderid, '_' . $this->woopay_api_name . '_tid', true );

			if ( $tid == '' ) {
				$message = __( 'No TID found.', $this->woopay_domain );
				$this->log( $message, $orderid );

				return array(
					'result' 	=> 'failure',
					'message'	=> $message
				);
			}

			if ( $this->inicis_method == 'lite' ) {
				$mid			= ( $this->testmode ) ? ( ( $this->escw_yn ) ? 'inilitescr' : 'INIpayTest' ) : $this->mid;
				$admin_key		= ( $this->testmode ) ? ( ( $this->escw_yn ) ? 'enY4SjNNSFlJcHhtbzhzcVJ6Y0ZhUT09' : 'SU5JTElURV9UUklQTEVERVNfS0VZU1RS' ) : $this->admin;
				$msg			= '';

				require_once $this->woopay_plugin_basedir . '/bin/lib/INILiteLib.php';

				$inipay = new INILite;

				$inipay->m_inipayHome = $this->woopay_plugin_basedir . '/bin' ;
				$inipay->m_inikeyDir = $this->woopay_upload_dir;
				$inipay->m_key = $admin_key;
				$inipay->m_ssl = 'true';
				$inipay->m_type = 'cancel';
				$inipay->m_log = ( $this->testmode == 'yes' ) ? 'true' : 'false';
				$inipay->m_debug = ( $this->testmode == 'yes' ) ? 'true' : 'false';
				$inipay->m_mid = $mid;
				$inipay->m_tid = $tid;
				$inipay->m_cancelMsg = $msg;

				$inipay->startAction();

				$ResultCode		= str_replace( ' ', '', $inipay->m_resultCode);
				$ResultMsg		= iconv( 'EUC-KR', 'UTF-8', $inipay->m_resultCode );
			} else {
				$mid			= ( $this->testmode ) ? ( ( $this->escw_yn ) ? 'iniescrow0' : 'INIpayTest' ) : $this->mid;
				$admin			= ( $this->testmode ) ? '1111' : $this->admin;
				$msg			= '';

				require_once $this->woopay_plugin_basedir . '/bin/lib/INILib.php';

				$inipay = new INIpay50;

				$inipay->SetField( 'inipayhome', $this->woopay_plugin_basedir . '/bin' );
				$inipay->SetField( 'inikeydir', $this->woopay_upload_dir );
				$inipay->SetField( 'type', 'cancel' );
				$inipay->SetField( 'debug', ( $this->testmode == 'yes' ) ? 'true' : 'false' );
				$inipay->SetField( 'mid', $mid );
				$inipay->SetField( 'admin', $admin );
				$inipay->SetField( 'tid', $tid );
				$inipay->SetField( 'cancelmsg', $msg );

				$doRefund = true;
				$showVbank = false;

				if ( $this->id == 'inicis_virtual' ) {
					$showVbank = true;

					$refundacctname = iconv( 'UTF-8', 'EUC-KR', $acctname );
					$refundbankcode = $bankcode;
					$refundacctnum = $banknum;

					if ( $refundacctname == '' ) {
						$return = array(
							'result'	=> 'failure',
							'message'	=> __( 'Please enter the account holder name. Refund failed.', $this->woopay_domain )
						);
						$doRefund = false;
					} elseif ( $refundbankcode  == '' ) {
						$return = array(
							'result'	=> 'failure',
							'message'	=> __( 'Please select a bank. Refund failed.', $this->woopay_domain )
						);
						$doRefund = false;
					} elseif ( $refundacctnum  == '' ) {
						$return = array(
							'result'	=> 'failure',
							'message'	=> __( 'Please enter the account number for refund. Refund failed.', $this->woopay_domain )
						);
						$doRefund = false;
					}

					$inipay->SetField( 'type', 'refund' );
					$inipay->SetField( 'racctnum', $refundacctnum );
					$inipay->SetField( 'rbankcode', $refundbankcode );
					$inipay->SetField( 'racctname', $refundacctname );
				}

				if ( $doRefund ) {
					$showVbank = false;

					$inipay->startAction();

					$ResultCode		= str_replace( ' ', '', $inipay->getResult( 'ResultCode' ) );
					$ResultMsg		= iconv( 'EUC-KR', 'UTF-8', $inipay->getResult( 'ResultMsg' ) );
				}
			}

			if ( $type == 'customer' ) {
				$refunder = __( 'Customer', $this->woopay_domain );
			} else {
				$refunder = __( 'Administrator', $this->woopay_domain );
			}

			if ( $reason == '' ) {
				$reason = '--';
			}


			if ( $ResultCode == '00' ) {
				$message = sprintf( __( 'Refund process complete. Refunded by %s. Reason: %s.', $this->woopay_domain ), $refunder, $reason );

				$this->log( $message, $orderid );

				$message = sprintf( __( '%s Timestamp: %s.', $this->woopay_domain ), $message, $this->get_timestamp() );

				$order->update_status( 'refunded', $message );

				return array(
					'result' 	=> 'success',
					'message'	=> __( 'Your refund request has been processed.', $this->woopay_domain )
				);
			} else {
				$message = __( 'An error occurred while processing the refund.', $this->woopay_domain );

				$this->log( $message, $orderid );
				$this->log( __( 'Result Code: ', $this->woopay_domain ) . $ResultCode, $orderid );
				$this->log( __( 'Result Message: ', $this->woopay_domain ) . $ResultMsg, $orderid );

				$order->add_order_note( sprintf( __( '%s Code: %s. Message: %s. Timestamp: %s.', $this->woopay_domain ), $message, $ResultCode, $ResultMsg, $this->get_timestamp() ) );

				return array(
					'result' 	=> 'failure',
					'message'	=> $message
				);
			}
		}
	}

	return new WooPayInicisRefund();
}