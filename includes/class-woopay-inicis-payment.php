<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooPayInicisPayment' ) ) {
	class WooPayInicisPayment extends WooPayInicis {
		public $title_default;
		public $desc_default;
		public $default_checkout_img;
		public $allowed_currency;
		public $allow_other_currency;
		public $allow_testmode;

		function __construct() {
			parent::__construct();

			$this->method_init();
			$this->init_settings();
			$this->init_form_fields();

			$this->get_woopay_settings();

			// Actions
			add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'pg_scripts' ) );
			add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'show_virtual_information' ) );
			add_action( 'woocommerce_view_order', array( $this, 'get_virtual_information' ), 9 );

			if ( ! $this->is_valid_for_use( $this->allowed_currency ) ) {
				if ( ! $this->allow_other_currency ) {
					$this->enabled = 'no';
				}
			}

			if ( ! $this->testmode ) {
				if ( $this->inicis_method == 'tx' ) {
					if ( $this->mid == '' || $this->admin == '' ) {
						$this->enabled = 'no';
					}
				} else if ( $this->inicis_method == 'web' ) {
					if ( $this->mid == '' || $this->sign_key == '' ) {
						$this->enabled = 'no';
					}
				} else {
					if ( $this->mid_lite == '' || $this->admin_lite == '' ) {
						$this->enabled = 'no';
					}
				}
			} else {
				$this->title		= __( '[Test Mode]', $this->woopay_domain ) . " " . $this->title;
				$this->description	= __( '[Test Mode]', $this->woopay_domain ) . " " . $this->description;
			}
		}

		public function method_init() {
		}

		public function get_mid() {
			$dir = $this->woopay_upload_dir . '/key/';

			if ( ! file_exists( $dir ) ) {
				return array(
					'' => __( 'Please upload your keyfile first', $this->woopay_domain )
				);
			}

			$keys = scandir( $dir );

			$key_array = array(
				'' => __( 'Select your Merchant ID', $this->woopay_domain )
			);

			foreach ( $keys as $key => $value ) {
				if ( $value == '.' || $value == '..' || $value == 'INIpayTest' || $value == 'iniescrow0' ) continue;
				if ( is_dir( $dir . $value ) ) {
					$key_array[ $value ] = $value;
				}
			}

			if ( sizeof( $key_array ) == 1 ) {
				return array(
					'' => __( 'Please upload your keyfile first', $this->woopay_domain )
				);
			} else {
				return $key_array;
			}
		}

		public function pg_scripts() {
			if ( is_checkout() ) {
				if ( ! $this->check_mobile() ) {
					if ( $this->inicis_method == 'web' ) {
						if ( $this->testmode ) {
							$script_url = 'https://stgstdpay.inicis.com/stdjs/INIStdPay.js';
						} else {
							$script_url = 'https://stdpay.inicis.com/stdjs/INIStdPay.js';
						}
					} else if ( $this->inicis_method == 'tx' ) {
						if ( $this->site_ssl() ) {
							$script_url = 'https://plugin.inicis.com/pay61_secunissl_cross.js';
						} else {
							$script_url = 'http://plugin.inicis.com/pay61_secuni_cross.js';
						}

					} else if ( $this->inicis_method == 'lite' ) {
						if ( $this->site_ssl() ) {
							$script_url = 'https://plugin.inicis.com/pay61_secunissl_cross.js';
						} else {
							$script_url = 'http://plugin.inicis.com/pay61_secuni_cross.js';
						}
					}

					wp_register_script( 'inicis_script', $script_url, array( 'jquery' ), null, false );
					wp_enqueue_script( 'inicis_script' );
				}
			}
		}

		public function receipt( $orderid ) {
			$order = new WC_Order( $orderid );

			if ( $this->checkout_img ) {
				echo '<div class="p8-checkout-img"><img src="' . $this->checkout_img . '"></div>';
			}

			echo '<div class="p8-checkout-txt">' . str_replace( "\n", '<br>', $this->checkout_txt ) . '</div>';

			if ( $this->show_chrome_msg == 'yes' ) {
				if ( $this->get_chrome_version() >= 42 && $this->get_chrome_version() < 45 ) {
					echo '<div class="p8-chrome-msg">';
					echo __( 'If you continue seeing the message to install the plugin, please enable NPAPI settings by following these steps:', $this->woopay_domain );
					echo '<br>';
					echo __( '1. Enter <u>chrome://flags/#enable-npapi</u> on the address bar.', $this->woopay_domain );
					echo '<br>';
					echo __( '2. Enable NPAPI.', $this->woopay_domain );
					echo '<br>';
					echo __( '3. Restart Chrome and refresh this page.', $this->woopay_domain );
					echo '</div>';
				}
			}

			$currency_check = $this->currency_check( $order, $this->allowed_currency );

			if ( $currency_check ) {
				echo $this->woopay_form( $orderid );
			} else {
				$currency_str = $this->get_currency_str( $this->allowed_currency );

				echo sprintf( __( 'Your currency (%s) is not supported by this payment method. This payment method only supports: %s.', $this->woopay_domain ), get_post_meta( $order->id, '_order_currency', true ), $currency_str );
			}
		}

		public function get_woopay_args( $order ) {
			$orderid = $order->id;

			$this->billing_phone = $order->billing_phone;

			if ( sizeof( $order->get_items() ) > 0 ) {
				foreach ( $order->get_items() as $item ) {
					if ( $item[ 'qty' ] ) {
						$item_name = $item[ 'name' ];
					}
				}
			}

			$timestamp = $this->get_timestamp();

			$price			= $order->order_total;

			if ( ! $this->check_mobile() ) {
				if ( $this->inicis_method == 'web' ) {
					require_once $this->woopay_plugin_basedir . '/bin/lib/INIStdPayUtil.php';

					$SignatureUtil = new INIStdPayUtil();

					$mid			= ( $this->testmode ) ? ( ( $this->escw_yn ) ? 'iniescrow0' : 'INIpayTest' ) : $this->mid;
					$admin			= ( $this->testmode ) ? '1111' : $this->admin;
					$signKey		= ( $this->testmode ) ? 'SU5JTElURV9UUklQTEVERVNfS0VZU1RS' : $this->sign_key;
					$timestamp		= get_post_meta( $orderid, '_ini_timestamp', true );

					$orderNumber	= $order->id;
					$price			= $price;

					$mKey			= $SignatureUtil->makeHash( $signKey, 'sha256' );

					$params = array(
						'oid' => $orderNumber,
						'price' => $price,
						'timestamp' => $timestamp,
					);

					$sign = $SignatureUtil->makeSignature( $params, 'sha256' );

					$currency = $this->get_currency();
					update_post_meta( $order->id, '_order_currency', $currency );

					if ( $currency == 'KRW' ) $currency = 'WON';

					$paymethod = '';

					switch( $this->method ) {
						case 'onlycard' :
							$paymethod = 'Card';
							break;
						case 'onlydbank' :
							$paymethod = 'DirectBank';
							break;
						case 'onlyvbank' :
							$paymethod = 'Vbank';
							break;
						case 'onlyhpp' :
							$paymethod = 'HPP';
							break;
					}

					$inicis_args = array(
						'version'				=> '1.0',
						'mid'					=> $mid,
						'goodsname'				=> sanitize_text_field( $item_name ),
						'oid'					=> $order->id,
						'price'					=> $price,
						'currency'				=> $currency,
						'buyername'				=> $this->get_name_lang( $order->billing_first_name, $order->billing_last_name ),
						'buyertel'				=> $order->billing_phone,
						'buyeremail'			=> $order->billing_email,
						'timestamp'				=> $timestamp,
						'signature'				=> $sign,
						'returnUrl'				=> $this->get_api_url( 'return' ),
						'mKey'					=> $mKey,
						'gopaymethod'			=> $paymethod,
						'acceptmethod'			=> $this->get_acceptmethod( 'web', $this->method, $this->escw_yn, $this->expiry_time, $this->skincolor, $this->noreceipt, $this->vareceipt ),
						'languageView'			=> 'ko',
						'charset'				=> 'UTF-8',
						'payViewType'			=> ( $this->check_mobile() ) ? 'new' : 'overlay',
						'closeUrl'				=> $this->get_api_url( 'close' ),
						'popupUrl'				=> $this->get_api_url( 'popup' ),
						'nointerest'			=> '',
						'quotabase'				=> $this->get_quotabase( 'web', $this->quotabase ),
						'merchantData'			=> '',
						'escw_yn'				=> $this->escw_yn,
						'ini_logoimage_url'		=> $this->logoimg,
					);
				} else {
					$inicis_args = array(
						'gopaymethod'			=> $this->method,
						'goodname'				=> sanitize_text_field( $item_name ),
						'price'					=> $price,
						'buyername'				=> $this->get_name_lang( $order->billing_first_name, $order->billing_last_name ),
						'buyertel'				=> $order->billing_phone,
						'oid'					=> $order->id,
						'currency'				=> 'WON',
						'buyeremail'			=> $order->billing_email,
						'parentemail'			=> '',
						'acceptmethod'			=> $this->get_acceptmethod( 'tx', $this->method, $this->escw_yn, $this->expiry_time, $this->skintype, $this->noreceipt, $this->vareceipt ),
						'ini_encfield'			=> '',
						'ini_certid'			=> '',
						'quotainterest'			=> '',
						'paymethod'				=> '',
						'cardcode'				=> '',
						'cardquota'				=> '',
						'rbankcode'				=> '',
						'reqsign'				=> 'DONE',
						'encrypted'				=> '',
						'sessionkey'			=> '',
						'uid'					=> '',
						'sid'					=> '',
						'version'				=> '5000',
						'clickcontrol'			=> 'enable',
						'nointerest'			=> ( $this->nointerest ) ? 'yes' : 'no',
						'quotabase'				=> $this->get_quotabase( 'tx', $this->quotabase ),
						'testmode'				=> $this->testmode,
						'escw_yn'				=> $this->escw_yn,
						'mid'					=> $this->mid,
						'admin'					=> $this->admin,
						'ini_logoimage_url'		=> $this->logoimg,
						'ini_menuarea_url'		=> $this->methodimg,
					);
				}
			} else {
				$inicis_args = array(
					'gopaymethod'			=> $this->method,
					'P_MID'					=> $this->mid,
					'P_OID'					=> $order->id,
					'P_AMT'					=> $price,
					'P_UNAME'				=> $this->get_name_lang( $order->billing_first_name, $order->billing_last_name ),
					'P_NOTI'				=> $order->id,
					'P_NEXT_URL'			=> $this->get_api_url( 'mobile_next' ),
					'P_NOTI_URL'			=> $this->get_api_url_http( 'mobile_notification' ),
					'P_RETURN_URL'			=> $this->get_api_url( 'mobile_return', 'pretty', array( 'P_OID' => $order->id ) ),
					'P_GOODS'				=> sanitize_text_field( $item_name ),
					'P_MOBILE'				=> $order->billing_phone,
					'P_EMAIL'				=> $order->billing_email,
					'P_HPP_METHOD'			=> '2',
					'P_VBANK_DT'			=> $this->get_expirytime( $this->expiry_time, 'Ymd' ),
					'P_CARD_OPTION'			=> '',
					'P_APP_BASE'			=> ( $this->method == 'onlydbank' ) ? 'ON' : '',
					'P_MLOGO_IMAGE'			=> '',
					'P_GOOD_IMAGE'			=> '',
					'P_RESERVED'			=> '',
					'P_TAX'					=> '',
					'P_TAXFREE'				=> '',
					'P_ONLY_CARDCODE'		=> '',
				);
			}

			$inicis_args = apply_filters( 'woocommerce_inicis_args', $inicis_args );

			return $inicis_args;
		}

		public function get_acceptmethod( $type, $paymethod, $escw_yn, $expiry_time, $skintype, $noreceipt, $vareceipt ) {
			$acceptmethod = '';

			if ( $type == 'web' ) {
				if ( $skintype == '' ) {
					$skintype = 'ORIGINAL';
				}

				$acceptmethod .= 'SKIN(' . $skintype . ')';

				if ( $paymethod == 'onlydbank' ) {
					if ( ! $noreceipt ) {
						$acceptmethod .= ':no_receipt';
					}
				}

				if ( $paymethod == 'onlyvbank' ) {
					if ( $expiry_time ) {
						if ( $expiry_time != 'Auto' ) {
							$acceptmethod .= ':vbank(' . $this->get_expirytime( $expiry_time, 'Ymd' ) . ')';
						}
					}

					if ( $vareceipt ) {
						$acceptmethod .= ':va_receipt';
					}
				}

				if ( $paymethod == 'onlyhpp' ) {
					$acceptmethod .= ':HPP(2)';
				}

				return $acceptmethod;
			} else {
				$acceptmethod = 'SKIN(' . $skintype . ')';

				if ( $paymethod == 'onlydbank' ) {
					if ( ! $noreceipt ) {
						$acceptmethod .= ':no_receipt';
					}
				}

				if ( $paymethod == 'onlyvbank' ) {
					if ( $expiry_time ) {
						if ( $expiry_time != 'Auto' ) {
							$acceptmethod .= ':Vbank(' . $this->get_expirytime( $expiry_time, 'Ymd' ) . ')';
						}
					}

					if ( $vareceipt ) {
						$acceptmethod .= ':va_receipt';
					}
				}

				if ( $paymethod == 'onlyhpp' ) {
					$acceptmethod .= ':HPP(2)';
				}

				return $acceptmethod;
			}
		}

		public function get_quotabase( $type, $quotabase ) {
			if ( $type == 'web' ) {
				$quotabase_str = "";

				if ( $quotabase ) $quotabase_str .= implode( ":", str_replace( "개월", "", $quotabase ) );
			} else {
				$quotabase_str = "선택:일시불:";

				if ( $quotabase ) $quotabase_str .= implode( ":", str_replace( "개월", "", $quotabase ) ) . "개월";
			}

			return $quotabase_str;
		}

		public function woopay_form( $orderid ) {
			$order = new WC_Order( $orderid );

			$inicis_args = $this->get_woopay_args( $order );

			$inicis_args_array = array();

			$mid			= ( $this->testmode ) ? ( ( $this->escw_yn ) ? 'iniescrow0' : 'INIpayTest' ) : $this->mid;
			$admin			= ( $this->testmode ) ? '1111' : $this->admin;

			if ( ! $this->check_mobile() ) {
				if ( $this->inicis_method == 'tx' ) {
					$inicis_args[ 'mid' ]			= $mid;
					$inicis_args[ 'admin' ]			= $admin;
					$inicis_args[ 'rn' ]			= get_post_meta( $order->id, '_ini_rn', true );
					$inicis_args[ 'enctype' ]		= get_post_meta( $order->id, '_ini_enctype', true );
					$inicis_args[ 'ini_encfield' ]	= get_post_meta( $order->id, '_ini_encfield', true );
					$inicis_args[ 'ini_certid' ]	= get_post_meta( $order->id, '_ini_certid', true );
				}
			} else {
				$inicis_args[ 'P_MID' ]			= $mid;
			}

			foreach ( $inicis_args as $key => $value ) {
				$inicis_args_array[] = '<input type="hidden" name="'.esc_attr( $key ).'" id="'.esc_attr( $key ).'" value="'.esc_attr( $value ).'" />';
			}

			if ( ! $this->check_mobile() ) {
				$woopay_form = "<form method='post' id='ini' name='ini' method='post'>" . implode( '', $inicis_args_array ) . "</form>";
			} else {
				switch( $inicis_args[ 'gopaymethod' ] ) {
					case 'onlycard' :
						$mobile_form_action = 'https://mobile.inicis.com/smart/wcard/';
						break;
					case 'onlydbank':
						$mobile_form_action = 'https://mobile.inicis.com/smart/bank/';
						break;
					case 'onlyvbank':
						$mobile_form_action = 'https://mobile.inicis.com/smart/vbank/';
						break;
					case 'onlyhpp':
						$mobile_form_action = 'https://mobile.inicis.com/smart/mobile/';
						break;
					default :
						$mobile_form_action = '';
						break;
				}

				$woopay_form = "<form method='post' id='form_inicis' name='form_inicis' accept-charset='euc-kr' action='" . $mobile_form_action. "'>" . implode( '', $inicis_args_array ) . "</form>";
			}

			if ( ! $this->check_mobile() ) {
				$woopay_script_url = $this->woopay_plugin_url . 'assets/js/woopay.js';
			} else {
				$woopay_script_url = $this->woopay_plugin_url . 'assets/js/woopay-mobile.js';
			}

			wp_register_script( $this->woopay_api_name . 'woopay_script', $woopay_script_url, array( 'jquery' ), '1.0.0', true );

			$translation_array = array(
				'testmode_msg'		=> __( 'Test mode is enabled. Continue?', $this->woopay_domain ),
				'cancel_msg'		=> __( 'You have cancelled your transaction. Returning to cart.', $this->woopay_domain ),
				'refresh_msg'		=> __( 'Plugin not installed. Press Ctrl+F5, or try refreshing this page!', $this->woopay_domain ),
				'checkout_url'		=> WC()->cart->get_checkout_url(),
				'testmode'			=> $this->testmode,
				'response_url'		=> $this->get_api_url( 'response' ),
				'inicis_method'		=> $this->inicis_method,
			);

			wp_localize_script( $this->woopay_api_name . 'woopay_script', 'woopay_string', $translation_array );
			wp_enqueue_script( $this->woopay_api_name . 'woopay_script' );
			
			return $woopay_form;
		}

		public function process_payment( $orderid ) {
			$order = new WC_Order( $orderid );

			$this->woopay_start_payment( $orderid );

			if ( ! $this->check_mobile() ) {
				if ( $this->inicis_method == 'tx' ) {
					$inicis_args = $this->get_woopay_args( $order );

					require_once $this->woopay_plugin_basedir . '/bin/lib/INILib.php';

					$mid			= ( $this->testmode == 'yes' ) ? ( ( $this->escw_yn == 'yes' ) ? 'iniescrow0' : 'INIpayTest' ) : $this->mid;
					$admin			= ( $this->testmode == 'yes' ) ? '1111' : $this->admin;

					$inipay = new INIpay50;

					$inipay->SetField( 'inipayhome', $this->woopay_plugin_basedir . '/bin' );
					$inipay->SetField( 'inikeydir', $this->woopay_upload_dir );
					$inipay->SetField( 'type', 'chkfake' );
					$inipay->SetField( 'debug', ( $inicis_args[ 'testmode' ] == 'yes' ) ? 'true' : 'false' );
					$inipay->SetField( 'enctype', 'asym' );
					$inipay->SetField( 'admin', $admin );
					$inipay->SetField( 'checkopt', 'false' );
					$inipay->SetField( 'mid', $mid );
					$inipay->SetField( 'price', $inicis_args[ 'price'] );
					$inipay->SetField( 'nointerest', $inicis_args[ 'nointerest' ] );
					$inipay->SetField( 'quotabase', iconv( 'UTF-8', 'EUC-KR', $inicis_args[ 'quotabase' ] ) );

					$inipay->startAction();

					if( $inipay->GetResult('ResultCode') != '00' ) {
						echo iconv( 'EUC-KR', 'UTF-8', $inipay->GetResult( 'ResultMsg' ) );
						exit();
					}

					update_post_meta( $order->id, '_ini_rn', $inipay->GetResult( 'rn' ) );
					update_post_meta( $order->id, '_ini_enctype', $inipay->GetResult( 'enctype' ) );
					update_post_meta( $order->id, '_ini_encfield', $inipay->GetResult( 'encfield' ) );
					update_post_meta( $order->id, '_ini_certid', $inipay->GetResult( 'certid' ) );
				} else if ( $this->inicis_method == 'web' ) {
					require_once $this->woopay_plugin_basedir . '/bin/lib/INIStdPayUtil.php';

					$util			= new INIStdPayUtil();

					$timestamp		= $util->getTimestamp();
					update_post_meta( $order->id, '_ini_timestamp', $timestamp );
				}
			}

			if ( $this->testmode ) {
				wc_add_notice( __( '<strong>Test mode is enabled!</strong> Please disable test mode if you aren\'t testing anything.', $this->woopay_domain ), 'error' );
			}

			return array(
				'result' 	=> 'success',
				'redirect'	=> $order->get_checkout_payment_url( true )
			);
		}

		public function process_refund( $orderid, $amount = null, $reason = '' ) {
			$woopay_refund = new WooPayInicisRefund();
			$return = $woopay_refund->do_refund( $orderid, $amount, $reason );

			if ( $return[ 'result' ] == 'success' ) {
				return true;
			} else {
				return false;
			}
		}

		public function admin_options() {
			$currency_str = $this->get_currency_str( $this->allowed_currency );

			echo '<h3>' . $this->method_title . '</h3>';

			$this->get_woopay_settings();
			$hide_form = "";

			if ( ! $this->woopay_check_api() ) {
				echo '<div class="inline error"><p><strong>' . sprintf( __( 'Gateway Disabled', $this->woopay_domain ) . '</strong>: ' . __( 'Please check your permalink settings. You must use a permalink structure other than \'General\'. Click <a href="%s">here</a> to change your permalink settings.', $this->woopay_domain ), $this->get_url( 'admin', 'options-permalink.php' ) ) . '</p></div>';

				$hide_form = "display:none;";
			} else {
				if ( ! $this->testmode ) {
					if ( $this->inicis_method == 'web' ) {
						if ( $this->mid == '' ) {
							echo '<div class="inline error"><p><strong>' . __( 'Gateway Disabled', $this->woopay_domain ) . '</strong>: ' . __( 'Please select your Merchant ID.', $this->woopay_domain ). '</p></div>';
						} else if ( $this->admin == '' ) {
							echo '<div class="inline error"><p><strong>' . __( 'Gateway Disabled', $this->woopay_domain ) . '</strong>: ' . __( 'Please enter your Key Password.', $this->woopay_domain ). '</p></div>';
						} else if ( $this->sign_key == '' ) {
							echo '<div class="inline error"><p><strong>' . __( 'Gateway Disabled', $this->woopay_domain ) . '</strong>: ' . __( 'Please enter your Sign Key.', $this->woopay_domain ). '</p></div>';
						}
					} else if ( $this->inicis_method == 'tx' ) {
						if ( $this->mid == '' ) {
							echo '<div class="inline error"><p><strong>' . __( 'Gateway Disabled', $this->woopay_domain ) . '</strong>: ' . __( 'Please select your Merchant ID.', $this->woopay_domain ). '</p></div>';
						} else if ( $this->admin == '' ) {
							echo '<div class="inline error"><p><strong>' . __( 'Gateway Disabled', $this->woopay_domain ) . '</strong>: ' . __( 'Please enter your Key Password.', $this->woopay_domain ). '</p></div>';
						}
					} else {
						if ( $this->mid_lite == '' ) {
							echo '<div class="inline error"><p><strong>' . __( 'Gateway Disabled', $this->woopay_domain ) . '</strong>: ' . __( 'Please enter your Merchant ID.', $this->woopay_domain ). '</p></div>';
						} else if ( $this->admin_lite == '' ) {
							echo '<div class="inline error"><p><strong>' . __( 'Gateway Disabled', $this->woopay_domain ) . '</strong>: ' . __( 'Please enter your Symmetry Key.', $this->woopay_domain ). '</p></div>';
						}
					}
				} else {
					echo '<div class="inline error"><p><strong>' . __( 'Test mode is enabled!', $this->woopay_domain ) . '</strong> ' . __( 'Please disable test mode if you aren\'t testing anything.', $this->woopay_domain ) . '</p></div>';
				}

				if ( ! $this->is_valid_for_use( $this->allowed_currency ) ) {
					if ( ! $this->allow_other_currency ) {
						echo '<div class="inline error"><p><strong>' . __( 'Gateway Disabled', $this->woopay_domain ) .'</strong>: ' . sprintf( __( 'Your currency (%s) is not supported by this payment method. This payment method only supports: %s.', $this->woopay_domain ), get_woocommerce_currency(), $currency_str ) . '</p></div>';
					} else {
						echo '<div class="inline notice notice-info"><p><strong>' . __( 'Please Note', $this->woopay_domain ) .'</strong>: ' . sprintf( __( 'Your currency (%s) is not recommended by this payment method. This payment method recommeds the following currency: %s.', $this->woopay_domain ), get_woocommerce_currency(), $currency_str ) . '</p></div>';
					}
				}
			}

			echo '<div id="' . $this->woopay_plugin_name . '" style="' . $hide_form . '">';
			echo '<table class="form-table ' . $this->id . '">';
			$this->generate_settings_html();
			echo '</table>';
			echo '</div>';
		}

		public function init_form_fields() {
			// General Settings
			$general_array = array(
				'general_title' => array(
					'title' => __( 'General Settings', $this->woopay_domain ),
					'type' => 'title',
				),
				'enabled' => array(
					'title' => __( 'Enable/Disable', $this->woopay_domain ),
					'type' => 'checkbox',
					'label' => __( 'Enable this method.', $this->woopay_domain ),
					'default' => 'yes'
				),
				'testmode' => array(
					'title' => __( 'Enable/Disable Test Mode', $this->woopay_domain ),
					'type' => 'checkbox',
					'label' => __( 'Enable test mode.', $this->woopay_domain ),
					'description' => '',
					'default' => 'no'
				),
				'log_enabled' => array(
					'title' => __( 'Enable/Disable Logs', $this->woopay_domain ),
					'type' => 'checkbox',
					'label' => __( 'Enable logging.', $this->woopay_domain ),
					'description' => __( 'Logs will be automatically created when in test mode.', $this->woopay_domain ),
					'default' => 'no'
				),
				'log_control' => array(
					'title' => __( 'View/Delete Log', $this->woopay_domain ),
					'type' => 'log_control',
					'description' => '',
					'desc_tip' => '',
					'default' => 'no'
				),
				'title' => array(
					'title' => __( 'Title', $this->woopay_domain ),
					'type' => 'text',
					'description' => __( 'Title that users will see during checkout.', $this->woopay_domain ),
					'default' => $this->title_default,
				),
				'description' => array(
					'title' => __( 'Description', $this->woopay_domain ),
					'type' => 'textarea',
					'description' => __( 'Description that users will see during checkout.', $this->woopay_domain ),
					'default' => $this->desc_default,
				),
				'inicis_method' => array(
					'title' => __( 'Communication Method', $this->woopay_domain ),
					'type' => 'select',
					'class' => 'wc-enhanced-select inicis_method',
					'description' => __( 'Select the communication method. Recommended method is \'Web Standard\'.', $this->woopay_domain ),
					'options' => array(
						'web' => __( 'Web Standard', $this->woopay_domain ),
						'tx' => __( 'TX Mode', $this->woopay_domain ),
						'lite' => __( 'INILite Method', $this->woopay_domain ),
					),
					'default' => 'web'
				),
				'keyfile_upload' => array(
					'title' => __( 'Upload Keyfile', $this->woopay_domain ),
					'type' => 'keyfile_upload',
					'desc_tip' => '',
					'description' => sprintf( __( 'Please select your keyfile (as .ZIP file) and click \'Save Settings\'. Your keyfile will be uploaded to: <code>%s</code>', $this->woopay_domain ), $this->woopay_upload_dir . '/key/' ),
				),
				'mid' => array(
					'title' => __( 'Merchant ID', $this->woopay_domain ),
					'class' => 'wc-enhanced-select inicis_mid',
					'type' => 'select',
					'description' => __( 'Please select your Merchant ID.', $this->woopay_domain ),
					'options' => $this->get_mid()
				),
				'mid_lite' => array(
					'title' => __( 'Merchant ID', $this->woopay_domain ),
					'type' => 'text',
					'description' => __( 'Please enter your Merchant ID.', $this->woopay_domain ),
				),
				'sign_key' => array(
					'title' => __( 'Sign Key', $this->woopay_domain ),
					'type' => 'text',
					'description' => __( 'Please enter your Sign Key. You can find your key at <a href=\"https://iniweb.inicis.com/\" target=\"_blank\">https://iniweb.inicis.com/</a>.', $this->woopay_domain ),
				),
				'admin' => array(
					'title' => __( 'Key Password', $this->woopay_domain ),
					'type' => 'text',
					'description' => __( 'Please enter your Key Password. Default is: <code>1111</code>.', $this->woopay_domain ),
					'default' => ''
				),
				'admin_lite' => array(
					'title' => __( 'Symmetry Key', $this->woopay_domain ),
					'type' => 'text',
					'description' => __( 'Please enter your Symmetry Key. You can find your key at <a href=\"https://iniweb.inicis.com/\" target=\"_blank\">https://iniweb.inicis.com/</a>.', $this->woopay_domain ),
					'default' => ''
				),
				'expiry_time' => array(
					'title' => __( 'Expiry time in days', $this->woopay_domain ),
					'type'=> 'select',
					'description' => __( 'Select the virtual account transfer expiry time in days.', $this->woopay_domain ),
					'options'	=> array(
						'1'			=> __( '1 day', $this->woopay_domain ),
						'2'			=> __( '2 days', $this->woopay_domain ),
						'3'			=> __( '3 days', $this->woopay_domain ),
						'4'			=> __( '4 days', $this->woopay_domain ),
						'5'			=> __( '5 days', $this->woopay_domain ),
						'6'			=> __( '6 days', $this->woopay_domain ),
						'7'			=> __( '7 days', $this->woopay_domain ),
						'8'			=> __( '8 days', $this->woopay_domain ),
						'9'			=> __( '9 days', $this->woopay_domain ),
						'10'		=> __( '10 days', $this->woopay_domain ),
					),
					'default' => ( '5' ),
				),
				'escw_yn' => array(
					'title' => __( 'Escrow Settings', $this->woopay_domain ),
					'type' => 'checkbox',
					'description' => __( 'Force escrow settings.', $this->woopay_domain ),
					'default' => 'no',
				),
			);

			// Refund Settings
			$refund_array = array(
				'refund_title' => array(
					'title' => __( 'Refund Settings', $this->woopay_domain ),
					'type' => 'title',
				),
				'refund_btn_txt' => array(
					'title' => __( 'Refund Button Text', $this->woopay_domain ),
					'type' => 'text',
					'description' => __( 'Text for refund button that users will see.', $this->woopay_domain ),
					'default' => __( 'Refund', $this->woopay_domain ),
				),
				'customer_refund' => array (
					'title' => __( 'Refundable Satus for Customer', $this->woopay_domain ),
					'type' => 'multiselect',
					'class' => 'chosen_select',
					'description' => __( 'Select the order status for allowing refund.', $this->woopay_domain ),
					'options' => $this->get_status_array(),
				)
			);

			// Design Settings
			$design_array = array(
				'design_title' => array(
					'title' => __( 'Design Settings', $this->woopay_domain ),
					'type' => 'title',
				),
				'skintype' => array(
					'title' => __( 'Skin Type', $this->woopay_domain ),
					'type' => 'select',
					'description' => __( 'Select the skin type for your Inicis form.', $this->woopay_domain ),
					'options' => array(
						'BLUE' => 'Blue',
						'RED' => 'Red',
						'PURPLE' => 'Purple',
						'GREEN' => 'Green',
					)
				),
				'skincolor' => array(
					'title' => __( 'Skin Color', $this->woopay_domain ),
					'type' => 'text',
					'class' => 'color-picker-field',
					'desciprtion' => __( 'Select the background color for your Inicis form.', $this->woopay_domain ),
					'default' => ''
				),
				'logoimg' => array(
					'title' => __( 'Logo Image', $this->woopay_domain ),
					'type' => 'img_upload',
					'description' => __( 'Please select or upload your logo. The size should be 95*35. You can use GIF/JPG/PNG.', $this->woopay_domain ),
					'default' => '',
					'btn_name' => __( 'Select/Upload Logo', $this->woopay_domain ),
					'remove_btn_name' => __( 'Remove Logo', $this->woopay_domain ),
					'default_btn_url' => ''
				),
				'methodimg' => array(
					'title' => __( 'Payment Method Image', $this->woopay_domain ),
					'type' => 'img_upload',
					'description' => __( 'Please select or upload your image for the payment method. The size should be 128*100. You can use GIF/JPG/PNG, but the background cannot be transparent.', $this->woopay_domain ),
					'default' => '',
					'btn_name' => __( 'Select/Upload Image', $this->woopay_domain ),
					'remove_btn_name' => __( 'Remove Image', $this->woopay_domain ),
					'default_btn_url' => ''
				),
				'checkout_img' => array(
					'title' => __( 'Checkout Processing Image', $this->woopay_domain ),
					'type' => 'img_upload',
					'description' => __( 'Please select or upload your image for the checkout processing page. Leave blank to show no image.', $this->woopay_domain ),
					'default' => $this->woopay_plugin_url . 'assets/images/' . $this->default_checkout_img . '.png',
					'btn_name' => __( 'Select/Upload Image', $this->woopay_domain ),
					'remove_btn_name' => __( 'Remove Image', $this->woopay_domain ),
					'default_btn_name' => __( 'Use Default', $this->woopay_domain ),
					'default_btn_url' => $this->woopay_plugin_url . 'assets/images/' . $this->default_checkout_img . '.png',
				),	
				'checkout_txt' => array(
					'title' => __( 'Checkout Processing Text', $this->woopay_domain ),
					'type' => 'textarea',
					'description' => __( 'Text that users will see on the checkout processing page. You can use some HTML tags as well.', $this->woopay_domain ),
					'default' => __( "<strong>Please wait while your payment is being processed.</strong>\nIf you see this page for a long time, please try to refresh the page.", $this->woopay_domain )
				),
				'show_chrome_msg' => array(
					'title' => __( 'Chrome Message', $this->woopay_domain ),
					'type' => 'checkbox',
					'label' => __( 'Show steps to enable NPAPI for Chrome users using less than v45.', $this->woopay_domain ),
					'description' => '',
					'default' => 'yes'
				)
			);

			if ( $this->id == 'inicis_card' ) {
				$general_array = array_merge( $general_array,
					array(
						'quotabase' => array(
							'title' => __( 'Installments Setting', $this->woopay_domain ),
							'type' => 'multiselect',
							'class' => 'chosen_select',
							'description' => __( 'Select installments.', $this->woopay_domain ),
							'options'       => array(
								'2' => __( '2 Months', $this->woopay_domain ),
								'3' => __( '3 Months', $this->woopay_domain ),
								'4' => __( '4 Months', $this->woopay_domain ),
								'5' => __( '5 Months', $this->woopay_domain ),
								'6' => __( '6 Months', $this->woopay_domain ),
								'7' => __( '7 Months', $this->woopay_domain ),
								'8' => __( '8 Months', $this->woopay_domain ),
								'9' => __( '9 Months', $this->woopay_domain ),
								'10' => __( '10 Months', $this->woopay_domain ),
								'11' => __( '11 Months', $this->woopay_domain ),
								'12' => __( '12 Months', $this->woopay_domain ),
							),
						),
						'nointerest' => array(
							'title' => __( 'No Interest Setting', $this->woopay_domain ),
							'type' => 'checkbox',
							'description' => __( 'Allow no interest settings.', $this->woopay_domain ),
							'default' => 'no',
						),
					)
				);
			}

			if ( $this->id == 'inicis_transfer' ) {
				$general_array = array_merge( $general_array,
					array(
						'noreceipt' => array(
							'title' => __( 'Allow Cash Receipt', $this->woopay_domain ),
							'type' => 'checkbox',
							'description' => __( 'Allow cash receipt for customers.', $this->woopay_domain ),
							'default' => 'yes',
						)
					)
				);
			}

			if ( $this->id == 'inicis_virtual' ) {
				$general_array = array_merge( $general_array,
					array(
						'vareceipt' => array(
							'title' => __( 'Allow Cash Receipt', $this->woopay_domain ),
							'type' => 'checkbox',
							'description' => __( 'Allow cash receipt for customers.', $this->woopay_domain ),
							'default' => 'no',
						),
						'callback_url' => array(
							'title' => __( 'Callback URL', $this->woopay_domain ),
							'type' => 'txt_info',
							'txt' => $this->get_api_url( 'cas_response' ),
							'description' => __( 'Callback URL used for payment notice from Inicis.', $this->woopay_domain )
						)
					)
				);
			}

			if ( ! $this->allow_testmode ) {
				$general_array[ 'testmode' ] = array(
					'title' => __( 'Enable/Disable Test Mode', $this->woopay_domain ),
					'type' => 'txt_info',
					'txt' => __( 'You cannot test this payment method.', $this->woopay_domain ),
					'description' => '',
				);
			}

			if ( $this->id == 'inicis_card' || $this->id == 'inicis_mobile' ) {
				unset( $general_array[ 'escw_yn' ] );
			}

			if ( $this->id != 'inicis_virtual' ) {
				unset( $general_array[ 'expiry_time' ] );
			}

			if ( ! in_array( 'refunds', $this->supports ) ) {
				unset( $refund_array[ 'refund_btn_txt' ] );
				unset( $refund_array[ 'customer_refund' ] );

				$refund_array[ 'refund_title' ][ 'description' ] = __( 'This payment method does not support refunds. You can refund each transaction using the merchant page.', $this->woopay_domain );
			}

			$form_array = array_merge( $general_array, $refund_array );
			$form_array = array_merge( $form_array, $design_array );

			$this->form_fields = $form_array;

			$inicis_mid_bad_msg = __( 'This Merchant ID is not from Planet8. Please visit the following page for more information: <a href="http://www.planet8.co/woopay-inicis-change-mid/" target="_blank">http://www.planet8.co/woopay-inicis-change-mid/</a>', $this->woopay_domain );

			if ( is_admin() ) {
				if ( $this->id != '' ) {
					wc_enqueue_js( "
						jQuery( '.inicis_method' ).change(function() {
							var val = jQuery( this ).val();

							if ( val == 'web' ) {
								jQuery( '#woocommerce_" . $this->id . "_mid' ).closest( 'tr' ).show();
								jQuery( '#woocommerce_" . $this->id . "_mid_lite' ).closest( 'tr' ).hide();
								jQuery( '#woocommerce_" . $this->id . "_admin' ).closest( 'tr' ).show();
								jQuery( '#woocommerce_" . $this->id . "_admin_lite' ).closest( 'tr' ).hide();
								jQuery( '#woocommerce_" . $this->id . "_keyfile_upload' ).closest( 'tr' ).show();
								jQuery( '#woocommerce_" . $this->id . "_sign_key' ).closest( 'tr' ).show();
								jQuery( '#woocommerce_" . $this->id . "_skintype' ).closest( 'tr' ).hide();
								jQuery( '#woocommerce_" . $this->id . "_skincolor' ).closest( 'tr' ).show();
								jQuery( '#woocommerce_" . $this->id . "_nointerest' ).closest( 'tr' ).hide();
							} else if ( val == 'tx' ) {
								jQuery( '#woocommerce_" . $this->id . "_mid' ).closest( 'tr' ).show();
								jQuery( '#woocommerce_" . $this->id . "_mid_lite' ).closest( 'tr' ).hide();
								jQuery( '#woocommerce_" . $this->id . "_admin' ).closest( 'tr' ).show();
								jQuery( '#woocommerce_" . $this->id . "_admin_lite' ).closest( 'tr' ).hide();
								jQuery( '#woocommerce_" . $this->id . "_keyfile_upload' ).closest( 'tr' ).show();
								jQuery( '#woocommerce_" . $this->id . "_sign_key' ).closest( 'tr' ).hide();
								jQuery( '#woocommerce_" . $this->id . "_skintype' ).closest( 'tr' ).show();
								jQuery( '#woocommerce_" . $this->id . "_skincolor' ).closest( 'tr' ).hide();
								jQuery( '#woocommerce_" . $this->id . "_nointerest' ).closest( 'tr' ).show();
							} else if ( val == 'lite' ) {
								jQuery( '#woocommerce_" . $this->id . "_mid' ).closest( 'tr' ).hide();
								jQuery( '#woocommerce_" . $this->id . "_mid_lite' ).closest( 'tr' ).show();
								jQuery( '#woocommerce_" . $this->id . "_admin' ).closest( 'tr' ).hide();
								jQuery( '#woocommerce_" . $this->id . "_admin_lite' ).closest( 'tr' ).show();
								jQuery( '#woocommerce_" . $this->id . "_keyfile_upload' ).closest( 'tr' ).hide();
								jQuery( '#woocommerce_" . $this->id . "_sign_key' ).closest( 'tr' ).hide();
								jQuery( '#woocommerce_" . $this->id . "_skintype' ).closest( 'tr' ).show();
								jQuery( '#woocommerce_" . $this->id . "_skincolor' ).closest( 'tr' ).hide();
							}
						}).change();

						jQuery( '.inicis_mid' ).change(function() {
							var val = jQuery( this ).val();
							var bad_mid = '<span style=\"color:red;font-weight:bold;\">" . $inicis_mid_bad_msg . "</span>';

							jQuery( '#woocommerce_" . $this->id . "_mid' ).closest( 'td' ).append( '<div id=\"inicis_mid_bad_msg\"></div>' );

							if ( val == '' || val == undefined ) {
								jQuery( '#woocommerce_" . $this->id . "_mid' ).closest( 'tr' ).css( 'background-color', 'transparent' );
								jQuery( '#inicis_mid_bad_msg' ).remove();
							} else {
								if ( val.substring( 0, 3 ) == 'PLA' || val.substring( 0, 5 ) == 'ESPLA' ) {
									jQuery( '#woocommerce_" . $this->id . "_mid' ).closest( 'tr' ).css( 'background-color', 'transparent' );
									jQuery( '#inicis_mid_bad_msg' ).html( '' );
								} else {
									jQuery( '#woocommerce_" . $this->id . "_mid' ).closest( 'tr' ).css( 'background-color', '#FFC1C1' );
									jQuery( '#inicis_mid_bad_msg' ).html( bad_mid );
								}
							}
						}).change();
					" );
				}
			}
		}
	}

	return new WooPayInicisPayment();
}