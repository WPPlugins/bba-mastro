<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BBAM_Utils {
    public static function getConfig($key, $default = null){
        $settings = get_option('woocommerce_bbamastro_rules_settings', null);
        if(is_array($settings) && !empty($settings[$key])) {
            return $settings[$key];
        }
        return $default;
    }

    public static function setHeaderOriginValue() { 
        $apiUrl = self::getConfig('api_url', null);
        switch($apiUrl) {
            case "https://api-dev.bbamastro.com":
                return "https://dashboard-dev.bbamastro.com";
                break;
            case "https://api-staging.bbamastro.com": 
                return "https://dashboard-staging.bbamastro.com";
                break;
            case "https://api-demo.bbamastro.com": 
                return "https://dashboard-demo.bbamastro.com";
                break;
            case "https://api.bbamastro.com": 
                return "https://dashboard.bbamastro.com";
                break;
        }
    }

    public static function trimAction($action){
        if ( $action ) {
            $action = '/' . trim( $action, '/' ) . '/';
        }
        return $action;
    }

    public static function createUrlQuery($vars = []) {
        $result = [];
        foreach($vars as $name=>$value) {
            $result[] = $name.'='.$value;
        }
        return $result;
    }

    public static function woocommerceEnabled(){
        return class_exists('woocommerce') ? true : false;
    }

    public static function addTaxonomy($taxonomy){
        global $wpdb;
        $attributeTaxonomyName =  wc_attribute_taxonomy_name($taxonomy);

        if(!taxonomy_exists($attributeTaxonomyName)) {
            $attribute = [];
            $attribute['attribute_label'] = "Country of Manufacturer";
            $attribute['attribute_name'] = $taxonomy;
            $attribute['attribute_type'] = "select";
            $attribute['attribute_orderby'] = "name";
            $attribute['attribute_public'] = 0;

            $result = $wpdb->insert($wpdb->prefix . 'woocommerce_attribute_taxonomies', $attribute);
            if($result !== false) {
                do_action( 'woocommerce_attribute_added', $wpdb->insert_id, $attribute );
                flush_rewrite_rules();
                delete_transient( 'wc_attribute_taxonomies' );
            } else {
                // TODO: LOG ERROR 
            }
        }
    }

    public static function addTerms($taxonomy){
        $taxonomy =  wc_attribute_taxonomy_name($taxonomy);
        if(taxonomy_exists($taxonomy)) {
            $queries = [
                'type' => 'select',
                'orderBy' => 'name', 
                'slug' => 'manufacturer'
            ];
            
            $api = new BBAM_Api();
            $terms = $api->sendApiRequest('GET', 'address/country', $queries);
            if(!empty($terms) && is_array($terms)) {
                foreach($terms as $term) {
                    if(!strlen(trim($term->name)) || !strlen(trim($term->code))){
                        continue;
                    }

                    if(!term_exists($term->code, $taxonomy)) {
                        wp_insert_term($term->code,$taxonomy, array("description"=> $term->code,"slug"=>$term->code));
                    }
                }
                //TODO: NEED TO RESEARCH ON THIS ONE
                //remove action from wp_loaded to make sure  this function will be executed once.
                //remove_action("wp_loaded", array('BBAMastro','bbam_add_terms'),10);
            }
        }
    }

    public static function checkRequestType($type){
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

    public static function defineConstantIfNotExists($name, $value){
        if(!defined($name)) {
            define($name, $value);
        }
    }

    public static function includeBbaClass() {
        require_once "class-bbam-api.php";
        require_once "class-bbam-shipping-rules.php";
        require_once "class-bbam-install.php";
        require_once "class-bbam-country.php";
        require_once "class-bbam-custom-order-fields.php";
        require_once "class-bbam-admin-custom-order-fields-shop-order.php";
        require_once "class-bbam-tracking-id-and-consignment-id-fields.php";
        require_once "class-bbam-admin-custom-order-fields-meta-box.php";
    }

    public static function logger($logData, $label, $source, $varDump = false){
        $logger = wc_get_logger();
        $context = ['source' => $source];
        $logger->debug("--------------------------$label--------------------------------", $context);
        $logger->debug($varDump ? var_dump($logData) : json_encode($logData), $context);
    }

    public static function prepareProducts($response) {
        $data = $response->get_data();
        if(empty($data['sku'])){
            $data['sku'] = $data['id'];
            $response->set_data($data);
        }
        return $response;
    }

    public static function generateWebhookPayload($payload){
        if($payload['order_key']){
            $shippingData = $payload['shipping_lines'][0]['meta_data'][0];
            self::logger($shippingData, 'Shipping Data', 'bba-webhook-payload');

            if(is_object($shippingData)) {
                $shipping = $shippingData->get_data();
                $payload['shipping_lines'][0]['method_id'] = $shipping['value'];
            } else {
                $payload['shipping_lines'][0]['method_id'] = $shippingData['value'];
            }
        }
        else if($payload['name']){
            if(empty($payload['sku'])){
                $payload['sku'] = $payload['id'];
            }
        } 
        self::logger($shippingData, 'Webhook Payload', 'bba-webhook-payload');
        return $payload;
    }
}