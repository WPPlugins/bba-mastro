<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bbamastro_add_taxonomy($taxonomy) {

    global $wpdb;
    

    $attribute_taxonomy_name =  wc_attribute_taxonomy_name($taxonomy);

   if (!taxonomy_exists( $attribute_taxonomy_name)) {
       
        $attribute = array();

        $attribute['attribute_label'] = "Country of Manufacturer";
        $attribute['attribute_name'] = $taxonomy;
        $attribute['attribute_type'] = "select";
        $attribute['attribute_orderby'] = "name";
        $attribute['attribute_public'] = 0;

        $result = $wpdb->insert( $wpdb->prefix . 'woocommerce_attribute_taxonomies', $attribute );
        if($result !== false){
            do_action( 'woocommerce_attribute_added', $wpdb->insert_id, $attribute );
            flush_rewrite_rules();
            delete_transient( 'wc_attribute_taxonomies' );
        }
        
    
    }
    

}

function bbamastro_add_terms($taxonomy){

    
    
     $taxonomy =  wc_attribute_taxonomy_name($taxonomy);
    if (taxonomy_exists($taxonomy)) {

        $queries = array(
            'type' => "select",
            'orderby'    => "name",
            'slug'    => "manufacturer"
        );
   
        $api = new BBAMastro_Api();
        $terms = $api->bbam_dispatch( 'GET', 'address/country', $queries );
 
        if(!empty($terms) && is_array($terms)){

       

   
        foreach ($terms as $term) {

            if ( !strlen(trim($term->name)) || !strlen(trim($term->code))){
                continue;
            }
            
            if ( !term_exists($term->code, $taxonomy) ) {
                wp_insert_term($term->code,$taxonomy, array("description"=> $term->code,"slug"=>$term->code));
            }
          
        }
        //remove action from wp_loaded to make sure  this function will be executed once.
        remove_action("wp_loaded", array('BBAMastro','bbam_add_terms'),10);
       }
    }
    
    

}


/**
 * Get template and including the file.
 *
 * @access public
 * @param string $template_name
 * @param array $args (default: array())
 * @param string $template_path (default: '')
 * @param string $default_path (default: '')
 */
function bbamastro_get_template( $template_name, $args = array(), $template_path = '', $default_path = '' ) {

    if ( $args && is_array( $args ) ) {
        extract( $args );
    }

    $located = bbamastro_locate_template( $template_name, $template_path, $default_path );

    if ( ! file_exists( $located ) ) {
        return;
    }

    // Allow 3rd party plugin filter template file from their plugin.
    $located = apply_filters( 'bbamastro_get_template', $located, $template_name, $args, $template_path, $default_path );

    do_action( 'bbamastro_before_template_part', $template_name, $template_path, $located, $args );

    include( $located );

    do_action( 'bbamastro_after_template_part', $template_name, $template_path, $located, $args );

}

/**
 * Locate a template and return the path for inclusion.
 *
 * @access public
 * @param string $template_name
 * @param string $template_path (default: '')
 * @param string $default_path (default: '')
 * @return string
 */
function bbamastro_locate_template( $template_name, $template_path = '', $default_path = '' ) {

    if ( ! $template_path ) {
        $template_path = BBAM()->bbam_template_path();
    }

    if ( ! $default_path ) {
        $default_path = BBAM()->bbam_plugin_path() . '/templates/';
    }

    // Look within passed path within the theme - this is priority.
    $template = locate_template(
        array(
            trailingslashit( $template_path ) . $template_name,
            $template_name
        )
    );

    // Get default template
    if ( ! $template ) {
        $template = $default_path . $template_name;
    }

    // Return what we found.
    return apply_filters( 'bbamastro_locate_template', $template, $template_name, $template_path );
}

/**
 * Get a config value.
 */
function bbamastro_config( $key, $default = null ) {

    $settings = get_option( 'woocommerce_bbamastro_rules_settings', null );
    
    if ( is_array( $settings ) && ! empty( $settings[$key] ) ) {
        return $settings[$key];
    }
    return $default;
}

