<?php
class wpsc_merchant_braintree_v_zero_cc extends wpsc_merchant_braintree_v_zero {

	public function __construct( $purchase_id = null, $is_receiving = false ) {
		parent::__construct( $purchase_id, $is_receiving );
		
	}

	/**
	 * submit method, sends the received data to the payment gateway
	 * @access public
	 */
	public function submit() {
		global $braintree_settings;

		self::setBraintreeConfiguration();

		$paymentAmount = $this->cart_data['total_price'];

		$session_id = $this->cart_data['session_id'];
		$email_address = $this->cart_data['email_address'];
		$billing_address = $this->cart_data['billing_address'];

		$is_same_billing_address = false;

		// Check if opted to use billing address for shipping and
		// handle accordingly
		if ( isset( $_POST['shippingSameBilling'] ) ) {
			if ( $_POST['shippingSameBilling'] == true ) {
				$is_same_billing_address = true;
			}
		}

		if ( $is_same_billing_address == true ) {
			$shipping_address = $this->cart_data['shipping_address'];
		} else {
			$shipping_address = $this->cart_data['billing_address'];
		}

		$payment_method_nonce = $_POST['pp_btree_method_nonce'];
		$kount_fraud = isset( $_POST['pp_btree_card_kount'] ) ? strip_tags( trim ( $_POST['pp_btree_card_kount'] ) ) : '';

		//echo "DEBUG :: "."payment_method_nonce = ".$payment_method_nonce."<br />";
		if ( $braintree_settings['settlement_type'] == 'upfront' ) {
			$submit_for_settlement = true;
		} else {
			$submit_for_settlement = false;
		}

		// Check 3DS transaction.
		$threedcheck = true;
		$braintree_threedee_secure = get_option( 'braintree_threedee_secure' );
		$force3ds = false;
		if ( 'on' == $braintree_threedee_secure ) {
			$force3ds = true;
			$threedcheck = $this->check_3ds_risk_transaction( $payment_method_nonce );
		}

		if ( ! $threedcheck ) {
			// 3DS check failed so return;
			$purchase_log = new WPSC_Purchase_Log( $session_id, 'sessionid' );
			$purchase_log->set( array(
				'processed' => WPSC_Purchase_Log::INCOMPLETE_SALE,
				'notes' => '3D Secure verification failed!',
			) );
			$purchase_log->save();

			$error_messages = wpsc_get_customer_meta( 'checkout_misc_error_messages' );

			if ( ! is_array( $error_messages ) ) {
				$error_messages = array();
			}

			$error_messages[] = '<strong style="color:red">3D Secure verification failed!</strong>';
			wpsc_update_customer_meta( 'checkout_misc_error_messages', $error_messages );

			$this->return_to_checkout();
		}

		//Submit using $gateway(for auth users)
		if ( self::bt_auth_can_connect() && self::bt_auth_is_connected() ) {
			$acc_token = get_option( 'wpec_braintree_auth_access_token' );

			$gateway = new Braintree_Gateway( array(
				'accessToken' => $acc_token,
			));

			$result = $gateway->transaction()->sale([
				"amount" => $paymentAmount,
				"paymentMethodNonce" => $payment_method_nonce,
				"channel" => "WPec_Cart_PPpbBT",
				"orderId" => $session_id,
				"customer" => [
					"firstName" => $billing_address['first_name'],
					"lastName" => $billing_address['last_name'],
					"phone" => $billing_address['phone'],
					"email" => $email_address
				],
				"billing" => [
					"firstName" => $billing_address['first_name'],
					"lastName" => $billing_address['last_name'],
					"streetAddress" => $billing_address['address'],
					"locality" => $billing_address['city'],
					"region" => wpsc_get_state_by_id( wpsc_get_customer_meta( '_wpsc_cart.billing_region' ), 'code' ),
					"postalCode" => $billing_address['post_code'],
					"countryCodeAlpha2" => $billing_address['country']
				],
				"shipping" => [
					"firstName" => $shipping_address['first_name'],
					"lastName" => $shipping_address['last_name'],
					"streetAddress" => $shipping_address['address'],
					"locality" => $shipping_address['city'],
					"region" => wpsc_get_state_by_id( wpsc_get_customer_meta( '_wpsc_cart.delivery_region' ), 'code' ),
					"postalCode" => $shipping_address['post_code'],
					"countryCodeAlpha2" => $shipping_address['country']
				],				
				"options" => [
					"submitForSettlement" => $submit_for_settlement,
					"threeDSecure" => [
						"required" => $force3ds,
					]
				],
				"deviceData" => $kount_fraud,
			]);
			
			// In theory all error handling should be done on the client side...?
			if ($result->success) {
				// Payment complete
				wpsc_update_purchase_log_details( $session_id, array( 'processed' => WPSC_Purchase_Log::ACCEPTED_PAYMENT, 'transactid' => $result->transaction->id ), 'sessionid' );

				$this->go_to_transaction_results( $session_id );
			} else {
				if ($result->transaction) {
					wpsc_update_purchase_log_details( $session_id, array( 'processed' => WPSC_Purchase_Log::ORDER_RECEIVED, 'transactid' => $result->transaction->id ), 'sessionid' );

					$this->go_to_transaction_results( $session_id );
				} else {
					$error = $result->message;

					$this->set_error_message( "Payment Error: ".$error );

					$this->return_to_checkout();
				}
			}
		}

		// Create a sale transaction with Braintree
		$result = Braintree_Transaction::sale(array(
			"amount" => $paymentAmount,
			"orderId" => $session_id,
			"paymentMethodNonce" => $payment_method_nonce,
			"customer" => [
				"firstName" => $billing_address['first_name'],
				"lastName" => $billing_address['last_name'],
				"phone" => $billing_address['phone'],
				"email" => $email_address
			],
			"billing" => [
				"firstName" => $billing_address['first_name'],
				"lastName" => $billing_address['last_name'],
				"streetAddress" => $billing_address['address'],
				"locality" => $billing_address['city'],
				"region" => wpsc_get_state_by_id( wpsc_get_customer_meta( '_wpsc_cart.billing_region' ), 'code' ),
				"postalCode" => $billing_address['post_code'],
				"countryCodeAlpha2" => $billing_address['country']
			],
			"shipping" => [
				"firstName" => $shipping_address['first_name'],
				"lastName" => $shipping_address['last_name'],
				"streetAddress" => $shipping_address['address'],
				"locality" => $shipping_address['city'],
				"region" => wpsc_get_state_by_id( wpsc_get_customer_meta( '_wpsc_cart.delivery_region' ), 'code' ),
				"postalCode" => $shipping_address['post_code'],
				"countryCodeAlpha2" => $shipping_address['country']
			],
			"options" => [
				"submitForSettlement" => $submit_for_settlement,
				"threeDSecure" => [
					"required" => $force3ds
				]
			]
		));

		// In theory all error handling should be done on the client side...?
		if ($result->success) {
			// Payment complete
			wpsc_update_purchase_log_details( $session_id, array( 'processed' => WPSC_Purchase_Log::ACCEPTED_PAYMENT, 'transactid' => $result->transaction->id ), 'sessionid' );

	 		$this->go_to_transaction_results( $session_id );
		} else {
			if ($result->transaction) {
				wpsc_update_purchase_log_details( $session_id, array( 'processed' => WPSC_Purchase_Log::ORDER_RECEIVED, 'transactid' => $result->transaction->id ), 'sessionid' );

	 			$this->go_to_transaction_results( $session_id );
			} else {
				$error = $result->message;

				$this->set_error_message( "Payment Error: ".$error );

				$this->return_to_checkout();
			}
		}

	 	exit();
	}

