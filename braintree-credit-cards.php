<?php
class WPSC_Payment_Gateway_Braintree_Credit_Cards extends WPSC_Payment_Gateway {
	
	public function __construct() {
		parent::__construct();
		$this->title            = __( 'PayPal powered by Braintree - Cards', 'wpsc_authorize_net' );
		$this->image            = WPSC_URL . '/images/cc.gif';
		$this->supports         = array( 'default_credit_card_form', 'tokenization', 'tev1', 'auth-capture', 'refunds' );
		$this->sandbox          = $this->setting->get( 'sandbox' ) == '1' ? true : false;
		$this->payment_capture 	= $this->setting->get( 'payment_capture' ) !== null ? $this->setting->get( 'payment_capture' ) : 'standard';
		// Define user set variables
	}

	public function init() {
		parent::init();

		// Disable if not setup using BT Auth
		if ( ! WPEC_Btree_Helpers::is_gateway_setup( 'braintree-credit-cards' ) ) {
			// Remove gateway if its not setup properly
			add_filter( 'wpsc_get_active_gateways', array( $this, 'remove_gateways' ) );
			add_filter( 'wpsc_payment_method_form_fields', array( $this, 'remove_gateways_v2' ), 999 );			
		}

		// Tev1 fields
		add_action( 'wpsc_tev1_default_credit_card_form_fields_braintree-credit-cards', array( $this, 'tev1_checkout_fields'), 10, 2 );
		add_action( 'wpsc_tev1_default_credit_card_form_end_braintree-credit-cards', array( $this, 'tev1_checkout_fields_extra') );
		// Tev2 fields
		add_filter( 'wpsc_default_credit_card_form_fields_braintree-credit-cards', array( $this, 'tev2_checkout_fields' ), 10, 2 );
		add_action( 'wpsc_default_credit_card_form_end_braintree-credit-cards', array( $this, 'tev2_checkout_fields_extra' ) );
	}

	public function tev2_checkout_fields( $fields, $name ) {
		unset($fields['card-name-field']);

		$fields['card-number-field'] = '<p class="wpsc-form-row wpsc-form-row-wide wpsc-cc-field">
				<label for="' . esc_attr( $name ) . '-card-number">' . __( 'Card Number', 'wp-e-commerce' ) . ' <span class="required">*</span></label>
				<div id="braintree-credit-cards-card-number" class="bt-hosted-field"></div>
			</p>';
		$fields['card-expiry-field'] = '<p class="wpsc-form-row-middle wpsc-cc-field">
				<label for="' . esc_attr( $name ) . '-card-expiry">' . __( 'Expiration Date', 'wp-e-commerce' ) . ' <span class="required">*</span></label>
				<div id="braintree-credit-cards-card-expiry" class="bt-hosted-field"></div>
			</p>';
		$fields['card-cvc-field'] = '<p class="wpsc-form-row-last wpsc-cc-field">
				<label for="' . esc_attr( $name ) . '-card-cvc">' . __( 'Card Code', 'wp-e-commerce' ) . ' <span class="required">*</span></label>
				<div id="braintree-credit-cards-card-cvc" class="bt-hosted-field"></div></td>
			</p>';

		return $fields;
	}

	public function tev2_checkout_fields_extra( $name ) {
		echo '<div id="pp-btree-hosted-fields-modal" class="pp-btree-hosted-fields-modal-hidden" tabindex="-1">
				<div class="pp-btree-hosted-fields-bt-mask"></div>
					<div class="pp-btree-hosted-fields-bt-modal-frame">
						<div class="pp-btree-hosted-fields-bt-modal-header">
							<div class="header-text">Authentication</div>
						</div>
						<div class="pp-btree-hosted-fields-bt-modal-body"></div>
						<div class="pp-btree-hosted-fields-bt-modal-footer"><a id="pp-btree-hosted-fields-text-close" href="#">Cancel</a></div>
				  </div>
			</div>';
	}

