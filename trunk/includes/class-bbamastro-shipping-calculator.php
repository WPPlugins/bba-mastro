<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BBAMastro_Shipping_Calculator Class.
 */
class BBAMastro_Shipping_Calculator extends WC_Cart {

    /**
     * To override parent.
     */
    public function __construct() {
        $this->prices_include_tax    = wc_prices_include_tax();
        $this->round_at_subtotal     = get_option( 'woocommerce_tax_round_at_subtotal' ) == 'yes';
        $this->tax_display_cart      = get_option( 'woocommerce_tax_display_cart' );
        $this->dp                    = wc_get_price_decimals();
        $this->display_totals_ex_tax = $this->tax_display_cart == 'excl';
        $this->display_cart_ex_tax   = $this->tax_display_cart == 'excl';
        $this->cart_contents         = array();
    }

    /**
     * To override parent.
     */
    public function get_cart() {
        return array_filter( (array) $this->cart_contents );
    }

    /**
     * To override parent.
     */
    public function set_session() {}

    /**
     * Get shipping rates
     */
    public function bbam_getRates() { 
        $country = $_REQUEST['country'];
        $postcode = $_REQUEST['postcode'];

        $product_id = absint( $_REQUEST['product-id'] );
        $product = wc_get_product( $product_id );

        $rates = array();
        if ( ! $product ) {
            return $rates;
        }

        $args = array(
            'variation'    => array(),
            'quantity'     => 1,
            'data'         => $product
        );

        if ( $product->is_type( 'variation' ) ) {
            $args['variation_id'] = $product_id;
        } else {
            $args['product_id'] = $product_id;
        }
        
        $this->cart_contents[] = $args;
        if ( $this->needs_shipping() ) {
            $packages = $this->get_shipping_packages();
            $packages[0]['destination']['country'] = $country;
            $packages[0]['destination']['postcode'] = $postcode;

            WC()->shipping->calculate_shipping( $packages );

            $results = WC()->shipping->get_packages();
            $rates = $results[0]['rates'];
        }

        WC()->shipping->reset_shipping();
        return $rates;
    }
}
