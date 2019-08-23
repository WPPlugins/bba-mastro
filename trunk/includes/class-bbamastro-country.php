<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BBAMastro_Country Class.
 */
class BBAMastro_Country {

    protected $wc_countries;

    public function __construct() {
        $this->wc_countries = new WC_Countries();
    }

    public function bbam_get_countries() {
        if ( get_option( 'woocommerce_allowed_countries' ) == 'specific' ) {
            return $this->wc_countries->get_allowed_countries();
        } else {
            return $this->wc_countries->get_countries();
        }
    }

    /**
     * Get country code.
     */
    public function bbam_code() {
        return array_keys( $this->bbam_options() );
    }

    /**
     * Get country as options.
     */
    public function bbam_options( $to_json = false ) {
        $countries = $this->bbam_get_countries();
        if ( $to_json ) {
            $results = array();
            foreach( $countries as $id => $text ) {
                $obj = new stdClass();
                $obj->id   = $id;
                $obj->text = html_entity_decode($text);
                $results[] = $obj;
            }
            return $results;
        }
        return $countries;
    }
}