	public function tev1_checkout_fields( $fields, $name ) {
		unset( $fields['card-name-field'] );

		$fields['card-number-field'] = '<tr><td class="wpsc-form-row wpsc-form-row-wide wpsc-cc-field">
					<label for="' . esc_attr( $name ) . '-card-number">' . __( 'Card Number', 'wp-e-commerce' ) . ' <span class="required">*</span></label></td>
					<td><div id="braintree-credit-cards-card-number" class="bt-hosted-field"></div></td>
				</tr>';
		$fields['card-expiry-field'] = '<tr><td class="wpsc-form-row-middle wpsc-cc-field">
					<label for="' . esc_attr( $name ) . '-card-expiry">' . __( 'Expiration Date', 'wp-e-commerce' ) . ' <span class="required">*</span></label></td>
					<td><div id="braintree-credit-cards-card-expiry" class="bt-hosted-field"></div></td>
				</tr>';
		$fields['card-cvc-field'] = '<tr><td class="wpsc-form-row-last wpsc-cc-field">
					<label for="' . esc_attr( $name ) . '-card-cvc">' . __( 'Card Code', 'wp-e-commerce' ) . ' <span class="required">*</span></label></td>
					<td><div id="braintree-credit-cards-card-cvc" class="bt-hosted-field"></div></td>
				</tr>';

		return $fields;
	}
	
	public function tev1_checkout_fields_extra( $name ) {
		$output = '';

		$output .= '
			<div id="pp-btree-hosted-fields-modal" class="pp-btree-hosted-fields-modal-hidden" tabindex="-1">
				<div class="pp-btree-hosted-fields-bt-mask"></div>
					<div class="pp-btree-hosted-fields-bt-modal-frame">
						<div class="pp-btree-hosted-fields-bt-modal-header">
							<div class="header-text">Authentication</div>
						</div>
						<div class="pp-btree-hosted-fields-bt-modal-body"></div>
						<div class="pp-btree-hosted-fields-bt-modal-footer"><a id="pp-btree-hosted-fields-text-close" href="#">Cancel</a></div>
				  </div>
			</div>';

		echo $output;		
	}

	public function process() {
		global $braintree_settings;

		$order = $this->purchase_log;
		$purchase_log = new WPSC_Purchase_Log( $order->get('sessionid'), 'sessionid' );

		$payment_method_nonce = $_POST['pp_btree_method_nonce'];
		$kount_fraud = isset( $_POST['pp_btree_card_kount'] ) ? strip_tags( trim ( $_POST['pp_btree_card_kount'] ) ) : '';

		if ( $this->setting->get( 'settlement' ) == 'upfront' ) {
			$submit_for_settlement = true;
		} else {
			$submit_for_settlement = false;
		}

		$order_status = $submit_for_settlement === true ? WPSC_Purchase_Log::ACCEPTED_PAYMENT : WPSC_Purchase_Log::ORDER_RECEIVED;

		// Check 3DS transaction.
		$threedcheck = true;
		$braintree_threedee_secure = $this->setting->get('three_d_secure');
		$force3ds = false;
		if ( '1' == $braintree_threedee_secure ) {
			$force3ds = true;
			$threedcheck = $this->check_3ds_risk_transaction( $payment_method_nonce );
		}

		if ( ! $threedcheck ) {
			// 3DS check failed so return;
			$error = __( '3D Secure verification failed.', 'wp-e-commerce' );
			$order->set( 'processed', WPSC_Purchase_Log::INCOMPLETE_SALE )->save();
			$order->add_note( $error );
			WPEC_Btree_Helpers::set_payment_error_message( $error );
			wp_safe_redirect( $this->get_shopping_cart_payment_url() );
		}

		$phone_field = $this->checkout_data->get('billingphone');

		$params = array(
			'amount' => $order->get('totalprice'),
			'channel' => 'WPec_Cart_PPpbBT',
			'orderId' => $order->get('id'),
			'paymentMethodNonce' => $payment_method_nonce,
			'customer' => array(
				'firstName' => $this->checkout_data->get('billingfirstname'),
				'lastName' => $this->checkout_data->get('billinglastname'),
				'phone' => isset( $phone_field ) ? $phone_field : '',
				'email' => $this->checkout_data->get('billingemail'),
			),
			'billing' => array(
				'firstName' => $this->checkout_data->get('billingfirstname'),
				'lastName' => $this->checkout_data->get('billinglastname'),
				'streetAddress' => $this->checkout_data->get('billingaddress'),
				'locality' => $this->checkout_data->get('billingcity'),
				'region' => wpsc_get_state_by_id( wpsc_get_customer_meta( '_wpsc_cart.billing_region' ), 'code' ),
				'postalCode' => $this->checkout_data->get('billingpostcode'),
				'countryCodeAlpha2' => $this->checkout_data->get('billingcountry'),
			),
			'shipping' => array(
				'firstName' => $this->checkout_data->get('shippingfirstname'),
				'lastName' => $this->checkout_data->get('shippinglastname'),
				'streetAddress' => $this->checkout_data->get('shippingaddress'),
				'locality' => $this->checkout_data->get('shippingcity'),
				'region' => wpsc_get_state_by_id( wpsc_get_customer_meta( '_wpsc_cart.delivery_region' ), 'code' ),
				'postalCode' => $this->checkout_data->get('shippingpostcode'),
				'countryCodeAlpha2' => $this->checkout_data->get('shippingcountry'),
			),
			'options' => array(
				'submitForSettlement' => $submit_for_settlement,
				'threeDSecure' => array(
					'required' => $force3ds,
				),
			),
			'deviceData' => $kount_fraud,
		);
		
		if ( WPEC_Btree_Helpers::bt_auth_is_connected() ) {
			$acc_token = get_option( 'wpec_braintree_auth_access_token' );
			$gateway = new Braintree_Gateway( array(
				'accessToken' => $acc_token,
			));

			$result = $gateway->transaction()->sale( $params );
		} else {
			WPEC_Btree_Helpers::setBraintreeConfiguration();
			$result = Braintree_Transaction::sale( $params );
		}

		// In theory all error handling should be done on the client side...?
		if ( $result->success ) {
			// Payment complete
			$order->set( 'processed', $order_status )->save();
			$order->set( 'transactid', $result->transaction->id )->save();

			if ( false === $submit_for_settlement ) {
				// Order is authorized
				$order->set( 'bt_order_status' , 'Open' )->save();
				$order->add_note( __( 'Order opened. Capture the payment below.', 'wp-e-commerce' ) )->save();
			}

			$this->go_to_transaction_results();
		} else {
			if ( $result->transaction ) {
				$order->set( 'processed', WPSC_Purchase_Log::INCOMPLETE_SALE )->save();
				WPEC_Btree_Helpers::set_payment_error_message( $result->transaction->processorResponseText );
				wp_safe_redirect( $this->get_shopping_cart_payment_url() );
			} else {
				$error[] = "Payment Error: " . $result->message;

				WPEC_Btree_Helpers::set_payment_error_message( $error );
				wp_safe_redirect( $this->get_shopping_cart_payment_url() );
			}
		}
		exit;
	}

