<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BBAM_Shipping_Calculator extends BBAM_Api{

    public function generateGetQuotesParams($package = []) {
        $shippingRules = new BBAM_ShippingRules();

        $params = [];
        $default = $shippingRules->getPackageSettings();    

        $params['channel'] = (string)$shippingRules->get_option('channel');
        $params['source'] = $shippingRules->getShippingOrigin();
        $params['destination'] = $shippingRules->getShippingDestination($package['destination']);
        $params['items'] = [];
        $params['pickupDetails'] = [
            'asap' => true,
            'pickupDate' => null,
            'pickupEarliestTime' => null,
            'pickupLatestTime' => null,
            'pickupTimezone' => get_option('gmt_offset')
        ];
        
        # todo: need to modify this code
        if(!isset($_REQUEST['product-id'])) {
            foreach($package['contents'] as $item) {
                $terms_list = wp_get_post_terms($item['product_id'], 'product_cat');
                $excluded = $this->is_in_excluded_categories($terms_list);
                
                if (!$excluded) {
                    $params['items'][] = $this->generateItemData($item, $default);
                }
            }
        } 
        else {
            $productId = absint($_REQUEST['product-id']);
            $terms_list = wp_get_post_terms($productId, 'product_cat');
            $excluded = $this->is_in_excluded_categories($terms_list);
            
            if (!$excluded) {
                $product = wc_get_product($productId);
                $params['items'][] = $this->generateItemData($product, $default);
            }
        }

        // todo: need to fix this one here
        $params['packages'] = [];
        foreach($package['contents'] as $item) {
            $terms_list = wp_get_post_terms($item['product_id'], 'product_cat');
            $excluded = $this->is_in_excluded_categories($terms_list);
            if($item['data']->needs_shipping() && !$excluded){
                $params['packages'][] = $this->generatePackageData($item, $default);
            }
        }
        return $params;
    }

    public function calculatePackaging($package = []) {
        $api = new BBAM_Api();
        $shippingRules = new BBAM_ShippingRules();
        $default = $shippingRules->getPackageSettings();    
        $payload = [];

        foreach($package['contents'] as $item) {
            $terms_list = wp_get_post_terms($item['product_id'], 'product_cat');
            $excluded = $this->is_in_excluded_categories($terms_list);

            if($item['data']->needs_shipping() && !$excluded){
                $payload[] = [
                    'id' => null,
                    'quantity' => (int) $item['quantity'], # todo: need to fix this one here
                    'length' => ( float ) $item['data']->get_length() ?: $default['length'],
                    'width' => ( float ) $item['data']->get_width() ?: $default['width'],
                    'height' => ( float ) $item['data']->get_height() ?: $default['height'],
                    'weight' => ( float ) $item['data']->get_weight() ?: $default['weight'],
                    'weightUnit' => 'Kilograms',
                    'measureUnit'=> 'CM',
                ];
            }
        }
        
        if (count($payload) < 1) {
            return [];
        }

        BBAM_Utils::logger($payload, 'CALCULATE API REQUEST PAYLOAD', 'bba-api-calculate-api-request');
        $response = $api->sendApiRequest('POST', 'packaging/packaging/calculate', [], $payload, true);
        BBAM_Utils::logger($response, 'CALCULATE API RESPONSE', 'bba-api-calculate-api-request');
        
        if (!is_array($response)) { return []; }
        $selectedPackaging = $response[0];
        BBAM_Utils::logger($selectedPackaging, 'RAW SELECTED PACKAGING DATA', 'bba-api-calculate-api-request');

        
        $packaging = [];
        foreach ($selectedPackaging->packaging as $pkg) {
            $packaging[] = [
                'length'   => ( float ) $pkg->length,
                'width'    => ( float ) $pkg->width,
                'height'   => ( float ) $pkg->height,
                'weight'   => ( float ) $pkg->maxWeight,
                'quantity' => ( int ) $pkg->qty,
                'weightUnit' => $pkg->weightUnit,
                'measureUnit' => $pkg->measureUnit,
                'contentWeight' => ( float ) $pkg->contentWeight
            ];
        }

        BBAM_Utils::logger($packaging, 'SELECTED PACKAGING', 'bba-api-calculate-api-request');
        return $packaging;
    }

    private function generateItemData($item, $default) {
        return [
            'quantity' => (int) $item['quantity'],
            'value' => (int) $item['data']->get_regular_price(),
            'length' => ( float ) $item['data']->get_length() ?: $default['length'],
            'width' => ( float ) $item['data']->get_width() ?: $default['width'],
            'height' => ( float ) $item['data']->get_height() ?: $default['height'],
            'weight' => ( float ) $item['data']->get_weight() ?: $default['weight'],
            "code" => "TST",
            'weightUnit'        => 'Kilograms',
            'measureUnit'       => 'CM'
        ];
    }

    private function is_in_excluded_categories($terms_list) {
        $excluded_cat_str = BBAM_Utils::getConfig('excluded_categories');
        $excluded_cat_array = explode(',', strtolower($excluded_cat_str));
        
        foreach ($terms_list as $category) {
            if (in_array(strtolower($category->name), $excluded_cat_array)) {
                return true;
            }
        }

        return false;
    }

    private function generatePackageData($item, $default){
        return [
            'length'   => ( float ) $item['data']->get_length() ?: $default['length'],
            'width'    => ( float ) $item['data']->get_width() ?: $default['width'],
            'height'   => ( float ) $item['data']->get_height() ?: $default['height'],
            'weight'   => ( float ) $item['data']->get_weight() ?: $default['weight'],
            'quantity' => ( int ) $item['quantity'],
            'weightUnit' => 'Kilograms',
            'measureUnit' => 'CM',
            'contentWeight' => ( float ) $item['data']->get_weight() ?: $default['weight']
        ];
    }

    public function getQuotes($params = []){
        if(BBAM_Utils::getConfig('enabled') == 'yes') {
            $api = new BBAM_Api();
			BBAM_Utils::logger($params, 'QUOTE REQUEST PAYLOAD', 'bba-api-quote-request-payload');

            if(BBAM_Utils::getConfig('use_new_api') == 'yes') {
                $response = $api->sendApiRequest('POST', 'carrier/quote-seq', [], $params, true);
                BBAM_Utils::logger($response, 'QUOTE RAW RESPONSE', 'bba-api-quote-raw-response');
                return $this->parseNewAPIQuotes($response);
            } else {
                $response = $api->sendApiRequest('POST', 'carrier/quote', [], $params, true);
                return $this->parseOldAPIQuotes($response);
            }
        }
    }

    private function parseNewAPIQuotes($response) {
        $rates = [];
        if (is_array($response)) {
            if (!empty($response)) {
                for ($i = 0; $i < count($response); $i++) {
                    $carrier = $response[$i];

                    $displayName = $carrier->name;
                    $carrierProfileId = $carrier->carrierProfileId;
                    $serviceProfileId = $carrier->serviceProfileId;
                    $eta = $carrier->eta;
                    $cost = $carrier->amount;
                    $tax = $carrier->tax;
                    $currency = $carrier->currency;

                    $label = $displayName.' ['.$eta.']'.'[Tax: '.$currency.' $'. $tax .' ]';

                    $rates[] = [
                        'id' => $carrierProfileId . '_' . $serviceProfileId,
                        'label' => $label,
                        'cost' => $cost,
                        'meta_data' => [
                            'shipping_method' => $carrierProfileId . '_' . $serviceProfileId,
                        ]
                    ];
                }
            }
        }

        return $rates;
    }

    private function parseOldAPIQuotes($response){
        $rates = [];
        if(is_array($response)) {
            if(!empty($response)){
                for ($i = 0; $i < count($response); $i++) {
                    $carrier = $response[$i];
                    $serviceProfileName = $carrier->serviceProfileName;
                    $displayName = $carrier->displayName;
                    $carrierProfileId = $carrier->carrierProfileId;
                    $serviceProfileId = $carrier->serviceProfileId;
                    $eta = $carrier->eta;
                    $cost = $carrier->amount;
                    $tax = $carrier->tax;
                    $currency = $carrier->currency;
                    
                    $label = $displayName ? $displayName : $serviceProfileName;
                    $label = $label.' ['.$eta.']'.'[Tax: '.$currency .' $'. $tax .' ]';

                    $rates[] = [
                        'id' => $carrierProfileId . '_' . $serviceProfileId,
                        'label' => $label,
                        'cost' => $cost,
                        'meta_data' => [
                            'shipping_method' => $carrierProfileId . '_' . $serviceProfileId,
                        ]
                    ];
                }
            }
        }

        return $rates;
    }
}