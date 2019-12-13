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

        if(!isset($_REQUEST['product-id'])) {
            foreach($package['contents'] as $item) {
                $params['items'][] = $this->generateItemData($item, $default);
            }
        } 
        else {
            $productId = absint($_REQUEST['product-id']);
            $product = wc_get_product($productId);
            $params['items'][] = $this->generateItemData($product, $default);
        }

        $params['packages'] = [];
        foreach($package['contents'] as $item) {
            if($item['data']->needs_shipping()){
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
        BBAM_Utils::logger($params, 'GET QUOTES PARAMS', 'bba-get-quotes-request');
        if(BBAM_Utils::getConfig('enabled') == 'yes') {
            $api = new BBAM_Api();
            $response = $api->sendApiRequest('POST', 'carrier/quote', [], $params, true);
            //BBAM_Utils::logger($response, 'GET QUOTES RESPONSE', 'bba-get-quotes-request');
            return $this->parseQuotes($response);
        }
    }

    private function parseQuotes($response){
        $rates = [];
        if(is_array($response) || is_object($response)) {
            if(!empty($response)){
                foreach($response as $carrier) {
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

                    $rates[] = [
                        'id'    => $carrierProfileId . '_' . $serviceProfileId,
                        'label' => $carrierName . ' [' . $serviceProfileName . '] [' . $eta . '] [Tax: '  . $currency . ' $' . $tax . ' ]',
                        'cost'  => $amount,
                        'meta_data' => array(
							'shipping_method' => $carrierProfileId . '_' . $serviceProfileId,
						)
                    ];
                }
            }
        }
        return $rates;
    }
}