	public function check_3ds_risk_transaction( $nonce ) {
		$pp_3ds_risk = $this->setting->get( 'three_d_secure_risk' ) != false ? $this->setting->get( 'three_d_secure_risk' ) : 'standard' ;
		$auth_3ds = false;

		if ( WPEC_Btree_Helpers::bt_auth_can_connect() && WPEC_Btree_Helpers::bt_auth_is_connected() ) {
			$acc_token = get_option( 'wpec_braintree_auth_access_token' );

			$gateway = new Braintree_Gateway( array(
				'accessToken' => $acc_token,
			));

			$auth_3ds = true;
		}

		try {
			$paymentMethodNonce = $auth_3ds ? $gateway->PaymentMethodNonce()->find( $nonce ) : Braintree_PaymentMethodNonce::find( $nonce );
		} catch (Braintree_Exception_NotFound $e) {
			echo 'Caught exception: ',  $e->getMessage(), "\n";
			exit;
		}

		$info = $paymentMethodNonce->threeDSecureInfo;

		if ( empty( $info ) ) {
			return true;
		}

		// Level should be 'strict' or 'standard'
		$level = $pp_3ds_risk;

		$matrix  = array(
			'standard' => array(
				'unsupported_card' => true,
				'lookup_error'     => true,
				'lookup_enrolled'  => true,
				'authenticate_successful_issuer_not_participating' => true,
				'authentication_unavailable'  => true,
				'authenticate_signature_verification_failed'  => false,
				'authenticate_successful'  => true,
				'authenticate_attempt_successful'  => true,
				'authenticate_failed'  => false,
				'authenticate_unable_to_authenticate'  => true,
				'authenticate_error'  => true,
			),
			'strict' => array(
				'unsupported_card' => false,
				'lookup_error'     => false,
				'lookup_enrolled'  => true,
				'authenticate_successful_issuer_not_participating' => true,
				'authentication_unavailable'  => false,
				'authenticate_signature_verification_failed'  => false,
				'authenticate_successful'  => true,
				'authenticate_attempt_successful'  => true,
				'authenticate_failed'  => false,
				'authenticate_unable_to_authenticate'  => false,
				'authenticate_error'  => false,
			)
		);

		return apply_filters( 'wpec_braintree_3ds_pass_or_fail', $matrix[ $level ][ $info->status ], $level );
	}