/**
 * Shipping rules.
 */
function bbamastro_shipping_methods( $methods ) {
    array_push( $methods, 'BBAMastro_Shipping_Rules' );
    return $methods;
}

/**
 * Include scripts in admin.
 */
function bbamastro_admin_scripts() {

    $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
    $countries = new BBAMastro_Country();

    wp_register_script( 'bbamastro-address-api', BBAM()->bbam_plugin_url() . '/assets/js/admin/address-api' . $suffix . '.js', array( 'jquery', 'select2' ), BBAMASTRO_VERSION );
    wp_localize_script( 'bbamastro-address-api', 'bbamastro', array(
        'countries' => $countries->bbam_options( true ),
        'settings'  => array(
            'country'  => bbamastro_config( 'warehouse_country' ),
            'postcode' => bbamastro_config( 'warehouse_postcode' )
        )
    ) );
    wp_enqueue_script( 'bbamastro-address-api' );
}

/**
 * Include scripts in frontend.
 */
function bbamastro_frontend_scripts() {

    $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
    $shipping_enabled = BBAM()->bbam_woocommerce_enabled() && BBAM()->bbam_shipping_enabled();
    $countries = new BBAMastro_Country();
    $countries_options = $countries->bbam_options( true );
    
    //Custom BBA Styles
    wp_register_style( 'bbastyles',BBAM()->bbam_plugin_url() . '/assets/css/bbastyle.css', array(), '' );
    wp_enqueue_style( 'bbastyles' );

    if ( is_product() && $shipping_enabled ) {
        wp_register_style( 'select2_styles', WC()->plugin_url() . '/assets/css/select2.css', array(), '3.5.4' );
        wp_enqueue_style( 'select2_styles' );
        
        
        wp_register_script( 'select2', WC()->plugin_url() . '/bbamastro/assets/js/select2/select2' . $suffix . '.js', array( 'jquery' ), '3.5.4' );
        wp_register_script( 'bbamastro-address-api', BBAM()->bbam_plugin_url() . '/assets/js/frontend/address-api' . $suffix . '.js', array( 'jquery', 'select2' ), BBAMASTRO_VERSION );
        wp_localize_script( 'bbamastro-address-api', 'bbamastro', array(
            'ajax_url'  => admin_url( 'admin-ajax.php' ),
            'countries' => $countries_options
        ) );
       
        wp_enqueue_script( 'bbamastro-address-api' );
    }

    // Load script in Account Page > Edit Address as well
    if(is_account_page()){
        wp_register_style( 'select2_styles', WC()->plugin_url() . '/assets/css/select2.css', array(), '3.5.4' );
        wp_enqueue_style( 'select2_styles' );
        
        wp_register_script( 'select2', WC()->plugin_url() . '/bbamastro/assets/js/select2/select2' . $suffix . '.js', array( 'jquery' ), '3.5.4' );
        wp_register_script( 'bbamastro-address-api', BBAM()->bbam_plugin_url() . '/assets/js/frontend/address-api' . $suffix . '.js', array( 'jquery', 'select2' ), BBAMASTRO_VERSION );
        wp_localize_script( 'bbamastro-address-api', 'bbamastro', array(
            'ajax_url'  => admin_url( 'admin-ajax.php' ),
            'countries' => $countries_options
        ) );
       
        wp_enqueue_script( 'bbamastro-address-api' );
    }
}

/**
 * Include shipping calculator template.
 */
function bbamastro_shipping_calculator() {
    bbamastro_get_template( 'shipping/calculator.php' );
}

/**
 * Search for postcode.
 */
function bbamastro_search_postcode() {

    $queries = array(
        'country' => $_REQUEST['country'],
        'code'    => $_REQUEST['postcode'],
        'size'    => 50
    );

    $page = (int) $_REQUEST['page'] - 1;
    if ( $page ) {
        $queries['page'] = $page;
    }
   
   
    $api = new BBAMastro_Api();
    $response = $api->bbam_dispatch( 'GET', 'address/location', $queries );

    wp_send_json( bbamastro_parse_search_postcode( $response ) );
}

