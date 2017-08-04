<?php
class WPSC_Payment_Gateway_Braintree_PayPal extends WPSC_Payment_Gateway {

	function __construct() {
		parent::__construct();

		$this->title            = __( 'PayPal powered by Braintree - PayPal', 'wpsc_authorize_net' );
		$this->supports         = array( 'default_credit_card_form', 'tokenization', 'tev1' );
		$this->sandbox          = $this->setting->get( 'sandbox' ) == '1' ? true : false;
		$this->but_size         = $this->setting->get( 'but_size' ) !== null ? $this->setting->get( 'but_size' ) : $this->setting->set( 'but_size', 'responsive' );
		$this->but_colour       = $this->setting->get( 'but_colour' ) !== null ? $this->setting->get( 'but_colour' ) : $this->setting->set( 'but_colour', 'gold' );
		$this->but_shape        = $this->setting->get( 'but_shape' ) !== null ? $this->setting->get( 'but_shape' ) : $this->setting->set( 'but_shape', 'pill' );
	}

	public function init() {
		parent::init();

		// Tev1 fields
		add_filter( 'wpsc_tev1_default_credit_card_form_fields', array( $this, 'tev1_checkout_fields'), 99, 2 );
		// Tev2 fields
		add_filter( 'wpsc_default_credit_card_form_fields', array( $this, 'tev2_checkout_fields' ), 90, 2 );
	}

	public function tev2_checkout_fields( $fields, $name ) {
		$fields = array();
		$gat_name = str_replace( '_', '-', $this->setting->gateway_name );

		if ( $name != $gat_name ) {
			return $fields;
		}

		$fields = array(
			'bt-pp-button' => '<p class="wpsc-form-row wpsc-form-row-wide wpsc-bt-pp-but-field">
				<label for="' . esc_attr( $gat_name ) . '-bt-pp-but">' . __( 'Click below to continue to PayPal', 'wp-e-commerce' ) . '</label>
				<div id="pp_braintree_pp_button"></div>
			</p>'
		);

		return $fields;
	}

	public function tev1_checkout_fields( $fields, $name ) {
		$fields = array();
		$gat_name = str_replace( '_', '-', $this->setting->gateway_name );

		if ( $name != $gat_name ) {
			return $fields;
		}

		$fields = array(
			'bt-pp-button' => '<tr><td><p class="wpsc-form-row wpsc-form-row-wide wpsc-bt-pp-but-field">
				<label for="' . esc_attr( $gat_name ) . '-bt-pp-but">' . __( 'Click below to continue to PayPal', 'wp-e-commerce' ) . '</label></td></tr>
				<tr><td><div id="pp_braintree_pp_button"></div></td></tr>'
		);

		return $fields;

	}