	public function capture_payment( $log, $transaction_id ) {

		if ( $log->get( 'gateway' ) == 'braintree-credit-cards' && $log->get( 'bt_order_status' ) == 'Open' ) {

			$transaction_id = $log->get( 'transactid' );
			$log->get( 'totalprice' );

			if ( WPEC_Btree_Helpers::bt_auth_can_connect() && WPEC_Btree_Helpers::bt_auth_is_connected() ) {
				$acc_token = get_option( 'wpec_braintree_auth_access_token' );

				$gateway = new Braintree_Gateway( array(
					'accessToken' => $acc_token,
				));
				$result = $gateway->transaction()->submitForSettlement( $transaction_id );
			} else {
				WPEC_Btree_Helpers::setBraintreeConfiguration();
				$result = Braintree_Transaction::submitForSettlement( $transaction_id );
			}

			if ( $result->success ) {
				$log->set( 'processed', WPSC_Purchase_Log::ACCEPTED_PAYMENT )->save();
				$log->set( 'bt_order_status' , 'Completed' )->save();

				return true;
			} else {
				return false;
			}
		}
		return false;
	}

	public function process_refund( $log, $amount = 0.00, $reason = '', $manual = false ) {
		if ( $log->get( 'gateway' ) == 'braintree-credit-cards' ) {

			// Check if its a void
			if ( $log->get( 'bt_order_status' ) == 'Open' ) {
				// Process a Void on the Authorization
				$transaction_id = $log->get( 'transactid' );

				if ( WPEC_Btree_Helpers::bt_auth_can_connect() && WPEC_Btree_Helpers::bt_auth_is_connected() ) {
					$acc_token = get_option( 'wpec_braintree_auth_access_token' );

					$gateway = new Braintree_Gateway( array(
						'accessToken' => $acc_token,
					));
					$result = $gateway->transaction()->void( $transaction_id );
				} else {
					WPEC_Btree_Helpers::setBraintreeConfiguration();
					$result = Braintree_Transaction::void( $transaction_id );
				}

				if ( $result->success ) {
					// Set a log meta entry, and save log before adding refund note.
					$log->set( 'processed', WPSC_Purchase_Log::INCOMPLETE_SALE )->save();
					$log->set( 'total_order_refunded' , $log->get( 'totalprice' ) )->save();
					$log->add_note( __( 'Authorization voided.', 'wp-e-commerce' ) )->save();
					$log->set( 'bt_order_status', 'Voided' )->save();

					remove_action( 'wpsc_order_fully_refunded', 'wpsc_update_order_status_fully_refunded' );

					return true;
				} else {
					return false;
				}
			}
			// End Void code block

			if ( 0.00 == $amount ) {
				return new WP_Error( 'braintree_credit_cards_refund_error', __( 'Refund Error: You need to specify a refund amount.', 'wp-e-commerce' ) );
			}

			$log = wpsc_get_order( $log );

			if ( ! $log->get( 'transactid' ) ) {
				return new WP_Error( 'error', __( 'Refund Failed: No transaction ID', 'wp-e-commerce' ) );
			}

			$max_refund  = $log->get( 'totalprice' ) - $log->get_total_refunded();

			if ( $amount && $max_refund < $amount || 0 > $amount ) {
				throw new Exception( __( 'Invalid refund amount', 'wp-e-commerce' ) );
			}

			if ( $manual ) {
				$current_refund = $log->get_total_refunded();

				// Set a log meta entry, and save log before adding refund note.
				$log->set( 'total_order_refunded' , $amount + $current_refund )->save();
				$log->set( 'bt_order_status', 'Refunded' )->save();

				$log->add_refund_note(
					sprintf( __( 'Refunded %s via Manual Refund', 'wp-e-commerce' ), wpsc_currency_display( $amount ) ),
					$reason
				);

				return true;
			}

			$transaction_id = $log->get( 'transactid' );

			if ( WPEC_Btree_Helpers::bt_auth_can_connect() && WPEC_Btree_Helpers::bt_auth_is_connected() ) {
				$acc_token = get_option( 'wpec_braintree_auth_access_token' );

				$gateway = new Braintree_Gateway( array(
					'accessToken' => $acc_token,
				));
				$result = $gateway->transaction()->refund( $transaction_id );
			} else {
				WPEC_Btree_Helpers::setBraintreeConfiguration();
				$result = Braintree_Transaction::refund( $transaction_id );
			}

			if ( $result->success ) {
				
				$current_refund = $log->get_total_refunded();

				// Set a log meta entry, and save log before adding refund note.
				$log->set( 'total_order_refunded' , $amount + $current_refund )->save();
				$log->set( 'bt_order_status', 'Refunded' )->save();

				return true;
			} else {
				return false;
			}
		}

		return false;		
	}