/**
 * Parse postcode search result.
 */
function bbamastro_parse_search_postcode( $response ) {

    $result = array( 'items' => array(), 'total_count' => 0,"pagination" => array(
        "more" => (!$response->last)
      ));

    if ( $response->totalElements > 0 ) {
        $result['total_count'] = (int) $response->totalElements;
    }

    $exist = array();
    $zips = array();
    if ( count( $response->content ) > 0 ) {
        foreach( $response->content as $location ) {
            if ( ! isset( $exist[ $location->code ] ) ) {
                array_push($zips, $location->code);
                $exist[ $location->code ] = true;
            }
        }
    }

    sort($zips);

    foreach ($zips as $zip) {
        $obj = new stdClass();
        $obj->id   = $zip;
        $obj->text = $zip;
        $result['items'][] = $obj;        
    }

    

    return $result;
}

/**
 * Search for city.
 */
function bbamastro_search_city() {

    $queries = array(
        'country' => $_REQUEST['country'],
        'code'    => $_REQUEST['postcode'],
        'name'    => $_REQUEST['city'],
        'size'    => 50
    );

    $page = (int) $_REQUEST['page'] - 1;
    if ( $page ) {
        $queries['page'] = $page;
    }

    $api = new BBAMastro_Api();
    $response = $api->bbam_dispatch( 'GET', 'address/location', $queries );
    wp_send_json( bbamastro_parse_search_city( $response ) );
}

/**
 * Parse city search result.
 */
function bbamastro_parse_search_city( $response ) {

    $result = array( 'city' => array(), 'state' => array(),"pagination" => array(
        "more" => (!$response->last)
      ) );

    $exist = array();
    if ( count( $response->content ) > 0 ) {
        foreach( $response->content as $location ) {
            if ( ! isset( $exist[ $location->name ] ) ) {
                $obj = new stdClass();
                $obj->id   = $location->name;
                $obj->text = $location->name;
                $result['city'][] = $obj;
                $exist[ $location->name ] = true;

                if ( $location->state && ! isset( $exist[ $location->state->name ] ) ) {
                    $obj2 = new stdClass();
                    $obj2->id   = $location->state->name;
                    $obj2->text = $location->state->name;
                    $result['state'][] = $obj2;
                    $exist[ $location->state->name ] = true;
                }
            }
        }
    }

    return $result;
}

/**
 * Key-value pairs of url query.
 */
function bbamastro_create_url_query( $vars = array() ) {

    $result = array();
    foreach( $vars as $name => $value ) {
        $result[] = $name . '=' . $value;
    }
    return $result;
}

/**
 * Estimate shipping cost.
 */
function bbamastro_estimate_shipping() {

    $calculator = new BBAMastro_Shipping_Calculator();

    ob_start();

    bbamastro_get_template( 'shipping/calculator/result.php', array( 'rates' => $calculator->bbam_getRates() ) );

    echo ob_get_clean();

    if ( defined( 'DOING_AJAX' ) && DOING_AJAX )
        wp_die();
    else
        die;
}

/**
 * Rearrange the order of fields.
 */
function bbamastro_address_fields( $fields ) {

    $keys = array(
        'first_name', 'last_name', 'company', 'address_1', 'address_2','country', 'postcode', 
        'city', 'state'
    );

    $result = array();
    foreach ( $keys as $key ) {
        if ( ! empty( $fields[$key] ) ) {
            $result[$key] = $fields[$key];
            if ( $key == 'country' || $key == 'postcode' || $key == 'city' || $key == 'state' ) {
                $result[$key]['class'] = array( 'form-row-wide', 'address-field' );
                $result[$key]['type'] = 'bbamastro_select2';

                unset($result[$key]['clear']);
                unset($result[$key]['validate']);

                if ( $key == 'state' ) {
                    unset($result[$key]['required']);
                }
            }
        }
    }
    return $result;
}

