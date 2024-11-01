<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooPayInicisActions' ) ) {
	class WooPayInicisActions extends WooPayInicis {
		function api_action( $type ) {
			@ob_clean();
			header( 'HTTP/1.1 200 OK' );
			switch ( $type ) {
				case 'check_api' :
					$this->do_check_api( $_REQUEST );
					exit;
					break;
				case 'response' :
					$this->do_response( $_REQUEST );
					exit;
					break;
				case 'mobile_next' :
					$this->do_mobile_next( $_REQUEST );
					exit;
					break;
				case 'mobile_notification' :
					$this->do_mobile_notification( $_REQUEST );
					exit;
					break;
				case 'mobile_return' :
					$this->do_mobile_return( $_REQUEST );
					exit;
					break;
				case 'cas_response' :
					$this->do_cas_response( $_REQUEST );
					exit;
					break;
				case 'refund_request' :
					$this->do_refund_request( $_REQUEST );
					exit;
					break;
				case 'return' :
					$this->do_return( $_REQUEST );
					exit;
					break;
				case 'close' :
					$this->do_close( $_REQUEST );
					exit;
					break;
				case 'popup' :
					$this->do_popup( $_REQUEST );
					exit;
					break;
				case 'escrow_request' :
					$this->do_escrow_request( $_REQUEST );
					exit;
					break;
				case 'delete_log' :
					$this->do_delete_log( $_REQUEST );
					exit;
					break;
				default :
					exit;
			}
		}

		private function do_check_api( $params ) {
			$result = array(
				'result'	=> 'success',
			);

			echo json_encode( $result );
		}

		private function do_return( $params ) {
			if ( empty( $params[ 'orderNumber' ] ) ) {
				wp_die( $this->woopay_plugin_nice_name . ' Failure' );
			}

			$orderid		= $params[ 'orderNumber' ];
			$order			= new WC_Order( $orderid );

			if ( $order == null ) {
				$message = __( 'Return received, but order does not exist.', $this->woopay_domain );
				$this->log( $message );
				wp_die( $this->woopay_plugin_nice_name . ' Failure' );
			}

			$this->id		= $this->get_payment_method( $orderid );
			$this->init_settings();

			$this->get_woopay_settings();

			$this->log( __( 'Starting return process.', $this->woopay_domain ), $orderid );

			require_once $this->woopay_plugin_basedir . '/bin/lib/INIStdPayUtil.php';
			require_once $this->woopay_plugin_basedir . '/bin/lib/HttpClient.php';

			$util			= new INIStdPayUtil();

			$ResultCode		= $params[ 'resultCode' ];
			$ResultMsg		= isset( $params[ 'resultMsg' ] ) ? $params[ 'resultMsg' ] : '';

			try {
				if ( strcmp( '0000', $ResultCode ) == 0 ) {
					$mid			= ( $this->testmode ) ? ( ( $this->escw_yn ) ? 'iniescrow0' : 'INIpayTest' ) : $this->mid;
					$signKey		= ( $this->testmode ) ? 'SU5JTElURV9UUklQTEVERVNfS0VZU1RS' : $this->sign_key;
					$timestamp		= get_post_meta( $orderid, '_ini_timestamp', true );
					$charset		= 'UTF-8';
					$format			= 'JSON';

					$authToken		= $params[ 'authToken' ];
					$authUrl		= $params[ 'authUrl' ];
					$netCancel		= $params[ 'netCancelUrl' ];
					$ackUrl			= $params[ 'checkAckUrl' ];

					$signParam					= array();
					$signParam[ 'authToken' ]	= $authToken;
					$signParam[ 'timestamp' ]	= $timestamp;
					$signature					= $util->makeSignature( $signParam );

					$authMap					= array();
					$authMap[ 'mid' ]			= $mid;
					$authMap[ 'authToken' ]		= $authToken;
					$authMap[ 'signature' ]		= $signature;
					$authMap[ 'timestamp' ]		= $timestamp;
					$authMap[ 'charset' ]		= $charset;
					$authMap[ 'format' ]		= $format;
					$authMap[ 'price' ]			= $order->order_total;

					try {
						$httpUtil = new HttpClient();

						$authResultString = '';
						if ( $httpUtil->processHTTP( $authUrl, $authMap ) ) {
							$authResultString = $httpUtil->body;
						} else {
							$this->log( __( 'HTTP Connect Error. Message: ', $this->woopay_domain ) . $httpUtil->errormsg, $orderid );
							wp_die( 'HTTP Connect Error' );
							throw new Exception( 'HTTP Connect Error' );
						}

						$resultMap = json_decode( $authResultString, true );

						$this->log( __( 'Received result string', $this->woopay_domain ), $orderid );
						$this->log( __( 'Result Code: ', $this->woopay_domain ) . $ResultCode, $orderid );
						$this->log( __( 'Result Message: ', $this->woopay_domain ) . $ResultMsg, $orderid );
						$this->log( serialize( $resultMap ), $orderid );

						/*if ( $resultMap[ 'TotPrice' ] != $order->get_total() ) {
							$paySuccess = false;

							$this->woopay_payment_integrity_failed( $orderid );
							wp_redirect( WC()->cart->get_cart_url() );
							exit;
						}*/

						if ( strcmp( '0000', $resultMap[ 'resultCode' ] ) == 0 ) {
							$PayMethod		= $resultMap[ 'payMethod' ];
							$tid			= $resultMap[ 'tid' ];
							$VACT_BankCode	= $resultMap[ 'VACT_BankCode' ];
							$VACT_Num		= $resultMap[ 'VACT_Num' ];
							$VACT_Date		= $resultMap[ 'VACT_Date' ];

							$checkMap[ 'mid' ]			= $mid;
							$checkMap[ 'authToken' ]	= isset( $resultMap[ 'authToken' ] ) ? $resultMap[ 'authToken' ] : '';
							$checkMap[ 'applDate' ]		= isset( $resultMap[ 'applDate' ] ) ? $resultMap[ 'applDate' ] : '';
							$checkMap[ 'applTime' ]		= isset( $resultMap[ 'applTime' ] ) ? $resultMap[ 'applTime' ] : '';
							$checkMap[ 'timestamp' ]	= isset( $resultMap[ 'timestamp' ] ) ? $resultMap[ 'timestamp' ] : '';
							$checkMap[ 'signature' ]	= isset( $resultMap[ 'signature' ] ) ? $resultMap[ 'signature' ] : '';
							$checkMap[ 'charset' ]		= $charset;
							$checkMap[ 'format' ]		= $format;

							$ackResultString = '';

							if ( $httpUtil->processHTTP( $ackUrl, $checkMap ) ) {
								$ackResultString = $httpUtil->body;
							} else {
								$this->log( __( 'HTTP Connect Error. Message: ', $this->woopay_domain ) . $httpUtil->errormsg, $orderid );
								wp_die( 'HTTP Connect Error' );
								throw new Exception( 'HTTP Connect Error' );
							}

							$ackMap = json_decode( $ackResultString );

							$this->log( __( 'Received ACK result string', $this->woopay_domain ), $orderid );

							if ( $PayMethod == 'VBank' ) {
								$this->woopay_payment_awaiting( $orderid, $tid, $PayMethod, $this->get_bankname( $VACT_BankCode ), $VACT_Num, $VACT_Date );
							} else {
								$this->woopay_payment_complete( $orderid, $tid, $PayMethod );
							}

							WC()->cart->empty_cart();
							wp_redirect( $this->get_return_url( $order ) );
							exit;
						} else {
							$this->woopay_payment_failed( $orderid, '', $ResultMsg );
							wp_redirect( WC()->cart->get_cart_url() );
							exit;
						}
					} catch ( Exception $e ) {
						$ResultCode		= $e->getCode();
						$ResultMsg		= $e->getMessage();

						$this->log( __( 'Exception occurred.', $this->woopay_domain ), $orderid );
						$this->log( __( 'Result Code: ', $this->woopay_domain ) . $ResultCode, $orderid );
						$this->log( __( 'Result Message: ', $this->woopay_domain ) . $ResultMsg, $orderid );

						$netcancelResultString = '';

						if ( $httpUtil->processHTTP( $netCancel, $authMap ) ) {
							$netcancelResultString = $httpUtil->body;
						} else {
							$this->log( __( 'HTTP Connect Error. Message: ', $this->woopay_domain ) . $httpUtil->errormsg, $orderid );
							wp_die( 'HTTP Connect Error' );
							throw new Exception( 'HTTP Connect Error' );
						}

						$netcancelResultString = str_replace( '<', '&lt;', $$netcancelResultString );
						$netcancelResultString = str_replace( '>', '&gt;', $$netcancelResultString );

						$this->log( __( 'Net cancel success. Result: ', $this->woopay_domain ) . $netcancelResultString, $orderid );
					}
				} else {
					$this->log( __( 'Result Code: ', $this->woopay_domain ) . $ResultCode, $orderid );
					$this->log( __( 'Result Message: ', $this->woopay_domain ) . $ResultMsg, $orderid );

					wc_add_notice( $ResultMsg );

					$this->woopay_payment_failed( $orderid, '', $ResultMsg );
					wp_redirect( WC()->cart->get_cart_url() );
					exit;
				}
			} catch ( Exception $e ) {
				$ResultCode		= $e->getCode();
				$ResultMsg		= $e->getMessage();

				$this->log( __( 'Result Code: ', $this->woopay_domain ) . $ResultCode, $orderid );
				$this->log( __( 'Result Message: ', $this->woopay_domain ) . $ResultMsg, $orderid );
			}

			exit;
		}

		private function do_close( $params ) {
			?>
			<html>
				<head>
					<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
					<script type="text/javascript">
					function closeInicis() {
						parent.returnToCheckout();
					}
					</script>
				</head>
				<body onload='closeInicis();'>
				</body>
			</html>
			<?php
		}

		private function do_popup( $params ) {
			?>
			<script language="javascript" type="text/javascript" src="https://stdpay.inicis.com/stdjs/INIStdPay_popup.js" charset="UTF-8"></script>
			<?php
		}

		private function do_response( $params ) {
			if ( empty( $params[ 'oid' ] ) ) {
				wp_die( $this->woopay_plugin_nice_name . ' Failure' );
			}

			$orderid		= $params[ 'oid' ];
			$order			= new WC_Order( $orderid );

			if ( $order == null ) {
				$message = __( 'Response received, but order does not exist.', $this->woopay_domain );
				$this->log( $message );
				wp_die( $this->woopay_plugin_nice_name . ' Failure' );
			}

			$this->id		= $this->get_payment_method( $orderid );
			$this->init_settings();

			$this->get_woopay_settings();

			$this->log( __( 'Starting response process.', $this->woopay_domain ), $orderid );

			if ( $this->inicis_method == 'lite' ) {
				$mid			= ( $this->testmode ) ? ( ( $this->escw_yn ) ? 'inilitescr' : 'INIpayTest' ) : $this->mid;
				$admin_key		= ( $this->testmode ) ? ( ( $this->escw_yn ) ? 'enY4SjNNSFlJcHhtbzhzcVJ6Y0ZhUT09' : 'SU5JTElURV9UUklQTEVERVNfS0VZU1RS' ) : $this->admin;

				require_once $this->woopay_plugin_basedir . '/bin/lib/INILiteLib.php';

				$inipay = new INILite;

				$uid			= $params[ 'uid' ];
				$goodname		= iconv( 'UTF-8', 'EUC-KR', $params[ 'goodname' ] );
				$currency		= $params[ 'currency' ];
				$mid			= $params[ 'mid' ];
				$price			= $params[ 'price' ];
				$enctype		= '';
				$buyername		= iconv( 'UTF-8', 'EUC-KR', $params[ 'buyername' ] );
				$buyertel		= iconv( 'UTF-8', 'EUC-KR', $params[ 'buyertel' ] );
				$buyeremail		= iconv( 'UTF-8', 'EUC-KR', $params[ 'buyeremail' ] );
				$paymethod		= $params[ 'paymethod' ];
				$encrypted		= $params[ 'encrypted' ];
				$sessionkey		= $params[ 'sessionkey' ];
				$url			= $params[ 'url' ];
				$cardcode		= $params[ 'cardcode' ];
				$parentemail	= $params[ 'parentemail' ];

				$inipay->m_inipayHome = $this->woopay_plugin_basedir . '/bin';
				$inipay->m_inikeyDir = $this->woopay_upload_dir;
				$inipay->m_key = $admin_key;
				$inipay->m_ssl = 'true';
				$inipay->m_type = 'securepay';
				$inipay->m_pgId = 'INlite'.$pgid;
				$inipay->m_log = ( $this->testmode == 'yes' ) ? 'true' : 'false';
				$inipay->m_debug = ( $this->testmode == 'yes' ) ? 'true' : 'false';
				$inipay->m_mid = $mid;
				$inipay->m_uid = $uid;
				$inipay->m_uip = getenv( 'REMOTE_ADDR' );
				$inipay->m_goodName = $goodname;
				$inipay->m_currency = $currency;
				$inipay->m_price = $price;
				$inipay->m_buyerName = $buyername;
				$inipay->m_buyerTel = $buyertel;
				$inipay->m_buyerEmail = $buyeremail;
				$inipay->m_payMethod = $paymethod;
				$inipay->m_encrypted = $encrypted;
				$inipay->m_sessionKey = $sessionkey;
				$inipay->m_url = home_url();
				$inipay->m_cardcode = $cardcode;
				$inipay->m_ParentEmail = $parentemail;

				$inipay->startAction();

				$tid			= $inipay->m_tid;
				$ResultCode		= $inipay->m_resultCode;
				$ResultMsg		= iconv( 'EUC-KR', 'UTF-8', $inipay->m_resultMsg );
				$PayMethod		= $inipay->m_payMethod;
				$MOID			= $inipay->m_oid;
				$TotPrice		= $inipay->m_resultprice;

				$VACT_Num		= $inipay->m_vacct;
				$VACT_BankCode	= $inipay->m_vcdbank;
				$VACT_Date		= $inipay->m_dtinput;
				$VACT_InputName	= $inipay->m_nminput;
				$VACT_Name		= $inipay->m_nmvacct;
			} else if ( $this->inicis_method == 'tx' ) {
				$mid			= ( $this->testmode ) ? ( ( $this->escw_yn ) ? 'iniescrow0' : 'INIpayTest' ) : $this->mid;
				$admin			= ( $this->testmode ) ? '1111' : $this->admin;

				require_once $this->woopay_plugin_basedir . '/bin/lib/INILib.php';

				$inipay = new INIpay50;

				$admin			= $params[ 'admin' ];
				$uid			= $params[ 'uid' ];
				$goodname		= iconv( 'UTF-8', 'EUC-KR', $params[ 'goodname' ] );
				$currency		= $params[ 'currency' ];
				$mid			= $params[ 'mid' ];
				$rn				= get_post_meta( $order->id, '_ini_rn', true );;
				$price			= $params[ 'price' ];
				$enctype		= get_post_meta( $order->id, '_ini_enctype', true );
				$buyername		= iconv( 'UTF-8', 'EUC-KR', $params[ 'buyername' ] );
				$buyertel		= iconv( 'UTF-8', 'EUC-KR', $params[ 'buyertel' ] );
				$buyeremail		= iconv( 'UTF-8', 'EUC-KR', $params[ 'buyeremail' ] );
				$paymethod		= $params[ 'paymethod' ];
				$encrypted		= $params[ 'encrypted' ];
				$sessionkey		= $params[ 'sessionkey' ];
				$url			= $params[ 'url' ];
				$cardcode		= $params[ 'cardcode' ];
				$parentemail	= $params[ 'parentemail' ];
				$quotabase		= $params[ 'quotabase' ];
				$nointerest		= $params[ 'nointerest' ];

				$inipay->SetField( 'inipayhome', $this->woopay_plugin_basedir . '/bin' );
				$inipay->SetField( 'inikeydir', $this->woopay_upload_dir );
				$inipay->SetField( 'type', 'securepay' );
				$inipay->SetField( 'pgid', 'INIphp'.$pgid );
				$inipay->SetField( 'subpgip', '203.238.3.10' );
				$inipay->SetField( 'admin', $admin );
				$inipay->SetField( 'debug', ( $params['testmode'] == 'yes' ) ? 'true' : 'false' );
				$inipay->SetField( 'uid', $uid );
				$inipay->SetField( 'goodname', $goodname );
				$inipay->SetField( 'currency', $currency );

				$inipay->SetField( 'mid', $mid );
				$inipay->SetField( 'rn', $rn );
				$inipay->SetField( 'price', $price );
				$inipay->SetField( 'enctype', $enctype );

				$inipay->SetField( 'buyername', $buyername );
				$inipay->SetField( 'buyertel',  $buyertel );
				$inipay->SetField( 'buyeremail', $buyeremail );
				$inipay->SetField( 'paymethod', $paymethod );
				$inipay->SetField( 'encrypted', $encrypted );
				$inipay->SetField( 'sessionkey', $sessionkey );
				$inipay->SetField( 'url', home_url() );
				$inipay->SetField( 'cardcode', $cardcode );
				$inipay->SetField( 'parentemail', $parentemail );

				$recvname		= get_post_meta( $order->id, '_shipping_first_name', true );
				$recvtel		= get_post_meta( $order->id, '_shipping_phone', true );
				$recvaddr		= get_post_meta( $order->id, '_shipping_address_1', true ) . get_post_meta( $order->id, '_shipping_address_2', true );
				$recvpostnum	= get_post_meta( $order->id, '_shipping_postcode', true );
				$recvmsg		= $order->customer_note;

				$recvname		= iconv( 'UTF-8', 'EUC-KR', $recvname );
				$recvtel		= iconv( 'UTF-8', 'EUC-KR', $recvtel );
				$recvaddr		= iconv( 'UTF-8', 'EUC-KR', $recvaddr );
				$recvpostnum	= iconv( 'UTF-8', 'EUC-KR', $recvpostnum );
				$recvmsg		= iconv( 'UTF-8', 'EUC-KR', $recvmsg );

				$inipay->SetField( 'recvname', $recvname );
				$inipay->SetField( 'recvtel', $recvtel );
				$inipay->SetField( 'recvaddr', $recvaddr );
				$inipay->SetField( 'recvpostnum', $recvpostnum );
				$inipay->SetField( 'recvmsg', $recvmsg );

				$inipay->SetField( 'joincard', $joincard );
				$inipay->SetField( 'joinexpire', $joinexpire );
				$inipay->SetField( 'id_customer', $id_customer );

				$inipay->startAction();

				$tid			= $inipay->GetResult( 'TID' );
				$ResultCode		= $inipay->GetResult( 'ResultCode' );
				$ResultMsg		= iconv( 'EUC-KR', 'UTF-8', $inipay->GetResult( 'ResultMsg' ) );
				$PayMethod		= $inipay->GetResult( 'PayMethod' );
				$MOID			= $inipay->GetResult( 'MOID' );
				$TotPrice		= $inipay->GetResult( 'TotPrice' );

				$VACT_Num		= $inipay->GetResult( 'VACT_Num' );
				$VACT_BankCode	= $inipay->GetResult( 'VACT_BankCode' );
				$VACT_Date		= $inipay->GetResult( 'VACT_Date' );
				$VACT_InputName	= $inipay->GetResult( 'VACT_InputName' );
				$VACT_Name		= $inipay->GetResult( 'VACT_Name' );
			}

			$this->log( __( 'Result Code: ', $this->woopay_domain ) . $ResultCode, $orderid );
			$this->log( __( 'Result Message: ', $this->woopay_domain ) . $ResultMsg, $orderid );

			$paySuccess = false;

			if ( $ResultCode == '00' || $ResultCode == '0000' ) $paySuccess = true;

			if ( $price != $order->get_total() ) {
				$paySuccess = false;

				$this->woopay_payment_integrity_failed( $orderid );
				wp_redirect( WC()->cart->get_cart_url() );
				exit;
			}

			if ( $paySuccess == true ) {
				if ( $PayMethod == 'VBank' ) {
					$this->woopay_payment_awaiting( $orderid, $tid, $PayMethod, $this->get_bankname( $VACT_BankCode ), $VACT_Num, $VACT_Date );
				} else {
					$this->woopay_payment_complete( $orderid, $tid, $PayMethod );
				}

				WC()->cart->empty_cart();
				wp_redirect( $this->get_return_url( $order ) );
				exit;
			} else {
				if ( $ResultCode == '01' ) {
					wp_redirect( WC()->cart->get_cart_url() );
					exit;
				} else {
					$this->woopay_payment_failed( $orderid, $ResultCode, $ResultMsg );
					wp_redirect( WC()->cart->get_cart_url() );
					exit;
				}
			}
		}

		private function do_mobile_next( $params ) {
			if ( empty( $params[ 'P_NOTI' ] ) ) {
				wp_die( $this->woopay_plugin_nice_name . ' Failure' );
			}

			$orderid		= $params[ 'P_NOTI' ];
			$order			= new WC_Order( $orderid );

			if ( $order == null ) {
				$message = __( 'Mobile next received, but order does not exist.', $this->woopay_domain );
				$this->log( $message );
				wp_die( $this->woopay_plugin_nice_name . ' Failure' );
			}

			$this->id		= $this->get_payment_method( $orderid );
			$this->init_settings();

			$this->get_woopay_settings();

			$this->log( __( 'Starting mobile next process.', $this->woopay_domain ), $orderid );

			$P_REQ_URL		= $params[ 'P_REQ_URL' ];
			$P_TID			= $params[ 'P_TID' ];
			$P_STATUS		= str_replace( ' ', '', $params[ 'P_STATUS' ] );
			$P_RMESG1		= iconv( 'EUC-KR', 'UTF-8', $params[ 'P_RMESG1' ] );

			$mid			= ( $this->testmode ) ? ( ( $this->escw_yn ) ? 'iniescrow0' : 'INIpayTest' ) : $this->mid;

			if ( $P_STATUS != '02' ) {
				$response = wp_remote_post( $P_REQ_URL, 
					array(
						'method' => 'POST',
						'timeout' => 45,
						'redirection' => 5,
						'httpversion' => '1.0',
						'blocking' => true,
						'headers' => array(),
						'body' => array(
							'P_TID' => $P_TID, 'P_MID' => $mid
						),
						'cookies' => array(),
					) );

				if ( is_wp_error( $response ) ) {
					$msg = $response->get_error_message();
					$message = sprintf( __( 'Error occurred. Message: %s.', $this->woopay_domain ), $msg );

					$this->woopay_add_order_note( $orderid, $message );
					wp_redirect( WC()->cart->get_checkout_url() );
					exit;
				} else {
					$pay_result_string		= $response[ 'body' ];
					$pay_result_array		= $this->parse_http_query( $pay_result_string );

					$P_STATUS				= $pay_result_array[ 'P_STATUS' ];
					$P_TID					= $pay_result_array[ 'P_TID' ];
					$P_OID					= $pay_result_array[ 'P_OID' ];
					$P_TYPE					= $pay_result_array[ 'P_TYPE' ];
					$P_VACT_NUM				= isset( $pay_result_array[ 'P_VACT_NUM' ] ) ? $pay_result_array[ 'P_VACT_NUM' ] : '';
					$P_VACT_DATE			= isset( $pay_result_array[ 'P_VACT_DATE' ] ) ? $pay_result_array[ 'P_VACT_DATE' ] : '';
					$P_VACT_TIME			= isset( $pay_result_array[ 'P_VACT_TIME' ] ) ? $pay_result_array[ 'P_VACT_TIME' ] : '';
					$P_VACT_BANK_CODE		= isset( $pay_result_array[ 'P_VACT_BANK_CODE' ] ) ? $pay_result_array[ 'P_VACT_BANK_CODE' ] : '';
					$P_VACT_NAME			= isset( $pay_result_array[ 'P_VACT_NAME' ] ) ? $pay_result_array[ 'P_VACT_NAME' ] : '';
				}

				if ( $P_STATUS == '01' && $P_TID == '' ) {
					$this->woopay_user_cancelled( $orderid );
					wp_redirect( WC()->cart->get_cart_url() );
					exit;
				}

				if ( $P_STATUS == '00' ) {
					if ( $P_TYPE == 'VBANK' ) {
						$this->woopay_payment_awaiting( $P_OID, $P_TID, $P_TYPE, $this->get_bankname( $P_VACT_BANK_CODE ), $P_VACT_NUM, $P_VACT_DATE . $P_VACT_TIME );
					} else {
						$this->woopay_payment_complete( $P_OID, $P_TID, $P_TYPE );
					}

					WC()->cart->empty_cart();
					wp_redirect( $this->get_return_url( $order ) );
					exit;
				} else {
					$this->woopay_payment_failed( $orderid, '', $P_RMESG1 );
					wp_redirect( WC()->cart->get_cart_url() );
					exit;
				}
			} else {
				exit;
			}
		}

		private function do_mobile_notification( $params ) {
			if ( empty( $params[ 'P_OID' ] ) ) {
				echo 'FAIL';
				exit;
			}

			$orderid		= str_replace( ' ', '', $params[ 'P_OID' ] );
			$order			= new WC_Order( $orderid );

			if ( $order == null ) {
				$message = __( 'Mobile notification received, but order does not exist.', $this->woopay_domain );
				$this->log( $message );
				echo 'FAIL';
				exit;
			}

			$this->id		= $this->get_payment_method( $orderid );
			$this->init_settings();

			$this->get_woopay_settings();

			$this->log( __( 'Starting mobile notification process.', $this->woopay_domain ), $orderid );

			$P_TID			= $params[ 'P_TID' ];
			$P_MID			= $params[ 'P_MID' ];
			$P_AUTH_DT		= $params[ 'P_AUTH_DT' ];
			$P_STATUS		= str_replace( ' ', '', $params[ 'P_STATUS' ] );
			$P_TYPE			= str_replace( ' ', '', $params[ 'P_TYPE' ] );
			$P_OID			= str_replace( ' ', '', $params[ 'P_OID' ] );
			$P_FN_CD1		= $params[ 'P_FN_CD1' ];
			$P_FN_CD2		= $params[ 'P_FN_CD2' ];
			$P_FN_NM		= $params[ 'P_FN_NM' ];
			$P_AMT			= $params[ 'P_AMT' ];
			$P_UNAME		= $params[ 'P_UNAME' ];
			$P_RMESG1		= $params[ 'P_RMESG1' ];
			$P_RMESG2		= iconv( 'EUC-KR', 'UTF-8', $params[ 'P_RMESG2' ] );
			$P_NOTI			= $params[ 'P_NOTI' ];
			$P_AUTH_NO		= $params[ 'P_AUTH_NO' ];

			$this->log( __( 'Result Code: ', $this->woopay_domain ) . '', $orderid );
			$this->log( __( 'Result Message: ', $this->woopay_domain ) . $P_RMESG2, $orderid );

			$paySuccess = false;

			if ( $P_STATUS == '00' || $P_STATUS == '02' ) $paySuccess = true;

			if ( $P_STATUS == '01' && $P_TID == '' ) {
				$this->woopay_user_cancelled( $orderid );
				wp_redirect( WC()->cart->get_cart_url() );
				exit;
			}

			if ( $paySuccess == true ) {
				if ( $P_TYPE != 'VBANK' && $P_STATUS == '00' ) {
					$this->woopay_payment_complete( $P_OID, $P_TID, $P_TYPE );

					echo 'OK';
					exit;
				} elseif ( $P_TYPE == 'VBANK' && $P_STATUS == '02' ) {
					$this->woopay_cas_payment_complete( $orderid, $tid, 'VBANK' );

					echo 'OK';
					exit;
				} else {
					if ( $P_TYPE == 'VBANK' && $P_STATUS == '00' ) {
						echo 'OK';
						exit;
					} else {
						echo 'OK';
						exit;
					}
				}
			} else {
				$this->woopay_payment_failed( $orderid, '', $P_RMESG2 );
				echo 'OK';
				exit;
			}

			echo 'FAIL';
			exit;
		}

		private function do_mobile_return( $params ) {
			if ( empty( $params[ 'P_OID' ] ) ) {
				echo 'FAIL';
				exit;
			}

			$orderid		= str_replace( ' ', '', $params[ 'P_OID' ] );
			$order			= new WC_Order( $orderid );

			if ( $order == null ) {
				$message = __( 'Mobile return received, but order does not exist.', $this->woopay_domain );
				$this->log( $message );
				echo 'FAIL';
				exit;
			}

			$this->id		= $this->get_payment_method( $orderid );
			$this->init_settings();

			$this->get_woopay_settings();

			$this->log( __( 'Starting mobile return process.', $this->woopay_domain ), $orderid );

			if ( $order->status == 'pending' || $order->status == 'processing' ) {
				WC()->cart->empty_cart();
				wp_redirect( $this->get_return_url( $order ) );
			} else {
				wp_redirect( WC()->cart->get_cart_url() );
			}
		}

		private function do_cas_response( $params ) {
			if ( empty( $params[ 'no_tid' ] ) ) {
				echo 'FAIL';
				exit;
			}

			if ( empty( $params[ 'no_oid' ] ) ) {
				echo 'FAIL';
				exit;
			}

			$orderid		= $params[ 'no_oid' ];
			$order			= new WC_Order( $orderid );

			if ( $order == null ) {
				$message = __( 'CAS response received, but order does not exist.', $this->woopay_domain );
				$this->log( $message );
				echo 'FAIL';
				exit;
			}

			$this->id		= $this->get_payment_method( $orderid );
			$this->init_settings();

			$this->get_woopay_settings();

			$this->log( __( 'Starting CAS response process.', $this->woopay_domain ), $orderid );

			$tid				= $params[ 'no_tid' ];

			$this->woopay_cas_payment_complete( $orderid, $tid, 'VBANK' );

			echo 'OK';
			exit;
		}

		private function do_refund_request( $params ) {
			if ( ! isset( $params[ 'orderid' ] ) || ! isset( $params[ 'tid' ] ) || ! isset( $params[ 'type' ] ) ) {
				wp_die( $this->woopay_plugin_nice_name . ' Failure' );
			}

			$orderid		= $params[ 'orderid' ];
			$tid			= $params[ 'tid' ];

			$woopay_refund = new WooPayInicisRefund();
			$return = $woopay_refund->do_refund( $orderid, null, __( 'Refund request by customer', $this->woopay_domain ), $tid, 'customer' );

			if ( $return[ 'result' ] == 'success' ) {
				wc_add_notice( $return[ 'message' ], 'notice' );
				wp_redirect( $params[ 'redirect' ] );
				exit;
			} else {
				wc_add_notice( $return[ 'message' ], 'error' );
				wp_redirect( $params[ 'redirect' ] );
				exit;
			}
			exit;
		}

		private function do_escrow_request( $params ) {
			exit;
		}

		private function do_delete_log( $params ) {
			if ( ! isset( $params[ 'file' ] ) ) {
				$return = array(
					'result' => 'failure',
				);
			} else {
				$file = trailingslashit( WC_LOG_DIR ) . $params[ 'file' ];

				if ( file_exists( $file ) ) {
					unlink( $file );
				}

				$return = array(
					'result' => 'success',
					'message' => __( 'Log file has been deleted.', $this->woopay_domain )
				);
			}

			echo json_encode( $return );

			exit;
		}
	}
}