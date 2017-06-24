<?php
/*
Plugin Name: WP eCommerce Braintree V.Zero
Plugin URI: https://wpecommerce.org/store/
Version: 1.0.0
Author: WP eCommerce
Description: A plugin that allows the store owner to process payments using Braintree V.Zero
Author URI:  https://wpecommerce.org
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

class WPEC_PP_Braintree_V_Zero {
	private static $instance;

	public function __construct() {
		add_action( 'admin_init', array( $this, 'handle_auth_connect' ) );
		add_action( 'admin_init', array( $this, 'handle_auth_disconnect' ) );
	}

	public static function get_instance() {

		if  ( ! isset( self::$instance ) && ! ( self::$instance instanceof WPEC_PP_Braintree_V_Zero ) ) {
			self::$instance = new WPEC_PP_Braintree_V_Zero;
			self::define_constants();
			self::includes();
			self::add_actions();
			self::add_filters();
		}
		return self::$instance;
	}	

	public static function define_constants() {

		if ( ! defined( 'WPEC_PPBRAINTREE_VZERO_PLUGIN_DIR' ) ) {
			define( 'WPEC_PPBRAINTREE_VZERO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		}

		if ( ! defined( 'WPEC_PPBRAINTREE_VZERO_PLUGIN_URL' ) ) {
			define( 'WPEC_PPBRAINTREE_VZERO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		}
		
		if ( ! defined( 'WPEC_PPBRAINTREE_VZERO_PLUGIN_FILE' ) ) {
			define( 'WPEC_PPBRAINTREE_VZERO_PLUGIN_FILE', __FILE__ );
		}
		
		if ( ! defined( 'WPEC_PPBRAINTREE_VZERO_VERSION' ) ) {
			define( 'WPEC_PPBRAINTREE_VZERO_VERSION', '1.0.0' );
		}
	}

	public static function includes() {
	}

	public static function add_actions() {
		add_action( 'wpsc_init', array( self::$instance, 'init' ), 2 );
		add_action( 'wpsc_init', array( self::$instance, 'pp_braintree_checkout_fields' ) );
	}

	public static function add_filters() {
		add_filter( 'wpsc_merchants_modules', array( self::$instance, 'register_gateway' ), 50 );
	}

	public function init() {
		include_once WPEC_PPBRAINTREE_VZERO_PLUGIN_DIR . '/class-pp-braintree-v-zero.php';
	}

	public function register_gateway( $gateways ) {
		$num = max( array_keys( $gateways ) ) + 1;
		$gateways[ $num ] = array(
			'name'                   => __( 'Braintree V.Zero', 'wp-e-commerce' ),
			'api_version'            => 2.0,
			'has_recurring_billing'  => true,
			'display_name'           => __( 'Braintree Payment', 'wp-e-commerce' ),
			'image'                  => WPSC_URL . '/images/cc.gif',
			'wp_admin_cannot_cancel' => false,
			'requirements' => array(
				//'php_version' => 5.0
				),
			'class_name'      => 'wpsc_merchant_braintree_v_zero',
			'form'            => 'form_braintree_v_zero',
			'internalname'    => 'wpsc_merchant_braintree_v_zero'
		);

		$image = apply_filters( 'wpsc_merchant_image', '', $gateways[$num]['internalname'] );
		if ( ! empty( $image ) ) {
			$gateways[$num]['image'] = $image;
		}

		return $gateways;
	}

	public function pp_braintree_checkout_fields() {
		global $gateway_checkout_form_fields;

		if ( in_array( 'wpsc_merchant_braintree_v_zero', (array) get_option( 'custom_gateway_options' ) ) ) {
			ob_start(); 

		if ( (bool) get_option( 'bt_vzero_pp_payments' ) == true ) {
			echo '<div id="pp_braintree_pp_button"></div>';
		}

		if ( (bool) get_option( 'bt_vzero_cc_payments' ) == true ) {
			echo '<br>
				<label class="hosted-fields--label" for="card-number">Card Number</label>
				<div id="card-number" class="hosted-field"></div>

				<label class="hosted-fields--label" for="expiration-date">Expiration Date</label>
					<div id="card-exp" class="hosted-field"></div>
				<label class="hosted-fields--label" for="cvv">CVV</label>
					<div id="card-cvv" class="hosted-field"></div>
					<input type="hidden" id="pp_btree_method_nonce" name="pp_btree_method_nonce" value="" />
				
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
		}

		$gateway_checkout_form_fields['wpsc_merchant_braintree_v_zero'] = ob_get_clean();
		}
	}
	
	/**
	 * Handles the Braintree Auth connection response.
	 *
	 * @since 1.0.0
	 */
	public function handle_auth_connect() {

		// TO DO some sort of validation that we are on the correct page ? settings/gateways
		if ( isset( $_REQUEST['wpec_paypal_braintree_admin_nonce'] ) && isset( $_REQUEST['access_token'] ) && ( isset( $_REQUEST['payment_gateway_id'] ) && $_REQUEST['payment_gateway_id'] == 'wpsc_merchant_braintree_v_zero' ) ) {

			$nonce = isset( $_REQUEST[ 'wpec_paypal_braintree_admin_nonce' ] ) ? trim( $_REQUEST[ 'wpec_paypal_braintree_admin_nonce' ] ) : false;
			// if no nonce is present, then this probably wasn't a connection response
			if ( ! $nonce ) {
				return;
			}

			// verify the nonce
			if ( ! wp_verify_nonce( $nonce, 'connect_paypal_braintree' ) ) {
				wp_die( __( 'Invalid connection request', 'wpec-paypal-braintree-vzero' ) );
			}

			$access_token = isset( $_REQUEST[ 'access_token' ] ) ? sanitize_text_field( base64_decode( $_REQUEST[ 'access_token' ] ) ) : false; 

			if ( $access_token ) {

				update_option( 'wpec_braintree_auth_access_token', $access_token );

				list( $token_key, $environment, $merchant_id, $raw_token ) = explode( '$', $access_token );

				update_option( 'wpec_braintree_auth_environment', $environment );
				update_option( 'wpec_braintree_auth_merchant_id', $merchant_id );

				$connected = true;

			} else {
				// Show an error message maybe ?
				$connected = false;
			}

			wp_safe_redirect( add_query_arg( 'wpec_braintree_connected', $connected, admin_url( 'options-general.php?page=wpsc-settings&tab=gateway&payment_gateway_id=wpsc_merchant_braintree_v_zero' ) ) );
			exit;
		}
	}

	/**
	 * Handles the Braintree Auth disconnect request
	 *
	 * @since 1.0.0
	 */
	public function handle_auth_disconnect() {
		// if this is not a disconnect request, bail
		if ( ! isset( $_REQUEST[ 'disconnect_paypal_braintree' ] ) ) {
			return;
		}

		$nonce = isset( $_REQUEST[ 'wpec_paypal_braintree_admin_nonce' ] ) ? trim( $_REQUEST[ 'wpec_paypal_braintree_admin_nonce' ] ) : false;

		// if no nonce is present, then this probably wasn't a disconnect request
		if ( ! $nonce ) {
			return;
		}

		// verify the nonce
		if ( ! wp_verify_nonce( $nonce, 'disconnect_paypal_braintree' ) ) {
			wp_die( __( 'Invalid disconnect request', 'wpec-paypal-braintree-vzero' ) );
		}

		delete_option( 'wpec_braintree_auth_access_token' );
		delete_option( 'wpec_braintree_auth_environment' );
		delete_option( 'wpec_braintree_auth_merchant_id' );

		wp_safe_redirect( add_query_arg( 'wpec_braintree_disconnected', true, admin_url( 'options-general.php?page=wpsc-settings&tab=gateway&payment_gateway_id=wpsc_merchant_braintree_v_zero' ) ) );
		exit;		
	}
}
add_action( 'wpsc_pre_init', 'WPEC_PP_Braintree_V_Zero::get_instance' );