/**
* Generate tempalate for BBA Location Widget
*/
function bbamastro_select2_fields( $field, $key, $args, $value ) {
    $data = "";
    
    $customer = json_decode(WC()->customer);

    // Added conditional for only Account Page. 
    if(is_account_page()){

        switch ($key) {
            case 'billing_country':
                $data = $customer->billing->country;
                break;
            case 'shipping_country':
                $data = $customer->shipping->country;
                break;
            case 'billing_postcode':
                $data = $customer->billing->postcode;
                break;
            case 'shipping_postcode':
                $data = $customer->shipping->postcode;
                break;
            case 'billing_city':
                $data = $customer->billing->city;
                break;
            case 'shipping_city':
                $data = $customer->shipping->city;
                break;
            case 'billing_state':
                $data = $customer->billing->state;
                break;
            case 'shipping_state':
                $data = $customer->shipping->state;
                break;
            
            default:
                $data = '';
                break;
        }
    }else{ 
        if($key === "billing_country" || $key === "shipping_country"){

             $data = WC()->customer->get_billing_country();

             if(!empty(WC()->customer->get_shipping_country())){

                  $data = WC()->customer->get_shipping_country();

             }

        }else  if($key === "billing_postcode" || $key === "shipping_postcode"){

            $data = WC()->customer->get_billing_postcode();
            
            if(!empty(WC()->customer->get_shipping_postcode())){

                $data = WC()->customer->get_shipping_postcode();

            }
           

        }else  if($key === "billing_city" || $key === "shipping_city"){

            $data = WC()->customer->get_billing_city();
            
            if(!empty(WC()->customer->get_shipping_city())){

                $data = WC()->customer->get_shipping_city();
                
            }

        }
        else  if($key === "billing_state" || $key === "shipping_state"){

            $data = WC()->customer->get_billing_state();
            
            if(!empty(WC()->customer->get_shipping_state())){
                $data = WC()->customer->get_shipping_state();
            }

        }
    }

    $field = '<select '.(!empty($data) ? "data-option='".$data."'" : "").' class="input-text ' . esc_attr( implode( ' ', $args['input_class'] ) ) .'" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '"  ></select>';
    if ( ! empty( $field ) ) {
        $field_html = '';
        $required = $args['required'] ? ' <abbr class="required" title="' . esc_attr__( 'required', 'woocommerce'  ) . '">*</abbr>' : '';

        if ( $args['label'] && 'checkbox' != $args['type'] ) {
            $field_html .= '<label for="' . esc_attr( $args['id'] ) . '" class="' . esc_attr( implode( ' ', $args['label_class'] ) ) .'">' . $args['label'] . $required . '</label>';
        }

        $field_html .= $field;
        $container_class = 'form-row ' . esc_attr( implode( ' ', $args['class'] ) );
        $container_id = esc_attr( $args['id'] ) . '_field';

        $field_container = '<p class="form-row %1$s" id="%2$s">%3$s</p>';
        $field = sprintf( $field_container, $container_class, $container_id, $field_html );
    }

    return $field;

}

/**
 * Build webhook payload
 * 
 */
function bbamastro_woocommerce_webhook_payload($payload, $resource, $resource_id, $id) {
    if($payload["order_key"]){
        for($i = 0; $i < count($payload['line_items']); $i++){
            if(empty($payload['line_items'][$i]['sku'])){
                $payload['line_items'][$i]['sku'] =  $payload['line_items'][$i]["product_id"];
            }
        }
    } else if($payload["name"]) {
        if(empty($payload['sku'])){
            $payload["sku"] =  $payload["id"];
        }
    }
    return $payload;
}

/**
 * Build rest api response for products
 */
function bbamastro_prepare_product( $response, $product, $request) {
    $data = $response->get_data();
	if(empty($data['sku'])){
       $data['sku'] = $data['id'];
       $response->set_data($data);
    }
	return $response;
}

/**
*  for testing
*/
function wc_reg_for_menus( $register, $name = '' ) {
     if ( $name == 'pa_size' ) $register = true;
     return $register;
}
