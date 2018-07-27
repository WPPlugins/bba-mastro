<?php
/**
 * Plugin Name: BBA Mastro
 * Description: Multi Carrier Shipping and Logistics Technology able to seamlessly integrate into your Woo cart.
 * Version: 1.2.4
 * Author: BBA Mastro
 * Author URI: https://bbamastro.com
 * @package BBA Mastro
 * @category Core
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'BBAMastro' ) ) :

/**
 * Main Class.
 *
 * @class BBAMastro
 */
final class BBAMastro { 

    /**
     * Plugin version.
     *
     * @var string
     */
    public $version = '1.2.4';

    /**
     * The single instance of the class.
     *
     * @var BBAMastro
     */
    protected static $us_instance = null;

    /**
     * Main Instance.
     *
     * @return BBAMastro
     */
    public static function bbam_instance() {

        if ( is_null( self::$us_instance ) ) {
            self::$us_instance = new self();
        }
        return self::$us_instance;
    }

    /**
     * Constructor.
     */
    public function __construct() {

       
        $this->bbam_define_constants();
        $this->bbam_includes();
        $this->bbam_init_hooks();

        register_activation_hook( __FILE__,array( $this,'bbam_add_taxonomy' ) );

        add_action( 'wp_loaded', array('BBAMastro','bbam_add_terms'));

        do_action( 'bbamastro_loaded' );
    
    }
    public static function bbam_add_terms(){
         if(class_exists( 'woocommerce' ) && is_admin()){
            bbamastro_add_terms("manufacturer");
         }
    }
    public function bbam_add_taxonomy(){
        if ( $this->bbam_woocommerce_enabled() && $this->bbam_check_request( 'admin' ) ) { // Woocommerce must be enabled.
            bbamastro_add_taxonomy("manufacturer");
        }
    }
   

    /**
     * Define Constants.
     */
    private function bbam_define_constants() {
        $this->bbam_define( 'BBAMASTRO_PLUGIN_FILE', __FILE__ );
        $this->bbam_define( 'BBAMASTRO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
        $this->bbam_define( 'BBAMASTRO_VERSION', $this->version );
    }

    /**
     * Define constant if not already set.
     *
     * @param  string $name
     * @param  string|bool $value
     */
    private function bbam_define( $name, $value ) {
        if ( ! defined( $name ) ) {
            define( $name, $value );
        }
    }

    /**
     * Include required core files used in admin and on the frontend.
     */
    public function bbam_includes() {
        include_once( 'includes/functions-bbamastro.php' );
        include_once( 'includes/class-bbamastro-api.php' );
        include_once( 'includes/class-bbamastro-country.php' );
    }

    /**
     * Hook into actions and filters.
     */
    private function bbam_init_hooks() {
        add_action( 'plugins_loaded', array( $this, 'bbam_plugins_loaded' ) );
    }

    /**
     * After Wordpress plugins loaded.
     */
    public function bbam_plugins_loaded() {
        if ( $this->bbam_woocommerce_enabled() ) { // Woocommerce must be enabled.
            if ( $this->bbam_check_request( 'admin' ) ) {
                add_action('admin_enqueue_scripts', 'bbamastro_admin_scripts' );
            }
            if ( $this->bbam_check_request( 'frontend' ) ) {
                // Shipping calculator
                add_action( 'wp_enqueue_scripts', 'bbamastro_frontend_scripts' );
                add_action( 'woocommerce_single_product_summary', 'bbamastro_shipping_calculator', 100 );
            }
            if ( $this->bbam_check_request( 'ajax' ) ) {
                add_action( 'ajax_bbamastro_estimate_shipping', 'bbamastro_estimate_shipping' );
                add_action( 'ajax_nopriv_bbamastro_estimate_shipping', 'bbamastro_estimate_shipping' );

            }

            // Shipping rules
            add_filter( 'woocommerce_shipping_methods', 'bbamastro_shipping_methods' );
            // webhook payload
            add_action('woocommerce_webhook_payload', 'bbamastro_woocommerce_webhook_payload');
            // rest api response for products
            add_filter( 'woocommerce_rest_prepare_product_object', 'bbamastro_prepare_product', 90, 2 );

            include_once( 'includes/class-bbamastro-shipping-rules.php' );
            include_once( 'includes/class-bbamastro-shipping-calculator.php' );
        }
        include_once( 'includes/class-bbamastro-install.php' );
    }

    /**
     * Is Woocommerce enabled.
     *
     * @return bool
     */
    public function bbam_woocommerce_enabled() {
        if ( class_exists( 'woocommerce' ) ) {
            return true;
        }else{
            return false;
        }
    }

    /**
     * Is shipping calculations enabled.
     *
     * @return bool
     */
    public function bbam_shipping_enabled() {
        $enabled = get_option( 'woocommerce_calc_shipping' );
        return ( $enabled == 'yes' ) ? true : false;
    }

    /**
     * Type of request.
     *
     * @param string $type
     * @return bool
     */
    private function  bbam_check_request( $type ) {
        switch ( $type ) {
            case 'admin' :
                return is_admin();
            case 'ajax' :
                return defined( 'DOING_AJAX' );
            case 'cron' :
                return defined( 'DOING_CRON' );
            case 'frontend' :
                return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
        }
    }

    /**
     * Get the plugin url.
     * @return string
     */
    public function bbam_plugin_url() {
        return untrailingslashit( plugins_url( '/', __FILE__ ) );
    }

    /**
     * Get the plugin path.
     * @return string
     */
    public function bbam_plugin_path() {
        return untrailingslashit( plugin_dir_path( __FILE__ ) );
    }

    /**
     * Get the template path.
     * @return string
     */
    public function bbam_template_path() {
        return apply_filters( 'bbamastro_template_path', 'bbamastro/' );
    }
}

endif;

/**
 * Main instance.
 *
 * Returns the main instance to prevent the need to use globals.
 * @return BBAMastro
 */
function BBAM() {
    return BBAMastro::bbam_instance();
}
BBAM();