	public function check_3ds_risk_transaction( $nonce ) {
		$pp_3ds_risk = get_option( 'bt_vzero_threedee_secure_risk' ) != false ? get_option( 'bt_vzero_threedee_secure_risk' ) : 'standard' ;
		$auth_3ds = false;

		if ( self::bt_auth_can_connect() && self::bt_auth_is_connected() ) {
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

		return apply_filters( 'wpsc_braintree_3ds_pass_or_fail', $matrix[ $level ][ $info->status ], $level );
	}

	/**
	 * Creates the Braintree Credit Cards configuration form in the admin section
	 * @return string
	 */
	public static function form_braintree_v_zero_cc() {
		$output = self::show_connect_button();

		$output .= '<div id="cc_manual_connection_api"><tr>
						<td colspan="2">
							<h4>Credit Card Payments</h4>
						</td>
					</tr>
					<tr>
						<td>
							Sandbox Mode
						</td>
						<td>
							<label><input ' . checked( 'on', get_option( 'braintree_sandbox_mode' ), false ) . ' type="radio" name="wpsc_options[braintree_sandbox_mode]" value="on" /> Yes</label>&nbsp;&nbsp;&nbsp;
							<label><input ' . checked( 'off', get_option( 'braintree_sandbox_mode' ), false ) . ' type="radio" name="wpsc_options[braintree_sandbox_mode]" value="off" /> No</label>
						</td>
					</tr>
					<tr>
						<td colspan="2">
							<h4>Sandbox Settings</h4>
						</td>
					</tr>
					<tr>
						<td>
							Sandbox Public Key
						</td>
						<td>
							<input class="braintree-error" id="braintree_sandbox_public_key" type="text" name="wpsc_options[braintree_sandbox_public_key]" value="' . get_option( 'braintree_sandbox_public_key' ) . '" />
							<span id="braintree_sandbox_public_key_errors" style="color: red"></span>
						</td>
					</tr>
					<tr>
						<td>
							Sandbox Private Key
						</td>
						<td>
							<input id="braintree_sandbox_private_key" type="text" name="wpsc_options[braintree_sandbox_private_key]" value="' . get_option( 'braintree_sandbox_private_key' ) . '" />
							<span id="braintree_sandbox_private_key_errors" style="color: red"></span>
						</td>
					</tr>
					<tr>
						<td>
							Sandbox Merchant ID
						</td>
						<td id="sandboxMerchantIds">
							<input id="braintree_sandbox_merchant_id" type="text" name="wpsc_options[braintree_sandbox_merchant_id]" value="' . get_option( 'braintree_sandbox_merchant_id' ) . '" />
							<select id="braintree_merchant_currency" name="wpsc_options[braintree_sandbox_merchant_currency]">
								<option value=""></option>';
									$merchant_currencies = self::getMerchantCurrencies();
									foreach ($merchant_currencies as $merchant_currency) {
										$output .= '<option value="'.$merchant_currency['currency'].'"';
										if (get_option( 'braintree_sandbox_merchant_currency' ) === $merchant_currency['currency']) {
											$output .= ' selected';
										}
										$output .= '>'.$merchant_currency['currency'].' - '.$merchant_currency['currency_label'].'</option>';
									}
									$output .= '
							</select>
							<span id="braintree_sandbox_merchant_id_errors" style="color: red"></span>
							<span id="braintree_merchant_currency_errors" style="color: red"></span>
						</td>
					</tr>
					<tr>
						<td colspan="2">
							<h4>Production Settings</h4>
						</td>
					</tr>
					<tr>
						<td>
							Production Public Key
						</td>
						<td>
							<input id="braintree_production_public_key" type="text" name="wpsc_options[braintree_production_public_key]" value="' . get_option( 'braintree_production_public_key' ) . '" />
							<span id="braintree_production_public_key_errors" style="color: red"></span>
						</td>
					</tr>
					<tr>
						<td>
							Production Private Key
						</td>
						<td>
							<input id="braintree_production_private_key" type="text" name="wpsc_options[braintree_production_private_key]" value="' . get_option( 'braintree_production_private_key' ) . '" />
							<span id="braintree_production_private_key_errors" style="color: red"></span>
						</td>
					</tr>
					<tr>
						<td>
							Production Merchant ID
						</td>
						<td id="productionMerchantIds">
							<input id="braintree_production_merchant_id" type="text" name="wpsc_options[braintree_production_merchant_id]" value="' . get_option( 'braintree_production_merchant_id' ) . '" />
							<select id="braintree_merchant_currency" name="wpsc_options[braintree_production_merchant_currency]">
								<option value=""></option>';
						$merchant_currencies = self::getMerchantCurrencies();
						foreach ($merchant_currencies as $merchant_currency) {
							$output .= '<option value="'.$merchant_currency['currency'].'"';
							if (get_option( 'braintree_sandbox_merchant_currency' ) === $merchant_currency['currency']) {
								$output .= ' selected';
							}
							$output .= '>'.$merchant_currency['currency'].' - '.$merchant_currency['currency_label'].'</option>';
						}
						$output .= '
							</select>
							<span id="braintree_production_merchant_id_errors" style="color: red"></span>
							<span id="braintree_merchant_currency_errors" style="color: red"></span>
						</td>
					</tr></div>
					<tr>
						<td>
							Settlement Type
						</td>
						<td>
							<select id="braintree_settlement_type" name="wpsc_options[braintree_settlement_type]">
								<option value="upfront"';
									if (get_option( 'braintree_settlement_type' ) === 'upfront')
										$output .= ' selected';
									$output .=		'>Upfront Settlement</option>
													<option value="deferred"';
									if (get_option( 'braintree_settlement_type' ) === 'deferred')
										$output .= ' selected';
									$output .=		'>Deferred Settlement</option>
							</select>
						</td>
					</tr>
					</tr>
					<tr>
						<td colspan="2">
							<label><h4>3D Secure Settings</h4></label>
						</td>
					</tr>
					<tr>
						<td>
							3-D Secure
						</td>
						<td>
							<label><input ' . checked( 'on', get_option( 'braintree_threedee_secure' ), false ) . ' type="radio" name="wpsc_options[braintree_threedee_secure]" value="on" /> Yes</label>&nbsp;&nbsp;&nbsp;
							<label><input ' . checked( 'off', get_option( 'braintree_threedee_secure' ), false ) . ' type="radio" name="wpsc_options[braintree_threedee_secure]" value="off" /> No</label>&nbsp;&nbsp;&nbsp;
							' . __( 'Checking this option processes card transactions through the 3-DS verification protocol.', 'wp-e-commerce' ).'
						</td>
					</tr>
					<tr>
						<td>
							3-D Secure Only
						</td>
						<td>
							<label><input ' . checked( 'on', get_option( 'braintree_threedee_secure_only' ), false ) . ' type="radio" name="wpsc_options[braintree_threedee_secure_only]" value="on" /> Yes</label>&nbsp;&nbsp;&nbsp;
							<label><input ' . checked( 'off', get_option( 'braintree_threedee_secure_only' ), false ) . ' type="radio" name="wpsc_options[braintree_threedee_secure_only]" value="off" /> No</label>&nbsp;&nbsp;&nbsp;
							' . __( 'Checking this option will only accept card transactions if compatible with the 3-DS verification protocol.', 'wp-e-commerce' ).'
						</td>
					</tr>
					<tr>
						<td>
							3-D Secure Risk Settings:
						</td>
						<td>
							<select name="wpsc_options[bt_vzero_threedee_secure_risk]">
								<option value="standard" ' . selected( get_option( 'bt_vzero_threedee_secure_risk' ), 'standard', false ) . '>Standard</option>
								<option value="strict" ' . selected( get_option( 'bt_vzero_threedee_secure_risk' ), 'strict', false ) . '>Strict</option>
							</select>
						</td>
					</tr>';
		return $output;
	}
}