	/**
	 * submit method, sends the received data to the payment gateway
	 * @access public
	 */
	public function process() {
		global $braintree_settings;

		WPEC_Btree_Helpers::setBraintreeConfiguration();

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
		if ( WPEC_Btree_Helpers::bt_auth_can_connect() && WPEC_Btree_Helpers::bt_auth_is_connected() ) {
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
	public function setup_form() {
		echo WPEC_Btree_Helpers::show_connect_button();
	?>
		<!-- Account Credentials -->
		<tr>
			<td colspan="2">
				<h4><?php _e( 'Account Credentials', 'wpsc_authorize_net' ); ?></h4>
			</td>
		</tr>
		<tr>
			<td>
				<label for="wpsc-worldpay-secure-net-id"><?php _e( 'Public Key', 'wpsc_authorize_net' ); ?></label>
			</td>
			<td>
				<input type="text" name="<?php echo esc_attr( $this->setting->get_field_name( 'public_key' ) ); ?>" value="<?php echo esc_attr( $this->setting->get( 'public_key' ) ); ?>" id="wpsc-anet-api-id" />
			</td>
		</tr>
		<tr>
			<td>
				<label for="wpsc-worldpay-secure-key"><?php _e( 'Private Key', 'wpsc_authorize_net' ); ?></label>
			</td>
			<td>
				<input type="text" name="<?php echo esc_attr( $this->setting->get_field_name( 'private_key' ) ); ?>" value="<?php echo esc_attr( $this->setting->get( 'private_key' ) ); ?>" id="wpsc-anet-trans-key" />
			</td>
		</tr>
		<tr>
			<td colspan="2">
				<h4><?php _e( 'Gateway Settings', 'wpsc_authorize_net' ); ?></h4>
			</td>
		</tr>	
		<tr>
			<td>
				<label for="wpsc-worldpay-secure-key"><?php _e( 'Button Size', 'wpsc_authorize_net' ); ?></label>
			</td>
			<td>
				<select id="wpsc-worldpay-payment-capture" name="<?php echo esc_attr( $this->setting->get_field_name( 'but_size' ) ); ?>">
					<option value='small' <?php selected( 'small', $this->setting->get( 'but_size' ) ); ?>><?php _e( 'Small', 'wpec-square' )?></option>
					<option value='medium' <?php selected( 'medium', $this->setting->get( 'but_size' ) ); ?>><?php _e( 'Medium', 'wpec-square' )?></option>
					<option value='responsive' <?php selected( 'responsive', $this->setting->get( 'but_size' ) ); ?>><?php _e( 'Responsive', 'wpec-square' )?></option>
				</select>
			</td>
		</tr>	
		<tr>
			<td>
				<label for="wpsc-worldpay-secure-key"><?php _e( 'Button Colour', 'wpsc_authorize_net' ); ?></label>
			</td>
			<td>
				<select id="wpsc-worldpay-payment-capture" name="<?php echo esc_attr( $this->setting->get_field_name( 'but_colour' ) ); ?>">
					<option value='gold' <?php selected( 'gold', $this->setting->get( 'but_colour' ) ); ?>><?php _e( 'Gold', 'wpec-square' )?></option>
					<option value='blue' <?php selected( 'blue', $this->setting->get( 'but_colour' ) ); ?>><?php _e( 'Blue', 'wpec-square' )?></option>
					<option value='silver' <?php selected( 'silver', $this->setting->get( 'but_colour' ) ); ?>><?php _e( 'Silver', 'wpec-square' )?></option>
				</select>
			</td>
		</tr>		
		<tr>
			<td>
				<label for="wpsc-worldpay-secure-key"><?php _e( 'Button Shape', 'wpsc_authorize_net' ); ?></label>
			</td>
			<td>
				<select id="wpsc-worldpay-payment-capture" name="<?php echo esc_attr( $this->setting->get_field_name( 'but_shape' ) ); ?>">
					<option value='pill' <?php selected( 'pill', $this->setting->get( 'but_shape' ) ); ?>><?php _e( 'Pill', 'wpec-square' )?></option>
					<option value='rect' <?php selected( 'rect', $this->setting->get( 'but_shape' ) ); ?>><?php _e( 'Rect', 'wpec-square' )?></option>
				</select>
			</td>
		</tr>
		<tr>
			<td>
				<label><?php _e( 'Sandbox Mode', 'wpsc_authorize_net' ); ?></label>
			</td>
			<td>
				<label><input <?php checked( $this->setting->get( 'sandbox' ) ); ?> type="radio" name="<?php echo esc_attr( $this->setting->get_field_name( 'sandbox' ) ); ?>" value="1" /> <?php _e( 'Yes', 'wpsc_authorize_net' ); ?></label>&nbsp;&nbsp;&nbsp;
				<label><input <?php checked( (bool) $this->setting->get( 'sandbox' ), false ); ?> type="radio" name="<?php echo esc_attr( $this->setting->get_field_name( 'sandbox' ) ); ?>" value="0" /> <?php _e( 'No', 'wpsc_authorize_net' ); ?></label>
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
}