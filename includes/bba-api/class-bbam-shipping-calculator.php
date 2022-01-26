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
            'measureUnit'       => 'CM',
            'currency'          => 'USD'
        ];
    }

    private function is_in_excluded_categories($terms_list) {
        $excluded_cat_str = BBAM_Utils::getConfig('excluded_categories'); // todo: need to change this one
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
            $response = $api->sendApiRequest('POST', 'carrier/quote', [], $params, true);
            return $this->parseQuotes($response);
        }
    }

    private function parseQuotes($response){
        $rates = [];
        if(is_array($response) || is_object($response)) {
            if(!empty($response)){
                foreach($response as $carrier) {
                    if(!is_string($carrier) && !is_null($carrier)) {
                        $carrierProfileId = $carrier->carrierProfileId;
                        $carrierName = $carrier->carrierName;
                        $currency = $carrier->currency;
                        $eta = $carrier->eta;
                        $amount = $carrier->amount;
                        $tax = $carrier->tax;


                        $serviceProfileId = $carrier->serviceProfileId;
                        $serviceProfileName = $carrier->serviceProfileName;
						$label = $carrierName;
						
						if ($carrier->operation) {
							$label = $label . '['.$carrier->operation->name.']';
						}
						
						$label = $label . ' [' . $serviceProfileName . '] [' . $eta . '] [Tax: '  . $currency . ' $' . $tax . ' ]';

                        $rates[] = [
                            'id'    => $carrierProfileId . '_' . $serviceProfileId,
                            'label' => $label,
                            'cost'  => $amount,
                            'meta_data' => array(
                                'shipping_method' => $carrierProfileId . '_' . $serviceProfileId,
                            )
                        ];
                    }
                }
            }
        }
        return $rates;
    }
}