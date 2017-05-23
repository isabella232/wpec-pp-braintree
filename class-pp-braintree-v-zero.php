<?php
/**
 * Custom Exception for when an issue occurs with a Braintree transaction
 */
class BraintreeTransactionException extends Exception {
	// No need to implement anything here
}

class wpsc_merchant_braintree_v_zero extends wpsc_merchant {

	var $name = '';

	function __construct( $purchase_id = null, $is_receiving = false ) {
		
		$this->name = __( 'Braintree V.Zero', 'wp-e-commerce' );
		
		parent::__construct( $purchase_id, $is_receiving );
		
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
	}

	/**
	 * submit method, sends the received data to the payment gateway
	 * @access public
	 */
	function submit() {

		setBraintreeConfiguration();

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
		
		$payment_method_nonce = $_POST['payment_method_nonce'];
		
		//echo "DEBUG :: "."payment_method_nonce = ".$payment_method_nonce."<br />";
		
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
				"region" => $billing_address['state'],
				"postalCode" => $billing_address['post_code'],
				"countryCodeAlpha2" => $billing_address['country']
			],
			"shipping" => [
				"firstName" => $shipping_address['first_name'],
				"lastName" => $shipping_address['last_name'],
				"streetAddress" => $shipping_address['address'],
				"locality" => $shipping_address['city'],
				"region" => $shipping_address['state'],
				"postalCode" => $shipping_address['post_code'],
				"countryCodeAlpha2" => $shipping_address['country']
			],
			"options" => [
				"submitForSettlement" => true,
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
}

/**
 * Creates the Briantree configuration form in the admin section 
 * @return string
 */
function form_braintree_v_zero() {
	
	$output = '
						<tr>
							<td>
								Sandbox Mode
							</td>
							<td>
								<input id="braintree_sandbox_mode_check" type="checkbox"';
								if (get_option( 'braintree_sandbox_mode' ) == 'on') { 
									$output .= ' checked="checked"';
								}
	$output .=					' />
								' . __( 'Check to run the plugin in sandbox mode.', 'wp-e-commerce' ).'
								<input id="braintree_sandbox_mode" type="hidden" name="wpsc_options[braintree_sandbox_mode]" value="on">
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
		
		$merchant_currencies = getMerchantCurrencies();
		
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
		
		$merchant_currencies = getMerchantCurrencies();
		
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
						<tr>
							<td colspan="2">
								<h4>Card Payments</h4>
							</td>
						</tr>
						<tr>
							<td>
								Display As
							</td>
							<td>
								<input id="braintree_card_payments_display_as_hosted_fields" class="braintree_card_payments_display_as" type="radio" name="wpsc_options[braintree_card_payments_display_as]" value="hosted_fields"';
								if (get_option( 'braintree_card_payments_display_as' ) == 'hosted_fields') { 
									$output .= ' checked="checked"';
								} elseif (get_option( 'braintree_card_payments_display_as' ) == '') { 
									$output .= ' checked="checked"';
								}
	$output .=					' />
								Hosted Fields
							</td>
						</tr>
						<tr id="hostedFieldsOptions">
							<td>
								' . __( 'Hosted Fields Options', 'wp-e-commerce' ) . '
							</td>
							<td>
								<input id="braintree_hosted_fields_options" type="radio" name="wpsc_options[braintree_hosted_fields_options]" value="combined_exp_date"';
								if (get_option( 'braintree_hosted_fields_options' ) == 'combined_exp_date') { 
									$output .= ' checked="checked"';
								}
	$output .=					' />
								Combined Expiry Date Field
								<br />
								<input id="braintree_hosted_fields_options" type="radio" name="wpsc_options[braintree_hosted_fields_options]" value="separate_exp_date"';
								if (get_option( 'braintree_hosted_fields_options' ) == 'separate_exp_date') { 
									$output .= ' checked="checked"';
								} elseif (get_option( 'braintree_hosted_fields_options' ) == '') { 
									$output .= ' checked="checked"';
								}
	$output .=					' />
								Separete Expiry Date Fields
							</td>
						</tr>
						<tr id="hostedFieldsCss">
							<td>
								Hosted Fields CSS
							</td>
							<td>
								If you want to customise the styling of your hosted fields then add the CSS here. The different HTML elements and their IDs are as follows:
								<br />
								<ul>
									<li>
										Card Number: #card-number
									</li>
									<li>
										Card Expiry Date (Combined Date Fields): #card-exp
									</li>
									<li>
										Card Expiry Month (Separate Date Fields): #card-exp-month
									</li>
									<li>
										Card Expiry Year (Separate Date Fields): #card-exp-year
									</li>
									<li>
										Card CVV: #card-cvv
									</li>
									<li>
										Submit button: #submitHostedFields
									</li>
								</ul>
								<br />
								<textarea id="braintree_hosted_fields_css" name="wpsc_options[braintree_hosted_fields_css]">' . get_option( 'braintree_hosted_fields_css' ) . '</textarea>
							</td>
						</tr>
						<tr>
							<td>
								3-D Secure
							</td>
							<td>
								<input id="braintree_threedee_secure_check" type="checkbox"';
								if (get_option( 'braintree_threedee_secure' ) == 'on') { 
									$output .= ' checked="checked"';
								}
	$output .=					' />
								' . __( 'Checking this option processes card transactions through the 3-DS verification protocol.', 'wp-e-commerce' ).'
								<input id="braintree_threedee_secure" type="hidden" name="wpsc_options[braintree_threedee_secure]" value="' . get_option( 'braintree_threedee_secure' ) .'">
							</td>
						</tr>
						<tr>
							<td>
								3-D Secure Only
							</td>
							<td>
								<input id="braintree_threedee_secure_only_check" type="checkbox"';
								if (get_option( 'braintree_threedee_secure_only' ) == 'on') { 
									$output .= ' checked="checked"';
								}
	$output .=					' />
								' . __( 'Checking this option will only accept card transactions if compatible with the 3-DS verification protocol.', 'wp-e-commerce' ).'
								<input id="braintree_threedee_secure_only" type="hidden" name="wpsc_options[braintree_threedee_secure_only]" value="' . get_option( 'braintree_threedee_secure_only' ) .'">
							</td>
						</tr>
						<tr>
							<td colspan="2">
								<h4>PayPal Payments</h4>
							</td>
						</tr>
						<tr>
							<td>
								Display As
							</td>
							<td>
								<input id="braintree_paypal_payments_display_as" type="radio" name="wpsc_options[braintree_paypal_payments_display_as]" value="drop_in_ui"';
								if (get_option( 'braintree_paypal_payments_display_as' ) == 'drop_in_ui') { 
									$output .= ' checked="checked"';
								} elseif (get_option( 'braintree_paypal_payments_display_as' ) == '') { 
									$output .= ' checked="checked"';
								} else { 
									$output .= ' checked="checked"';
								}
	$output .=					' />
								Drop-in UI';
	/*										
								<br />
								<input id="braintree_paypal_payments_display_as" type="radio" name="wpsc_options[braintree_paypal_payments_display_as]" value="custom"';
								if (get_option( 'braintree_paypal_payments_display_as' ) == 'custom') { 
									$output .= ' checked="checked"';
								}
	$output .=					' />
								Custom
	*/
	$output .= '
							</td>
						</tr>
						<script type="text/javascript">
								
							jQuery(function() {
			
								jQuery(".edit-payment-module-update").click(function(e) {
									
									var is_errors = false;
									
									// Validate Sanbox account details
									if (jQuery("#braintree_sandbox_public_key").val() == "") {
										jQuery("#braintree_sandbox_public_key_errors").html("<br/> You have not entered a Sandbox Public Key.");
										jQuery("#braintree_sandbox_public_key").focus();
										is_errors = true;
									} else {
										jQuery("#braintree_sandbox_public_key_errors").html("");
									}
									
									if (jQuery("#braintree_sandbox_private_key").val() == "") {
										jQuery("#braintree_sandbox_private_key_errors").html("<br/> You have not entered a Sandbox Private Key.");
										jQuery("#braintree_sandbox_private_key").focus();
										is_errors = true;
									} else {
										jQuery("#braintree_sandbox_private_key_errors").html("");
									}
									
									if (jQuery("#braintree_sandbox_merchant_id").val() == "") {
										jQuery("#braintree_sandbox_merchant_id_errors").html("<br/> You have not entered a Sandbox Merchant ID.");
										jQuery("#braintree_sandbox_merchant_id").focus();
										is_errors = true;
									} else {
										jQuery("#braintree_sandbox_merchant_id_errors").html("");
									}
									
									if (jQuery("#braintree_merchant_currency").val() == "") {
										jQuery("#braintree_merchant_currency_errors").html("<br/> You have not selcetd a Sandbox Currency.");
										jQuery("#braintree_merchant_currency").focus();
										is_errors = true;
									} else {
										jQuery("#braintree_merchant_currency_errors").html("");
									}
									
									// Validate Production account details
									if (jQuery("#braintree_production_public_key").val() == "") {
										jQuery("#braintree_production_public_key_errors").html("<br/> You have not entered a Production Public Key.");
										jQuery("#braintree_production_public_key").focus();
										is_errors = true;
									} else {
										jQuery("#braintree_production_public_key_errors").html("");
									}
									
									if (jQuery("#braintree_production_private_key").val() == "") {
										jQuery("#braintree_production_private_key_errors").html("<br/> You have not entered a Production Private Key.");
										jQuery("#braintree_production_private_key").focus();
										is_errors = true;
									} else {
										jQuery("#braintree_production_private_key_errors").html("");
									}
									
									if (jQuery("#braintree_production_merchant_id").val() == "") {
										jQuery("#braintree_production_merchant_id_errors").html("<br/> You have not entered a Production Merchant ID.");
										jQuery("#braintree_production_merchant_id").focus();
										is_errors = true;
									} else {
										jQuery("#braintree_production_merchant_id_errors").html("");
									}
									
									if (jQuery("#braintree_merchant_currency").val() == "") {
										jQuery("#braintree_merchant_currency_errors").html("<br/> You have not selected a Production Currency.");
										jQuery("#braintree_merchant_currency").focus();
										is_errors = true;
									} else {
										jQuery("#braintree_merchant_currency_errors").html("");
									}
									
									if (is_errors == true) {
										e.preventDefault();
									}
								});
										
								jQuery("#braintree_sandbox_mode_check").click(function() {
									if (jQuery("#braintree_sandbox_mode_check").is(":checked")) {
										jQuery("#braintree_sandbox_mode").val("on");
									} else {
										jQuery("#braintree_sandbox_mode").val("off");
									}
								});
										
								jQuery("#braintree_threedee_secure_check").click(function() {
									if (jQuery("#braintree_threedee_secure_check").is(":checked")) {
										jQuery("#braintree_threedee_secure").val("on");
									} else {
										jQuery("#braintree_threedee_secure").val("off");
									}
								});
										
								jQuery("#braintree_threedee_secure_only_check").click(function() {
									if (jQuery("#braintree_threedee_secure_only_check").is(":checked")) {
										jQuery("#braintree_threedee_secure_only").val("on");
									} else {
										jQuery("#braintree_threedee_secure_only").val("off");
									}
								});
								
								jQuery(".braintree_card_payments_display_as").click(function() {
									if (jQuery(this).val() == "hosted_fields") {
										jQuery("#hostedFieldsOptions").show();
										jQuery("#hostedFieldsCss").show();
									} else {
										jQuery("#hostedFieldsOptions").hide();
										jQuery("#hostedFieldsCss").hide();
									}
								});';
		
	if (get_option( 'braintree_card_payments_display_as' ) == 'hosted_fields') {
		$output .= '
								jQuery("#hostedFieldsOptions").show();
								jQuery("#hostedFieldsCss").show();';
	} else {
		$output .= '
								jQuery("#hostedFieldsOptions").hide();
								jQuery("#hostedFieldsCss").hide();';
	}
		
	$output .= '
							});
						</script>';
	
	return $output;
}

/**
 * Returns a list of merchant currencies
 */
function getMerchantCurrencies() {
	
	$merchant_currencies = array();
	
	// These are all the currencies supported by Braintree. Some have been commented out as trying to 
	// load them all really slows down the display of the admin section for Braintree payments
	
	/*
	$merchant_currencies[] = array('currency'=>'AFN','currency_label'=>'Afghan Afghani');
	$merchant_currencies[] = array('currency'=>'ALL','currency_label'=>'Albanian Lek');
	$merchant_currencies[] = array('currency'=>'AMD','currency_label'=>'Armenian Dram');
	$merchant_currencies[] = array('currency'=>'ANG','currency_label'=>'Netherlands Antillean Gulden');
	$merchant_currencies[] = array('currency'=>'AOA','currency_label'=>'Angolan Kwanza');
	$merchant_currencies[] = array('currency'=>'ARS','currency_label'=>'Argentine Peso');
	*/
	$merchant_currencies[] = array('currency'=>'AUD','currency_label'=>'Australian Dollar');
	/*
	$merchant_currencies[] = array('currency'=>'AWG','currency_label'=>'Aruban Florin');
	$merchant_currencies[] = array('currency'=>'AZN','currency_label'=>'Azerbaijani Manat');
	$merchant_currencies[] = array('currency'=>'BAM','currency_label'=>'Bosnia and Herzegovina Convertible Mark');
	$merchant_currencies[] = array('currency'=>'BBD','currency_label'=>'Barbadian Dollar');
	$merchant_currencies[] = array('currency'=>'BDT','currency_label'=>'Bangladeshi Taka');
	$merchant_currencies[] = array('currency'=>'BGN','currency_label'=>'Bulgarian Lev');
	$merchant_currencies[] = array('currency'=>'BHD','currency_label'=>'Bahraini Dinar');
	$merchant_currencies[] = array('currency'=>'BIF','currency_label'=>'Burundian Franc');
	$merchant_currencies[] = array('currency'=>'BMD','currency_label'=>'Bermudian Dollar');
	$merchant_currencies[] = array('currency'=>'BND','currency_label'=>'Brunei Dollar');
	$merchant_currencies[] = array('currency'=>'BOB','currency_label'=>'Bolivian Boliviano');
	$merchant_currencies[] = array('currency'=>'BRL','currency_label'=>'Brazilian Real');
	$merchant_currencies[] = array('currency'=>'BSD','currency_label'=>'Bahamian Dollar');
	$merchant_currencies[] = array('currency'=>'BTN','currency_label'=>'Bhutanese Ngultrum');
	$merchant_currencies[] = array('currency'=>'BWP','currency_label'=>'Botswana Pula');
	$merchant_currencies[] = array('currency'=>'BYR','currency_label'=>'Belarusian Ruble');
	$merchant_currencies[] = array('currency'=>'BZD','currency_label'=>'Belize Dollar');
	*/
	$merchant_currencies[] = array('currency'=>'CAD','currency_label'=>'Canadian Dollar');
	//$merchant_currencies[] = array('currency'=>'CDF','currency_label'=>'Congolese Franc');
	$merchant_currencies[] = array('currency'=>'CHF','currency_label'=>'Swiss Franc');
	//$merchant_currencies[] = array('currency'=>'CLP','currency_label'=>'Chilean Peso');
	$merchant_currencies[] = array('currency'=>'CNY','currency_label'=>'Chinese Renminbi Yuan');
	/*
	$merchant_currencies[] = array('currency'=>'COP','currency_label'=>'Colombian Peso');
	$merchant_currencies[] = array('currency'=>'CRC','currency_label'=>'Costa Rican Colón');
	$merchant_currencies[] = array('currency'=>'CUC','currency_label'=>'Cuban Convertible Peso');
	$merchant_currencies[] = array('currency'=>'CUP','currency_label'=>'Cuban Peso');
	$merchant_currencies[] = array('currency'=>'CVE','currency_label'=>'Cape Verdean Escudo');
	$merchant_currencies[] = array('currency'=>'CZK','currency_label'=>'Czech Koruna');
	$merchant_currencies[] = array('currency'=>'DJF','currency_label'=>'Djiboutian Franc');
	$merchant_currencies[] = array('currency'=>'DKK','currency_label'=>'Danish Krone');
	$merchant_currencies[] = array('currency'=>'DOP','currency_label'=>'Dominican Peso');
	$merchant_currencies[] = array('currency'=>'DZD','currency_label'=>'Algerian Dinar');
	$merchant_currencies[] = array('currency'=>'EEK','currency_label'=>'Estonian Kroon');
	$merchant_currencies[] = array('currency'=>'EGP','currency_label'=>'Egyptian Pound');
	$merchant_currencies[] = array('currency'=>'ERN','currency_label'=>'Eritrean Nakfa');
	$merchant_currencies[] = array('currency'=>'ETB','currency_label'=>'Ethiopian Birr');
	*/
	$merchant_currencies[] = array('currency'=>'EUR','currency_label'=>'Euro');
	//$merchant_currencies[] = array('currency'=>'FJD','currency_label'=>'Fijian Dollar');
	//$merchant_currencies[] = array('currency'=>'FKP','currency_label'=>'Falkland Pound');
	$merchant_currencies[] = array('currency'=>'GBP','currency_label'=>'British Pound');
	/*
	$merchant_currencies[] = array('currency'=>'GEL','currency_label'=>'Georgian Lari');
	$merchant_currencies[] = array('currency'=>'GHS','currency_label'=>'Ghanaian Cedi');
	$merchant_currencies[] = array('currency'=>'GIP','currency_label'=>'Gibraltar Pound');
	$merchant_currencies[] = array('currency'=>'GMD','currency_label'=>'Gambian Dalasi');
	$merchant_currencies[] = array('currency'=>'GNF','currency_label'=>'Guinean Franc');
	$merchant_currencies[] = array('currency'=>'GTQ','currency_label'=>'Guatemalan Quetzal');
	$merchant_currencies[] = array('currency'=>'GYD','currency_label'=>'Guyanese Dollar');
	*/
	$merchant_currencies[] = array('currency'=>'HKD','currency_label'=>'Hong Kong Dollar');
	/*
	$merchant_currencies[] = array('currency'=>'HNL','currency_label'=>'Honduran Lempira');
	$merchant_currencies[] = array('currency'=>'HRK','currency_label'=>'Croatian Kuna');
	$merchant_currencies[] = array('currency'=>'HTG','currency_label'=>'Haitian Gourde');
	$merchant_currencies[] = array('currency'=>'HUF','currency_label'=>'Hungarian Forint');
	$merchant_currencies[] = array('currency'=>'IDR','currency_label'=>'Indonesian Rupiah');
	$merchant_currencies[] = array('currency'=>'ILS','currency_label'=>'Israeli New Sheqel');
	$merchant_currencies[] = array('currency'=>'INR','currency_label'=>'Indian Rupee');
	$merchant_currencies[] = array('currency'=>'IQD','currency_label'=>'Iraqi Dinar');
	$merchant_currencies[] = array('currency'=>'IRR','currency_label'=>'Iranian Rial');
	$merchant_currencies[] = array('currency'=>'ISK','currency_label'=>'Icelandic Króna');
	$merchant_currencies[] = array('currency'=>'JMD','currency_label'=>'Jamaican Dollar');
	$merchant_currencies[] = array('currency'=>'JOD','currency_label'=>'Jordanian Dinar');
	*/
	$merchant_currencies[] = array('currency'=>'JPY','currency_label'=>'Japanese Yen');
	/*
	$merchant_currencies[] = array('currency'=>'KES','currency_label'=>'Kenyan Shilling');
	$merchant_currencies[] = array('currency'=>'KGS','currency_label'=>'Kyrgyzstani Som');
	$merchant_currencies[] = array('currency'=>'KHR','currency_label'=>'Cambodian Riel');
	$merchant_currencies[] = array('currency'=>'KMF','currency_label'=>'Comorian Franc');
	$merchant_currencies[] = array('currency'=>'KPW','currency_label'=>'North Korean Won');
	$merchant_currencies[] = array('currency'=>'KRW','currency_label'=>'South Korean Won');
	$merchant_currencies[] = array('currency'=>'KWD','currency_label'=>'Kuwaiti Dinar');
	$merchant_currencies[] = array('currency'=>'KYD','currency_label'=>'Cayman Islands Dollar');
	$merchant_currencies[] = array('currency'=>'KZT','currency_label'=>'Kazakhstani Tenge');
	$merchant_currencies[] = array('currency'=>'LAK','currency_label'=>'Lao Kip');
	$merchant_currencies[] = array('currency'=>'LBP','currency_label'=>'Lebanese Lira');
	$merchant_currencies[] = array('currency'=>'LKR','currency_label'=>'Sri Lankan Rupee');
	$merchant_currencies[] = array('currency'=>'LRD','currency_label'=>'Liberian Dollar');
	$merchant_currencies[] = array('currency'=>'LSL','currency_label'=>'Lesotho Loti');
	$merchant_currencies[] = array('currency'=>'LTL','currency_label'=>'Lithuanian Litas');
	$merchant_currencies[] = array('currency'=>'LVL','currency_label'=>'Latvian Lats');
	$merchant_currencies[] = array('currency'=>'LYD','currency_label'=>'Libyan Dinar');
	$merchant_currencies[] = array('currency'=>'MAD','currency_label'=>'Moroccan Dirham');
	$merchant_currencies[] = array('currency'=>'MDL','currency_label'=>'Moldovan Leu');
	$merchant_currencies[] = array('currency'=>'MGA','currency_label'=>'Malagasy Ariary');
	$merchant_currencies[] = array('currency'=>'MKD','currency_label'=>'Macedonian Denar');
	$merchant_currencies[] = array('currency'=>'MMK','currency_label'=>'Myanmar Kyat');
	$merchant_currencies[] = array('currency'=>'MNT','currency_label'=>'Mongolian Tögrög');
	$merchant_currencies[] = array('currency'=>'MOP','currency_label'=>'Macanese Pataca');
	$merchant_currencies[] = array('currency'=>'MRO','currency_label'=>'Mauritanian Ouguiya');
	$merchant_currencies[] = array('currency'=>'MUR','currency_label'=>'Mauritian Rupee');
	$merchant_currencies[] = array('currency'=>'MVR','currency_label'=>'Maldivian Rufiyaa');
	$merchant_currencies[] = array('currency'=>'MWK','currency_label'=>'Malawian Kwacha');
	$merchant_currencies[] = array('currency'=>'MXN','currency_label'=>'Mexican Peso');
	$merchant_currencies[] = array('currency'=>'MYR','currency_label'=>'Malaysian Ringgit');
	$merchant_currencies[] = array('currency'=>'MZN','currency_label'=>'Mozambican Metical');
	$merchant_currencies[] = array('currency'=>'NAD','currency_label'=>'Namibian Dollar');
	$merchant_currencies[] = array('currency'=>'NGN','currency_label'=>'Nigerian Naira');
	$merchant_currencies[] = array('currency'=>'NIO','currency_label'=>'Nicaraguan Córdoba');
	$merchant_currencies[] = array('currency'=>'NOK','currency_label'=>'Norwegian Krone');
	$merchant_currencies[] = array('currency'=>'NPR','currency_label'=>'Nepalese Rupee');
	*/
	$merchant_currencies[] = array('currency'=>'NZD','currency_label'=>'New Zealand Dollar');
	/*
	$merchant_currencies[] = array('currency'=>'OMR','currency_label'=>'Omani Rial');
	$merchant_currencies[] = array('currency'=>'PAB','currency_label'=>'Panamanian Balboa');
	$merchant_currencies[] = array('currency'=>'PEN','currency_label'=>'Peruvian Nuevo Sol');
	$merchant_currencies[] = array('currency'=>'PGK','currency_label'=>'Papua New Guinean Kina');
	$merchant_currencies[] = array('currency'=>'PHP','currency_label'=>'Philippine Peso');
	$merchant_currencies[] = array('currency'=>'PKR','currency_label'=>'Pakistani Rupee');
	$merchant_currencies[] = array('currency'=>'PLN','currency_label'=>'Polish Zloty');
	$merchant_currencies[] = array('currency'=>'PYG','currency_label'=>'Paraguayan Guaraní');
	$merchant_currencies[] = array('currency'=>'QAR','currency_label'=>'Qatari Riyal');
	$merchant_currencies[] = array('currency'=>'RON','currency_label'=>'Romanian Leu');
	$merchant_currencies[] = array('currency'=>'RSD','currency_label'=>'Serbian Dinar');
	$merchant_currencies[] = array('currency'=>'RUB','currency_label'=>'Russian Ruble');
	$merchant_currencies[] = array('currency'=>'RWF','currency_label'=>'Rwandan Franc');
	$merchant_currencies[] = array('currency'=>'SAR','currency_label'=>'Saudi Riyal');
	$merchant_currencies[] = array('currency'=>'SBD','currency_label'=>'Solomon Islands Dollar');
	$merchant_currencies[] = array('currency'=>'SCR','currency_label'=>'Seychellois Rupee');
	$merchant_currencies[] = array('currency'=>'SDG','currency_label'=>'Sudanese Pound');
	$merchant_currencies[] = array('currency'=>'SEK','currency_label'=>'Swedish Krona');
	$merchant_currencies[] = array('currency'=>'SGD','currency_label'=>'Singapore Dollar');
	$merchant_currencies[] = array('currency'=>'SHP','currency_label'=>'Saint Helenian Pound');
	$merchant_currencies[] = array('currency'=>'SKK','currency_label'=>'Slovak Koruna');
	$merchant_currencies[] = array('currency'=>'SLL','currency_label'=>'Sierra Leonean Leone');
	$merchant_currencies[] = array('currency'=>'SOS','currency_label'=>'Somali Shilling');
	$merchant_currencies[] = array('currency'=>'SRD','currency_label'=>'Surinamese Dollar');
	$merchant_currencies[] = array('currency'=>'STD','currency_label'=>'São Tomé and Príncipe Dobra');
	$merchant_currencies[] = array('currency'=>'SVC','currency_label'=>'Salvadoran Colón');
	$merchant_currencies[] = array('currency'=>'SYP','currency_label'=>'Syrian Pound');
	$merchant_currencies[] = array('currency'=>'SZL','currency_label'=>'Swazi Lilangeni');
	$merchant_currencies[] = array('currency'=>'THB','currency_label'=>'Thai Baht');
	$merchant_currencies[] = array('currency'=>'TJS','currency_label'=>'Tajikistani Somoni');
	$merchant_currencies[] = array('currency'=>'TMM','currency_label'=>'Turkmenistani Manat');
	$merchant_currencies[] = array('currency'=>'TMT','currency_label'=>'Turkmenistani Manat');
	$merchant_currencies[] = array('currency'=>'TND','currency_label'=>'Tunisian Dinar');
	$merchant_currencies[] = array('currency'=>'TOP','currency_label'=>'Tongan Pa?anga');
	$merchant_currencies[] = array('currency'=>'TRY','currency_label'=>'Turkish New Lira');
	$merchant_currencies[] = array('currency'=>'TTD','currency_label'=>'Trinidad and Tobago Dollar');
	$merchant_currencies[] = array('currency'=>'TWD','currency_label'=>'New Taiwan Dollar');
	$merchant_currencies[] = array('currency'=>'TZS','currency_label'=>'Tanzanian Shilling');
	$merchant_currencies[] = array('currency'=>'UAH','currency_label'=>'Ukrainian Hryvnia');
	$merchant_currencies[] = array('currency'=>'UGX','currency_label'=>'Ugandan Shilling');
	*/
	$merchant_currencies[] = array('currency'=>'USD','currency_label'=>'United States Dollar');
	/*
	$merchant_currencies[] = array('currency'=>'UYU','currency_label'=>'Uruguayan Peso');
	$merchant_currencies[] = array('currency'=>'UZS','currency_label'=>'Uzbekistani Som');
	$merchant_currencies[] = array('currency'=>'VEF','currency_label'=>'Venezuelan Bolívar');
	$merchant_currencies[] = array('currency'=>'VND','currency_label'=>'Vietnamese Ð?ng');
	$merchant_currencies[] = array('currency'=>'VUV','currency_label'=>'Vanuatu Vatu');
	$merchant_currencies[] = array('currency'=>'WST','currency_label'=>'Samoan Tala');
	$merchant_currencies[] = array('currency'=>'XAF','currency_label'=>'Central African Cfa Franc');
	$merchant_currencies[] = array('currency'=>'XCD','currency_label'=>'East Caribbean Dollar');
	$merchant_currencies[] = array('currency'=>'XOF','currency_label'=>'West African Cfa Franc');
	$merchant_currencies[] = array('currency'=>'XPF','currency_label'=>'Cfp Franc');
	$merchant_currencies[] = array('currency'=>'YER','currency_label'=>'Yemeni Rial');
	$merchant_currencies[] = array('currency'=>'ZAR','currency_label'=>'South African Rand');
	$merchant_currencies[] = array('currency'=>'ZMK','currency_label'=>'Zambian Kwacha');
	$merchant_currencies[] = array('currency'=>'ZWD','currency_label'=>'Zimbabwean Dollar');
	*/
	
	return $merchant_currencies;
}

/**
 * Setup the Braintree configuration
 */
function setBraintreeConfiguration() {
	global $merchant_currency;

	require_once( 'braintree/lib/Braintree.php' );
	
	// Get setting values
	$braintree_settings['sandbox_mode']     			= get_option( 'braintree_sandbox_mode' );
	$braintree_settings['sandbox_private_key'] 			= get_option( 'braintree_sandbox_private_key' );
	$braintree_settings['sandbox_public_key']  			= get_option( 'braintree_sandbox_public_key' );
	$braintree_settings['sandbox_merchant_id']			= get_option( 'braintree_sandbox_merchant_id' );
	$braintree_settings['sandbox_merchant_currency']	= get_option( 'braintree_sandbox_merchant_currency' );
	
	$braintree_settings['production_private_key'] 		= get_option( 'braintree_production_private_key' );
	$braintree_settings['production_public_key']  		= get_option( 'braintree_production_public_key' );
	$braintree_settings['production_merchant_id']		= get_option( 'braintree_production_merchant_id' );
	$braintree_settings['production_merchant_currency']	= get_option( 'braintree_production_merchant_currency' );
	
	$braintree_settings['settlement_type']     			= get_option( 'braintree_settlement_type' );
	$braintree_settings['threedee_secure']     			= get_option( 'braintree_threedee_secure' );
	$braintree_settings['threedee_secure_only']   		= get_option( 'braintree_threedee_secure_only' );
	
	// Retrieve the correct Braintree settings, depednign on whether
	// sandbox mode is turne on or off
	if ($braintree_settings['sandbox_mode'] == 'on') {
	
		Braintree_Configuration::environment( 'sandbox' );
		Braintree_Configuration::merchantId( $braintree_settings['sandbox_merchant_id'] );
		Braintree_Configuration::publicKey( $braintree_settings['sandbox_public_key'] );
		Braintree_Configuration::privateKey( $braintree_settings['sandbox_private_key'] );
		$merchant_currency = $braintree_settings['sandbox_merchant_currency'];
	
	} else {
	
		Braintree_Configuration::environment( 'production' );
		Braintree_Configuration::merchantId( $braintree_settings['production_merchant_id'] );
		Braintree_Configuration::publicKey( $braintree_settings['production_public_key'] );
		Braintree_Configuration::privateKey( $braintree_settings['production_private_key'] );
		$merchant_currency = $braintree_settings['production_merchant_currency'];
	
	}
}

/**
 * Checks whether a Braintree transaction ID is valid
 */
function checkBraintreeTransaction() {
	
	setBraintreeConfiguration();

	$transaction_id = $_POST['transaction_id'];

	if ( !empty( $transaction_id ) ) {
		$transaction = Braintree_Transaction::find( $transaction_id );
		return $transaction_id;
	} else {
		throw new BraintreeTransactionException;
	}
}

/**
 * Retrieves a specific transaction
 */
function retrieveBraintreeTransaction($transaction_id) {
	
	setBraintreeConfiguration();
	
	if ( !empty( $transaction_id ) ) {
		$transaction = Braintree_Transaction::find( $transaction_id );
		return $transaction;
	} else {
		throw new BraintreeTransactionException;
	}
}

/**
 * Submits a transaction for refunding
 */
function submitBraintreeRefund() {

	setBraintreeConfiguration();

	$transaction_id = $_POST['refund_payment'];

	try { 
		$result = Braintree_Transaction::refund( $transaction_id );
		
		if ($result->success) {
			$_SESSION['refund_state'] = 'success';
			wpsc_update_purchase_log_details( $transaction_id, array( 'processed' => WPSC_Purchase_Log::REFUNDED ), 'transactid' );
		} else {
			$_SESSION['refund_state'] = 'failure';
			$_SESSION['braintree_errors'] = $result->message;
		}
		
		$_SESSION['braintree_transaction_id'] = $transaction_id;
	}
	catch (Braintree\Exception\Configuration $bec) {
		$output = '<p style="font-weight: bold; color: red; padding: 10px; text-align: center;">There is a problem with the Braintree payment gateway configuration</p>';
		
		$gateway_checkout_form_fields['wpsc_merchant_braintree_v_zero'] = $output;
	}
	catch (Exception $e) {
		// There is not a valid Braintree connection so display nothing.
		$output = '<p style="font-weight: bold; color: red; padding: 10px; text-align: center;">There is a problem with the Braintree payment gateway</p>';
		
		$gateway_checkout_form_fields['wpsc_merchant_braintree_v_zero'] = $output;
	}
}

/**
 * Displays the transaction refund form
 */
function displayBraintreeRefundForm() {
	
	$braintree_transaction = null;
	$braintree_errors = null;
	$braintree_refund_state = null;
	
	if ( !empty( $_SESSION['braintree_transaction_id'] ) ) {
		try {
			$braintree_transaction = retrieveBraintreeTransaction( $_SESSION['braintree_transaction_id'] );
		}
		catch (BraintreeTransactionException $bte) {
			$braintree_errors = 'You have not entered a Transaction ID';
		}
		
		unset( $_SESSION['braintree_transaction_id'] );
	}

	if ( !empty( $_SESSION['braintree_errors'] ) ) {
		$braintree_errors = $_SESSION['braintree_errors'];
		unset( $_SESSION['braintree_errors'] );
	}
?>
	<h3><?php _e( 'Refund a Customer', 'wp-e-commerce' ); ?></h3>
	<p><?php _e( 'This page allows you to make refunds to customers that paid via the Braintree gateway. If the Transaction has been settled and has not yet been refunded you will be able to submit the Transaction for refunding.', 'wp-e-commerce' ); ?></p>

	<div>
<?php 
	if ( !empty( $_SESSION['refund_state'] ) ) {
		$braintree_refund_state = $_SESSION['refund_state'];
		unset( $_SESSION['$refund_state'] );
		
		if ($braintree_refund_state == 'success') {
?>
		<p>
			Your Transaction has been refunded.
		</p>
<?php 
		}
	}
	
	if ($braintree_errors != null) {
		print '<p style="margin-top: 10px; padding: 10px; border: 1px solid red; font-weight: bold; background-color: darksalmon;">'.$braintree_errors.'</p>';
	}
?>
		<p>
			Braintree Transaction ID: <input type="text" id="transaction_id" name="transaction_id" value="" /> <button type="submit" id="retrieve_transaction" name="retrieve_transaction" value="retrieve_transaction">Find Transaction</button>
		</p>
	</div>
<?php 
	if ($braintree_transaction != null) {
		//var_dump($braintree_transaction);
?>
	<p>
		<b>Braintree Transaction ID:</b> <?php print $braintree_transaction->id; ?>
	</p>
<?php 
		if ($braintree_transaction->type == 'credit') {
?>
	<p>
		<b>Transaction Type:</b> <span style="color: red;">Credit</span>
	</p>
<?php 
		} else {
?>
	<p>
		<b>Transaction Type:</b> <span style="color: green;">Sale</span>
	</p>
<?php 
		}
?>
	<p>
		<b>Transaction Status:</b> <?php print $braintree_transaction->status; ?>
	</p>
	<p>
		<b>Amount:</b> <?php print $braintree_transaction->currencyIsoCode; ?> <?php print $braintree_transaction->amount; ?>
	</p>
	<p>
		<b>Order ID:</b> <?php print $braintree_transaction->orderId; ?>
	</p>
	<p>
		<b>Customer:</b> <?php print $braintree_transaction->customerDetails->firstName; ?> <?php print $braintree_transaction->customerDetails->lastName; ?>
	</p>
	<p>
		<b>Email:</b> <?php print $braintree_transaction->customerDetails->email; ?>
	</p>
<?php 
		if ( $braintree_transaction->type == 'credit' ) {
			// This transaction is a refund so do not show refund button
		} elseif ( $braintree_transaction->refundId != null ) {
			// A refund has already been performed on his transaction so do not show refund button
		} else {
			if ($braintree_transaction->status == 'settled') {
?>
	<button type="submit" id="refund_payment" name="refund_payment" value="<?php print $braintree_transaction->id; ?>">Refund Transaction</button>
<?php 
			}
		}
	}
}

function pp_braintree_enqueue_js() {
	global $merchant_currency;
	
		setBraintreeConfiguration();
		
		$clientToken = Braintree_ClientToken::generate();
	
		?>	
		<script src="https://js.braintreegateway.com/web/3.16.0/js/client.js"></script>
		<script src="https://js.braintreegateway.com/web/3.16.0/js/hosted-fields.js"></script>
		<script type='text/javascript'>
			var clientToken = "<?php echo $clientToken; ?>";

			var form = document.querySelector('.wpsc_checkout_forms');
	
			braintree.client.create({
			  authorization: clientToken
			}, function(err, clientInstance) {
			  if (err) {
				console.error(err);
				return;
			  }
			  createHostedFields(clientInstance);
			});

			function createHostedFields(clientInstance) {
			  braintree.hostedFields.create({
				client: clientInstance,
				styles: {
				  'input': {
					'font-size': '16px',
					'font-family': 'courier, monospace',
					'font-weight': 'lighter',
					'color': '#ccc'
				  },
				  ':focus': {
					'color': 'black'
				  },
				  '.valid': {
					'color': '#8bdda8'
				  }
				},
				fields: {
				  number: {
					selector: '#card-number',
					placeholder: '4111 1111 1111 1111'
				  },
				  cvv: {
					selector: '#card-cvv',
					placeholder: '123'
				  },
				  expirationDate: {
					selector: '#card-exp',
					placeholder: 'MM/YYYY'
				  },
				}
			  }, function (err, hostedFieldsInstance) {
				var teardown = function (event) {
				  event.preventDefault();
				  alert('Submit your nonce to your server here!');
				  hostedFieldsInstance.teardown(function () {
					createHostedFields(clientInstance);
					form.removeEventListener('submit', teardown, false);
				  });
				};
				
				form.addEventListener('submit', teardown, false);
			  });
			}
		</script>
	<?php
}
add_action( 'wpsc_bottom_of_shopping_cart' , 'pp_braintree_enqueue_js', 100 );

add_filter(
	'wpsc_purchase_log_customer_notification_raw_message',
	'_wpsc_filter_test_merchant_customer_notification_raw_message',
	10,
	2
);

