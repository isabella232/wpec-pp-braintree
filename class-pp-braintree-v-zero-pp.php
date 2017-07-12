<?php
class wpsc_merchant_braintree_v_zero_pp extends wpsc_merchant_braintree_v_zero {

	function __construct( $purchase_id = null, $is_receiving = false ) {

		parent::__construct( $purchase_id, $is_receiving );
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
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

		//echo "DEBUG :: "."payment_method_nonce = ".$payment_method_nonce."<br />";
		if ($braintree_settings['settlement_type'] == 'upfront') {
			$submit_for_settlement = true;
		} else {
			$submit_for_settlement = false;
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
				]
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

	/**
	 * Creates the Braintree PayPal configuration form in the admin section
	 * @return string
	 */
	public static function form_braintree_v_zero_pp() {
		$output = self::show_connect_button();

		$output .= '<tr>
						<td colspan="2">
							<h4>PayPal Payments</h4>
						</td>
					</tr>
					<tr>
						<td>
							<label>Enable PayPal</label>
						</td>
						<td>
							<label><input ' . checked( get_option( 'bt_vzero_pp_payments' ), true, false ) . ' type="radio" name="wpsc_options[bt_vzero_pp_payments]" value="1" /> Yes</label>&nbsp;&nbsp;&nbsp;
							<label><input ' . checked( (bool) get_option( 'bt_vzero_pp_payments' ), false, false ) . ' type="radio" name="wpsc_options[bt_vzero_pp_payments]" value="0" /> No</label>
						</td>
					</tr>
					<tr>
						<td>
							Sandbox Mode
						</td>
						<td>
							<label><input ' . checked( 'on', get_option( 'braintree_pp_sandbox_mode' ), false ) . ' type="radio" name="wpsc_options[braintree_pp_sandbox_mode]" value="on" /> Yes</label>&nbsp;&nbsp;&nbsp;
							<label><input ' . checked( 'off', get_option( 'braintree_pp_sandbox_mode' ), false ) . ' type="radio" name="wpsc_options[braintree_pp_sandbox_mode]" value="off" /> No</label>
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
					</tr>
					<tr id"vzero-pp-button-style">
						<tr>
							<td>
								Button Size:
							</td>
							<td>
								<select name="wpsc_options[bt_vzero_pp_payments_but_size]">
									<option value="small" ' . selected( get_option( 'bt_vzero_pp_payments_but_size' ), 'small', false ) . '>Small</option>
									<option value="medium" ' . selected( get_option( 'bt_vzero_pp_payments_but_size' ), 'medium', false ) . '>Medium</option>
									<option value="responsive" ' . selected( get_option( 'bt_vzero_pp_payments_but_size' ), 'responsive', false ) . '>Responsive</option>
								</select>
							</td>
						</tr>
						<tr>
							<td>
								Button Colour:
							</td>
							<td>
								<select name="wpsc_options[bt_vzero_pp_payments_but_colour]">
									<option value="gold" ' . selected( get_option( 'bt_vzero_pp_payments_but_colour' ), 'gold', false ) . '>Gold</option>
									<option value="blue" ' . selected( get_option( 'bt_vzero_pp_payments_but_colour' ), 'blue', false ) . '>Blue</option>
									<option value="silver" ' . selected( get_option( 'bt_vzero_pp_payments_but_colour' ), 'silver', false ) . '>Silver</option>
								</select>
							</td>
						</tr>
						<tr>
							<td>
								Button Shape:
							</td>
							<td>
								<select name="wpsc_options[bt_vzero_pp_payments_but_shape]">
									<option value="pill" ' . selected( get_option( 'bt_vzero_pp_payments_but_shape' ), 'pill', false ) . '>Pill</option>
									<option value="rect" ' . selected( get_option( 'bt_vzero_pp_payments_but_shape' ), 'rect', false ) . '>Rect</option>
								</select>
							</td>
						</tr>
						</tr>
					</tr>';
		return $output;
	}
}