<?php
/**
 * Plugin Name: BBA Mastro
 * Description: Multi Carrier Shipping and Logistics Technology able to seamlessly integrate into your Woo cart.
 * Version: 2.4.8
 * Author: BBA Mastro
 * Author URI: https://bbamastro.com
 * @package BBA Mastro
 * @category Core
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
  exit;
}

if (!class_exists('BBAMastro')) {
  final class BBAMastro {
    public $version = '2.4.8';
    public static $bbamTrackingConsignmentInstance;
    public static $bbam_excluded_categories_ins;
    protected static $usInstance = null;

    public function __construct() {
      require_once "includes/bba-api/class-bbam-utils.php";
      $this->initialize();
    }

    public static function getInstance() {
      if (is_null(self::$usInstance)) {
        self::$usInstance = new self();
      }
      return self::$usInstance;
    }

    private function initialize() {
      $this->initializeConstants();
      add_action('wp_loaded', ['BBAMastro', 'onWordpressLoaded']);
      add_action('plugins_loaded', ['BBAMastro', 'onPluginLoaded']);
      register_activation_hook(__FILE__, ['BBAMastro', 'onPluginActivate']);
    }

    public static function onPluginActivate() {
      if (BBAM_Utils::woocommerceEnabled() && is_admin()) {
        BBAM_Utils::addTaxonomy("manufacturer");
      }
    }

    public static function onWordpressLoaded() {
      if (BBAM_Utils::woocommerceEnabled() && is_admin()) {
        self::includeAdminJsScripts();
        BBAM_Utils::addTerms('manufacturer');
      }
    }

    private static function includeAdminJsScripts(){
      $countries = new BBAM_Country();
      wp_register_script('bbamastro-api', self::pluginURL().'/admin/js/shipping-rules.js', ['jquery', 'select2'], BBAMASTRO_VERSION);
      wp_localize_script('bbamastro-api', 'bbamastro', [
        'countries'  => $countries->getOptions(true),
        'settings'   => [
          'country'  => BBAM_Utils::getConfig('warehouse_country'),
          'postcode' => BBAM_Utils::getConfig('warehouse_postcode')
        ]
      ]);
      wp_enqueue_script('bbamastro-api');
    }

    private static function pluginURL(){
      return untrailingslashit(plugins_url( '/', __FILE__ ));
    } 

    private static function pluginPath(){
      return untrailingslashit(plugin_dir_path( __FILE__ ));
    }

    public static function onPluginLoaded() {
      if(BBAM_Utils::woocommerceEnabled()) {
        BBAM_Utils::includeBbaClass();
        add_filter('woocommerce_shipping_methods', ['BBAMastro', 'shippingMethods']);
        add_action('woocommerce_webhook_payload', ['BBAM_Utils', 'generateWebhookPayload']);
        add_filter('woocommerce_rest_prepare_product_object', ['BBAM_Utils', 'prepareProducts']);
        add_filter('woocommerce_default_address_fields', ['BBAMastro', 'reorderCheckoutFields']);
        add_filter('woocommerce_package_rates', ['BBAMastro', 'hideFlatRate']);

        // Initialize Tracking ID and Consigment ID Fields
        self::$bbamTrackingConsignmentInstance = new BBAM_TrackingIdAndConsignmentIdFields(self::pluginPath());
        self::$bbam_excluded_categories_ins = new BBAAM_ExcludedCategories(self::pluginPath());
      }
    }
    
    public static function shippingMethods($methods) {
      array_push($methods, 'BBAM_ShippingRules');
      return $methods;
    }

    public static function reorderCheckoutFields($address_fields){
      $fieldsArray = array(
        'company',
        'first_name',
        'last_name',
        'address_1',
        'address_2',
        'country',
        'state',
        'city',
        'postcode',
        'phone',
        'email'
      );

      for($i = 0; $i < count($fieldsArray); $i++) {
        $priority = $i * 10;
        $address_fields[$fieldsArray[$i]]['priority'] = $priority;
      }

      return $address_fields;
    }

    private function initializeConstants() {
      BBAM_Utils::defineConstantIfNotExists('BBAMASTRO_PLUGIN_FILE', __FILE__);
      BBAM_Utils::defineConstantIfNotExists('BBAMASTRO_PLUGIN_BASENAME', plugin_basename(__FILE__));
      BBAM_Utils::defineConstantIfNotExists('BBAMASTRO_VERSION', $this->version);
    }

    public static function hideFlatRate($rates) {      
      $show_flat_rate = BBAM_Utils::getConfig('show_flat_rate');
      $bba_mastro_rules_index = BBAM_Utils::getShippingMethodIndex($rates, 'bbamastro_rules');
      $flat_rate_quote_index = BBAM_Utils::getShippingMethodIndex($rates, 'flat_rate');
      
      if ($show_flat_rate === 'no' && $bba_mastro_rules_index != -1 && $flat_rate_found != -1) {
        $new_rates = array();

        foreach($rates as $rate_id => $rate) {
          if ($rate->method_id !== 'flat_rate') {
            $new_rates[$rate_id] = $rate; 
          }
        }

        return $new_rates;
      }

      return $rates;
    }
  }
}

function BBAM() {
  return BBAMastro::getInstance();
}
BBAM();
