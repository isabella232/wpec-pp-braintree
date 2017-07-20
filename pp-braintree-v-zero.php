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
		require_once( 'braintree/lib/Braintree.php' );
	}

	public static function add_actions() {
		add_action( 'wpsc_init', array( self::$instance, 'init' ), 2 );
		add_action( 'admin_enqueue_scripts', array( self::$instance, 'admin_scripts' ) );
		add_action( 'wpsc_bottom_of_shopping_cart' , array( 'wpsc_merchant_braintree_v_zero', 'pp_braintree_enqueue_js' ), 100 );
	}

	public static function add_filters() {
		add_filter( 'wpsc_merchants_modules', array( self::$instance, 'register_gateway' ), 50 );
		add_action( 'wpsc_inside_shopping_cart', array( self::$instance, 'te_v1_insert_hidden_field' ) );
		add_filter( 'wpsc_gateway_checkout_form_wpsc_merchant_braintree_v_zero_cc', array( self::$instance, 'pp_braintree_cc_checkout_fields') );
		add_filter( 'wpsc_gateway_checkout_form_wpsc_merchant_braintree_v_zero_pp', array( self::$instance, 'pp_braintree_pp_checkout_fields') );
	}

	public function init() {
		include_once WPEC_PPBRAINTREE_VZERO_PLUGIN_DIR . '/class-pp-braintree-v-zero.php';
		include_once WPEC_PPBRAINTREE_VZERO_PLUGIN_DIR . '/class-pp-braintree-v-zero-pp.php';
		include_once WPEC_PPBRAINTREE_VZERO_PLUGIN_DIR . '/class-pp-braintree-v-zero-cc.php';
	}

	public function admin_scripts( $hook ) {
		if ( 'settings_page_wpsc-settings' !== $hook ) {
			return;
		}

		wp_enqueue_script( 'bt-script', WPEC_PPBRAINTREE_VZERO_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), WPSC_VERSION, true );
	}

	public function register_gateway( $gateways ) {
		$num = max( array_keys( $gateways ) ) + 1;
		$gateways[ $num ] = array(
			'name'                   => __( 'PayPal powered by Braintree - PayPal', 'wp-e-commerce' ),
			'api_version'            => 2.0,
			'has_recurring_billing'  => true,
			'display_name'           => __( 'PayPal', 'wp-e-commerce' ),
			'image'                  => 'https://www.paypalobjects.com/webstatic/en_US/i/buttons/PP_logo_h_200x51.png',
			'wp_admin_cannot_cancel' => false,
			'requirements' => array(
				//'php_version' => 5.0
				),
			'class_name'      => 'wpsc_merchant_braintree_v_zero_pp',
			'form'            => array( 'wpsc_merchant_braintree_v_zero_pp', 'form_braintree_v_zero_pp' ),
			'internalname'    => 'wpsc_merchant_braintree_v_zero_pp'
		);

		$image = apply_filters( 'wpsc_merchant_image', '', $gateways[$num]['internalname'] );
		if ( ! empty( $image ) ) {
			$gateways[$num]['image'] = $image;
		}

		$num = max( array_keys( $gateways ) ) + 1;
		$gateways[ $num ] = array(
			'name'                   => __( 'PayPal powered by Braintree - Cards', 'wp-e-commerce' ),
			'api_version'            => 2.0,
			'has_recurring_billing'  => true,
			'display_name'           => __( 'Credit/Debit Cards', 'wp-e-commerce' ),
			'image'                  => WPSC_URL . '/images/cc.gif',
			'wp_admin_cannot_cancel' => false,
			'requirements' => array(
				//'php_version' => 5.0
				),
			'class_name'      => 'wpsc_merchant_braintree_v_zero_cc',
			'form'            => array( 'wpsc_merchant_braintree_v_zero_cc', 'form_braintree_v_zero_cc' ),
			'internalname'    => 'wpsc_merchant_braintree_v_zero_cc'
		);

		$image = apply_filters( 'wpsc_merchant_image', '', $gateways[$num]['internalname'] );
		if ( ! empty( $image ) ) {
			$gateways[$num]['image'] = $image;
		}
		
		return $gateways;
	}

	public function te_v1_insert_hidden_field() {
		echo '<input type="hidden" id="pp_btree_method_nonce" name="pp_btree_method_nonce" value="" />';
	}

	public function pp_braintree_cc_checkout_fields() {
		$output = '';

		if ( wpsc_merchant_braintree_v_zero::is_gateway_active( 'wpsc_merchant_braintree_v_zero_cc' ) ) {
			$output .= '<tr><td><label class="hosted-fields--label" for="card-number">Card Number</label>
							<div id="bt-cc-card-number" class="hosted-field"></div>
						</td></tr>
						<tr><td><label class="hosted-fields--label" for="expiration-date">Expiration Date</label>
							<div id="bt-cc-card-exp" class="hosted-field"></div>
						</td></tr>
						<tr><td><label class="hosted-fields--label" for="cvv">CVV</label>
							<div id="bt-cc-card-cvv" class="hosted-field"></div></td>
						</tr>

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

		return $output;
	}

	public function pp_braintree_pp_checkout_fields() {
		$output = '';

		if ( wpsc_merchant_braintree_v_zero::is_gateway_active( 'wpsc_merchant_braintree_v_zero_pp' ) ) {
			$output .= '<tr><td>' . __( 'Click below to continue to PayPal', 'wp-e-commerce' ) .'</td></tr>';
			$output .= '<tr><td><div id="pp_braintree_pp_button"></div></td></tr>';
		}

		return $output;
	}

	/**
	 * Handles the Braintree Auth connection response.
	 *
	 * @since 1.0.0
	 */
	public function handle_auth_connect() {
		// TO DO some sort of validation that we are on the correct page ? settings/gateways
		if ( isset( $_REQUEST['wpec_paypal_braintree_admin_nonce'] ) && isset( $_REQUEST['access_token'] ) ) {

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

			wp_safe_redirect( add_query_arg( 'wpec_braintree_connected', $connected, admin_url( 'options-general.php?page=wpsc-settings&tab=gateway' ) ) );
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

		wp_safe_redirect( add_query_arg( 'wpec_braintree_disconnected', true, admin_url( 'options-general.php?page=wpsc-settings&tab=gateway' ) ) );
		exit;		
	}
}
add_action( 'wpsc_pre_init', 'WPEC_PP_Braintree_V_Zero::get_instance' );