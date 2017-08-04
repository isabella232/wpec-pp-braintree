<?php
class WPSC_Payment_Gateway_Braintree_Credit_Cards extends WPSC_Payment_Gateway {
	
	public function __construct() {
		parent::__construct();
		$this->title            = __( 'PayPal powered by Braintree - Cards', 'wpsc_authorize_net' );
		$this->supports         = array( 'default_credit_card_form', 'tokenization', 'tev1' );
		$this->sandbox          = $this->setting->get( 'sandbox' ) == '1' ? true : false;
		$this->payment_capture 	= $this->setting->get( 'payment_capture' ) !== null ? $this->setting->get( 'payment_capture' ) : 'standard';
		// Define user set variables
	}

	public function init() {
		parent::init();
		
		// Tev1 fields
		add_action( 'wpsc_tev1_default_credit_card_form_fields', array( $this, 'tev1_checkout_fields'), 90, 2 );
		add_action( 'wpsc_tev1_default_credit_card_form_end', array( $this, 'tev1_checkout_fields_extra') );
		// Tev2 fields
		add_filter( 'wpsc_default_credit_card_form_fields', array( $this, 'tev2_checkout_fields' ), 99, 2 );
		add_action( 'wpsc_default_credit_card_form_end', array( $this, 'tev2_checkout_fields_extra' ) );
	}

	public function tev2_checkout_fields( $fields, $name ) {
		$gat_name = str_replace( '_', '-', $this->setting->gateway_name );

		if ( $name != $gat_name ) {
			return $fields;
		}

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
		$gat_name = str_replace( '_', '-', $this->setting->gateway_name );

		if ( $name != $gat_name ) {
			return;
		}

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
		$gat_name = str_replace( '_', '-', $this->setting->gateway_name );

		if ( $name != $gat_name ) {
			return $fields;
		}

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
		$gat_name = str_replace( '_', '-', $this->setting->gateway_name );

		if ( $name != $gat_name ) {
			return;
		}
		
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
		
	}
	
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
				<h4><?php _e( 'Transaction Settings', 'wpsc_authorize_net' ); ?></h4>
			</td>
		</tr>
		<tr>
			<td>
				<label for="wpsc-worldpay-secure-key"><?php _e( 'Settlement Type', 'wpsc_authorize_net' ); ?></label>
			</td>
			<td>
				<select id="wpsc-worldpay-payment-capture" name="<?php echo esc_attr( $this->setting->get_field_name( 'settlement' ) ); ?>">
					<option value='upfront' <?php selected( 'upfront', $this->setting->get( 'settlement' ) ); ?>><?php _e( 'Upfront Settlement.', 'wpec-square' )?></option>
					<option value='deferred' <?php selected( 'deferred', $this->setting->get( 'settlement' ) ); ?>><?php _e( 'Deferred Settlement.', 'wpec-square' )?></option>
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
					<option value='standard' <?php selected( 'standard', $this->setting->get( 'three_d_secure_risk' ) ); ?>><?php _e( 'Authorize and capture the payment when the order is placed.', 'wpec-square' )?></option>
					<option value='strict' <?php selected( 'strict', $this->setting->get( 'three_d_secure_risk' ) ); ?>><?php _e( 'Authorize the payment when the order is placed.', 'wpec-square' )?></option>
				</select>
			</td>
		</tr>
		<tr>
			<td>
				<label><?php _e( 'Sandbox Mode', 'wpsc_authorize_net' ); ?></label>
			</td>
			<td>
				<label><input <?php checked( $this->setting->get( 'sandbox_cc' ) ); ?> type="radio" name="<?php echo esc_attr( $this->setting->get_field_name( 'sandbox_cc' ) ); ?>" value="1" /> <?php _e( 'Yes', 'wpsc_authorize_net' ); ?></label>&nbsp;&nbsp;&nbsp;
				<label><input <?php checked( (bool) $this->setting->get( 'sandbox_cc' ), false ); ?> type="radio" name="<?php echo esc_attr( $this->setting->get_field_name( 'sandbox_cc' ) ); ?>" value="0" /> <?php _e( 'No', 'wpsc_authorize_net' ); ?></label>
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