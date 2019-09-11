<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BBAMastro_Shipping_Rules Class.
 */
class BBAMastro_Shipping_Rules extends WC_Shipping_Method {

    /**
     * Constructor.
     */
    public function __construct() {

        $this->id                 = 'bbamastro_rules';
        $this->title              = __( 'BBA Mastro', 'bbamastro' );
        $this->method_title       = __( 'BBA Mastro', 'bbamastro' );
        $this->method_description = __( 'Multi Carrier Shipping and Logistics Technology able to seamlessly integrate into your Woo cart.', 'bbamastro' );
        $this->bbam_init();

        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    /**
     * Initialize.
     */
    public function bbam_init() {
        $this->bbam_init_form_fields();
        $this->init_settings();
    }

    /**
     * Initialize settings form fields.
     */
    public function bbam_init_form_fields() {
        $this->form_fields = include( 'settings/bbamastro-rules.php' );
    }

    /**
     * Calculate shipping rates.
     * this method is an overide
     */
    public function calculate_shipping( $package = array() ) {

        try {
            $params = $this->bbam_get_carrier_quote_params( $package );
            $rates = $this->bbam_collect_rates( $params );
            foreach( $rates as $rate ) {
                $this->add_rate( $rate );
            }
        } catch (Exception $e) {
            $this->bbam_log( $e->getMessage() );
        }
    }

    /**
     * Validate country code.
     */
    public function bbam_validate_country_code( $country ) {

        $countries = new BBAMastro_Country();
        if ( ! in_array( $country, $countries->bbam_code() ) ) {
            throw new Exception('Invalid country code.');
        }
        return true;
    }

    /**
     * Get shipping origin.
     */
    public function bbam_get_shipping_origin() {

        $this->bbam_validate_country_code( $this->get_option( 'warehouse_country' ) );

        return array(
            'country'  => $this->get_option( 'warehouse_country' ),
            'city'     => $this->get_option( 'wh_city' ),
            'postcode' => $this->get_option( 'wh_postcode' ),
        );
    }

    /**
     * Get shipping destination.
     */
    public function bbam_get_shipping_destination( $address ) {

        $this->bbam_validate_country_code( $address['country'] );

        return array(
            'country'  => $address['country'],
            'city'     => $address['city'],
            'postcode' => $address['postcode'],
        );
    }

    /**
     * Get package settings.
     */
    public function bbam_get_package_settings() {

        return array(
            'length' => ( float ) $this->get_option( 'default_length' ),
            'width'  => ( float ) $this->get_option( 'default_width' ),
            'height' => ( float ) $this->get_option( 'default_height' ),
            'weight' => ( float ) $this->get_option( 'default_weight' ),
        );
    }

    /**
     * Get Location Object from Selected country & postcode
     */
    public function bbam_getLocationObject($country, $postcode) {

        $queries = array(
            'country' => $country,
            'code'    => $postcode,
            'size'    => 50
        );
       
       
        $api = new BBAMastro_Api();
        $response = $api->bbam_dispatch( 'GET', 'address/location', $queries );

        return $response;
    }

    /**
     * Get carrier quote params.
     */
    public function bbam_get_carrier_quote_params( $package = array() ) {
        $params = array();
        $default = $this->bbam_get_package_settings();

        $params['channel'] = (string)$this->get_option('channel');
        $params['source'] = $this->bbam_get_shipping_origin();
        $params['destination'] = $this->bbam_get_shipping_destination( $package['destination'] );
        $params['items'] = array();
        if(!isset($_REQUEST['product-id'])){
            foreach ( $package['contents'] as $item ) {
                $params['items'][] = array(
                //"description"       => $item['data']->get_description(),
                'quantity'          => ( int ) $item['quantity'],
                "value"             => ( int ) $item['data']->get_regular_price(),
                //"manufacturer"      => "China",
                //"itemType"          => $item['data']->get_type(),
                //"categories"        => array("expensive", "electronics"),
                //"sku"               => $item['data']->get_sku(),
                //"country"           => "Canada",
                'length'            => ( float ) $item['data']->get_length() ?: $default['length'],
                'width'             => ( float ) $item['data']->get_width() ?: $default['width'],
                'height'            => ( float ) $item['data']->get_height() ?: $default['height'],
                'weight'            => ( float ) $item['data']->get_weight() ?: $default['weight'],
                "code"              => "TST",
                //"name"              => $item['data']->get_name(),
                //"taxes"             => null,
                "weightUnit"        => "Kilograms",
                "measureUnit"       => "CM",
                "currency"          => "USD"
                );
            }
        } else{
            $product_id = absint($_REQUEST['product-id'] );
            $product = wc_get_product( $product_id );
            $params['items'][] = array(
                //"description"       => $product->get_description(),
                "quantity"          => 1,
                "value"             => ( int )$product->get_regular_price(),
                //"manufacturer"      => "China",
                //"itemType"          => $product->get_type(),
                //"categories"        => array("expensive", "electronics"),
                //"sku"               => $product->get_sku(),
                //"country"           => "Canada",
                "weight"            => $product->get_weight() ?: $default['length'],
                "length"            => $product->get_length() ?: $default['length'],
                "width"             => $product->get_width() ?: $default['length'],
                "height"            => $product->get_height() ?: $default['length'],
                "code"              => "TST",
                //"name"              => $product->get_name(),
                //"taxes"             => null,
                "weightUnit"        => "Kilograms",
                "measureUnit"       => "CM",
                "currency"          => "USD"
            );
        }

        $params['packages'] = array();
        foreach ( $package['contents'] as $item ) {
            if ( $item['data']->needs_shipping() ) {
                $params['packages'][] = array(
                    'length'   => ( float ) $item['data']->get_length() ?: $default['length'],
                    'width'    => ( float ) $item['data']->get_width() ?: $default['width'],
                    'height'   => ( float ) $item['data']->get_height() ?: $default['height'],
                    'weight'   => ( float ) $item['data']->get_weight() ?: $default['weight'],
                    'quantity' => ( int ) $item['quantity'],
                    "weightUnit" => "Kilograms",
                    "measureUnit" => "CM",
                    "contentWeight" => ( float ) $item['data']->get_weight() ?: $default['weight']
                );
            }
        }  
        
        return $params;
    }

    /**
     * Collect rates.
     */
    public function bbam_collect_rates( $params = array() ) {
        $this->bba_woo_logger($params, 'Request Quote Params');
        if(bbamastro_config('enabled') === 'yes'){
            $api = new BBAMastro_Api();
            $response = $api->bbam_dispatch( 'POST', 'carrier/quote', array(), $params, true );
            $this->bba_woo_logger($params, 'Request Quote Response');
            return $this->bbam_parse_rates( $response );
        }
    }

    /**
     * Parse rates.
     */
    public function bbam_parse_rates( $response ) {

        $rates = array();
        if (is_array($response) || is_object($response)){
            if(!empty($response)) {
                foreach( $response as $carrier ) {
                    $carrierProfileId = $carrier->carrierProfileId;
                    $carrierName = $carrier->carrierName;
                    $currency = $carrier->currency;
                    $eta = $carrier->eta;
                    $amount = $carrier->amount;
                    $tax = $carrier->tax;

                    if(!empty($carrier->serviceProfile)){
                        $serviceProfileId = $carrier->serviceProfile->id;
                        $serviceProfileName = $carrier->serviceProfile->name;
                    }

                    $rates[] = array(
                        'id'    => $carrierProfileId . '_' . $serviceProfileId,
                        'label' => $carrierName . ' [' . $serviceProfileName . '] [' . $eta . '] [Tax: '  . $currency . ' $' . $tax . ' ]',
                        'cost'  => $amount,
                        'meta_data' => array(
							'shipping_method' => $carrierProfileId . '_' . $serviceProfileId,
						)
                    );
                }
            }
        }
        return $rates;
    }

    /**
     * Log exceptions.
     */
    public function bbam_log( $message = '' ) {}

    /**
     * Woo Logger
     * 
     */
    private function bba_woo_logger($logData, $label){
        $logger = wc_get_logger();
        $context = array( 'source' => 'bba-mastro-request-quote-payload-response' );
        $logger->debug("---------------------------------- $label ------------------------------------------", $context);
        $logger->debug(json_encode($logData), $context);
    }
}