	public function manual_credentials( $hide = false ) {
		$hidden = $hide ? ' style="display:none;"' : '';
	?>
		<!-- Account Credentials -->
		<tr id="bt-cc-manual-header">
			<td colspan="2">
				<h4><?php _e( 'Account Credentials', 'wpsc_authorize_net' ); ?></h4>
			</td>
		</tr>
		<tr id="bt-cc-manual-public-key">
			<td>
				<label for="wpsc-worldpay-secure-net-id"><?php _e( 'Public Key', 'wpsc_authorize_net' ); ?></label>
			</td>
			<td>
				<input type="text" name="<?php echo esc_attr( $this->setting->get_field_name( 'public_key' ) ); ?>" value="<?php echo esc_attr( $this->setting->get( 'public_key' ) ); ?>" id="wpsc-anet-api-id" />
			</td>
		</tr>
		<tr id="bt-cc-manual-private-key">
			<td>
				<label for="wpsc-worldpay-secure-key"><?php _e( 'Private Key', 'wpsc_authorize_net' ); ?></label>
			</td>
			<td>
				<input type="text" name="<?php echo esc_attr( $this->setting->get_field_name( 'private_key' ) ); ?>" value="<?php echo esc_attr( $this->setting->get( 'private_key' ) ); ?>" id="wpsc-anet-trans-key" />
			</td>
		</tr>
		<tr id="bt-cc-manual-sandbox">
			<td>
				<label><?php _e( 'Sandbox Mode', 'wpsc_authorize_net' ); ?></label>
			</td>
			<td>
				<label><input <?php checked( $this->setting->get( 'sandbox_cc' ) ); ?> type="radio" name="<?php echo esc_attr( $this->setting->get_field_name( 'sandbox_cc' ) ); ?>" value="1" /> <?php _e( 'Yes', 'wpsc_authorize_net' ); ?></label>&nbsp;&nbsp;&nbsp;
				<label><input <?php checked( (bool) $this->setting->get( 'sandbox_cc' ), false ); ?> type="radio" name="<?php echo esc_attr( $this->setting->get_field_name( 'sandbox_cc' ) ); ?>" value="0" /> <?php _e( 'No', 'wpsc_authorize_net' ); ?></label>
			</td>
		</tr>
	<?php
	}
	
	
	public function setup_form() {
		if ( WPEC_Btree_Helpers::bt_auth_can_connect() ) {
			echo WPEC_Btree_Helpers::show_connect_button();
		} else {
			$this->manual_credentials(true);
		}
	?>
		<tr>
			<td colspan="2">
				<h4><?php _e( 'Transaction Settings', 'wpsc_authorize_net' ); ?></h4>
			</td>
		</tr>
		<tr>
			<td>
				<label for="wpsc-worldpay-secure-key"><?php _e( 'Settlement Type', 'wpsc_authorize_net' ); ?></label>
			</td>
			<td>
				<select id="wpsc-worldpay-payment-capture" name="<?php echo esc_attr( $this->setting->get_field_name( 'settlement' ) ); ?>">
					<option value='upfront' <?php selected( 'upfront', $this->setting->get( 'settlement' ) ); ?>><?php _e( 'Upfront Settlement', 'wpec-square' )?></option>
					<option value='deferred' <?php selected( 'deferred', $this->setting->get( 'settlement' ) ); ?>><?php _e( 'Deferred Settlement', 'wpec-square' )?></option>
				</select>
			</td>
		</tr>
		<tr>
			<td colspan="2">
				<h4><?php _e( '3D Secure Settings', 'wpsc_authorize_net' ); ?></h4>
			</td>
		</tr>		
		<tr>
			<td>
				<label for="wpsc-worldpay-secure-key"><?php _e( 'Enable 3D Secure', 'wpsc_authorize_net' ); ?></label>
			</td>
			<td>
				<label><input <?php checked( $this->setting->get( 'three_d_secure' ) ); ?> type="radio" name="<?php echo esc_attr( $this->setting->get_field_name( 'three_d_secure' ) ); ?>" value="1" /> <?php _e( 'Yes', 'wpec-square' ); ?></label>&nbsp;&nbsp;&nbsp;
				<label><input <?php checked( (bool) $this->setting->get( 'three_d_secure' ), false ); ?> type="radio" name="<?php echo esc_attr( $this->setting->get_field_name( 'three_d_secure' ) ); ?>" value="0" /> <?php _e( 'No', 'wpec-square' ); ?></label>
			</td>
		</tr>
		<tr>
			<td>
				<label for="wpsc-worldpay-secure-key"><?php _e( 'Allow only 3D Secure', 'wpsc_authorize_net' ); ?></label>
			</td>
			<td>
				<label><input <?php checked( $this->setting->get( 'three_d_secure_only' ) ); ?> type="radio" name="<?php echo esc_attr( $this->setting->get_field_name( 'three_d_secure_only' ) ); ?>" value="1" /> <?php _e( 'Yes', 'wpec-square' ); ?></label>&nbsp;&nbsp;&nbsp;
				<label><input <?php checked( (bool) $this->setting->get( 'three_d_secure_only' ), false ); ?> type="radio" name="<?php echo esc_attr( $this->setting->get_field_name( 'three_d_secure_only' ) ); ?>" value="0" /> <?php _e( 'No', 'wpec-square' ); ?></label>
				<p class="description"><?php _e( 'Only transactions that pass 3D Secure verifications are allowed to be processed', 'wpsc' ); ?></p>
			</td>
		</tr>		
		<tr>
			<td>
				<label for="wpsc-worldpay-payment-capture"><?php _e( '3D Secure Risk Settings', 'wpec-square' ); ?></label>
			</td>
			<td>
				<select id="wpsc-worldpay-payment-capture" name="<?php echo esc_attr( $this->setting->get_field_name( 'three_d_secure_risk' ) ); ?>">
					<option value='standard' <?php selected( 'standard', $this->setting->get( 'three_d_secure_risk' ) ); ?>><?php _e( 'Standard', 'wpec-square' )?></option>
					<option value='strict' <?php selected( 'strict', $this->setting->get( 'three_d_secure_risk' ) ); ?>><?php _e( 'Strict', 'wpec-square' )?></option>
				</select>
			</td>
		</tr>
		<!-- Error Logging -->
		<tr>
			<td colspan="2">
				<h4><?php _e( 'Error Logging', 'wpec-square' ); ?></h4>
			</td>
		</tr>
		<tr>
			<td>
				<label><?php _e( 'Enable Debugging', 'wpec-square' ); ?></label>
			</td>
			<td>
				<label><input <?php checked( $this->setting->get( 'debugging' ) ); ?> type="radio" name="<?php echo esc_attr( $this->setting->get_field_name( 'debugging' ) ); ?>" value="1" /> <?php _e( 'Yes', 'wpsc_authorize_net' ); ?></label>&nbsp;&nbsp;&nbsp;
				<label><input <?php checked( (bool) $this->setting->get( 'debugging' ), false ); ?> type="radio" name="<?php echo esc_attr( $this->setting->get_field_name( 'debugging' ) ); ?>" value="0" /> <?php _e( 'No', 'wpsc_authorize_net' ); ?></label>
			</td>
		</tr>
	<?php
	}

	public function get_image_url() {
		return apply_filters( 'wpsc_braintree-credit-cards_logo', WPSC_URL . '/images/cc.gif' );
	}

	public function remove_gateways( $gateways ) {
		foreach ( $gateways as $i => $gateway ) {
			if ( 'braintree-credit-cards' == $gateway ) {
				unset( $gateways[ $i ] );
			}
		}
		return $gateways;
	}

	public function remove_gateways_v2( $fields ) {
		foreach ( $fields as $i => $field ) {
			if ( 'braintree-credit-cards' == $field['value'] ) {
				unset( $fields[ $i ] );
			}
		}
		return $fields;
	}
}