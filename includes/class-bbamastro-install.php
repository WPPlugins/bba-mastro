<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BBAMastro_Install Class.
 */
class BBAMastro_Install {

    /**
     * Hook in tabs.
     */
    public static function bbam_init() {
        
        add_filter( 'plugin_action_links_' . BBAMASTRO_PLUGIN_BASENAME, array( __CLASS__, 'bbam_plugin_action_links' ) );
        add_filter( 'plugin_row_meta', array( __CLASS__, 'bbam_plugin_row_meta' ), 10, 2 );

    }

    /**
     * Show action links on the plugin screen.
     *
     * @param	mixed $links Plugin Action links
     * @return	array
     */
    public static function bbam_plugin_action_links( $links ) {

        if ( BBAM()->bbam_woocommerce_enabled() ) {
            $action_links = array(
                'settings' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=shipping&section=bbamastro_shipping_rules' ) . '" title="' . esc_attr( __( 'View Settings', 'bbamastro' ) ) . '">' . __( 'Settings', 'bbamastro' ) . '</a>',
            );
            return array_merge( $action_links, $links );
        }

        return $links;
    }

    /**
     * Show row meta on the plugin screen.
     *
     * @param	mixed $links Plugin Row Meta
     * @param	mixed $file  Plugin Base file
     * @return	array
     */
   public static function bbam_plugin_row_meta( $links, $file ) {

        if ( $file == BBAMASTRO_PLUGIN_BASENAME ) {
            $row_meta = array(
                'Quickstart Guide'    => '<a href="' . esc_url( apply_filters( 'bbamastro_docs_url', 'https://bbamastro.atlassian.net/wiki/download/attachments/363561064/BBA_Woocommerce_wordpress_QuickStartGuide_final.pdf?version=1&modificationDate=1522813629916&cacheVersion=1&api=v2&download=true' ) ) . '" title="' . esc_attr( __( 'View Documentation', 'bbamastro' ) ) . '">' . __( 'Quickstart Guide', 'bbamastro' ) . '</a>'
            );

            return array_merge( $links, $row_meta );
        }

        return (array) $links;
    }
}

BBAMastro_Install::bbam_init();
