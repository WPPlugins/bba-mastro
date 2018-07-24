<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BBAMastro_Api Class.
 */
class BBAMastro_Api {

    /**
     * API Url.
     * @var string
     */
    // Filled from Woocommerce > Settings > Shipping > BBA Mastro > API Url Field
    protected $api_url;

    /**
     * Auth Url.
     * @var string
     */
    // Filled from Woocommerce > Settings > Shipping > BBA Mastro > Auth API Url Field
    protected $auth_url;

    /**
     * Access token.
     */
    protected $token;

    /**
     * curl_init().
     */
    protected $resource;

    /**
     * Start curl_init().
     */
    protected function bbam_get_resource() {

        if ( is_null( $this->resource ) ) {
            $this->resource = curl_init();
        }
        return $this->resource;
    }

    /**
     * Send request to the remote server.
     */
    public function bbam_write( $method, $url, $http_ver = '1.1', $headers = array(), $body = '' ) {

        $fullRequest = array(
            "Method" => $method,
            "url" => $url,
            "headers" => $headers,
            "body" => $body
        );

        $options = array(
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false
        );

        if ( $method == 'POST' ) {
            $options[CURLOPT_POST]       = true;
            $options[CURLOPT_POSTFIELDS] = $body;
        } elseif ( $method == 'GET' ) {
            $options[CURLOPT_HTTPGET] = true;
        }
        if ( is_array( $headers ) ) {
            $options[CURLOPT_HTTPHEADER] = $headers;
        }
        curl_setopt_array( $this->bbam_get_resource(), $options );
    }

    /**
     * Read response from server.
     *
     * @return string
     */
    public function bbam_read() {
        return curl_exec( $this->bbam_get_resource() );
    }

    /**
     * Close the connection to the server.
     */
    public function bbam_close() {
        curl_close( $this->bbam_get_resource() );
        $this->resource = null;
    }

    /**
     * Trim action.
     */
    public function bbam_trimAction( $action = '' ) {

        if ( $action ) {
            $action = '/' . trim( $action, '/' ) . '/';
        }

        return $action;
    }

    /**
     * Parse json-formatted response.
     */
    public function bbam_parse_json( $response ) {
        return json_decode( $response );
    }

    /**
     * Main entry.
     */
    public function bbam_dispatch( $method, $action, $queries = array(), $params = array(), $as_json = false ) {

        $this->api_url = bbamastro_config( 'api_url', null );

        $url = $this->api_url . $this->bbam_trimAction( $action );
        if ( ! empty( $queries ) ) {
            $url_query = bbamastro_create_url_query( $queries );
            $url = $url . '?' . implode( '&', $url_query );
        }
        
        $sanitizedParams = $this->cleanPayload($params);
        $headers = array( 'Authorization: Bearer ' . $this->bbam_getAccessToken() );
        array_push($headers, 'Origin: '.$this->setOriginValue());

        $body = '';
        if ( $as_json ) {
            array_push( $headers, 'Content-Type: application/json' );
            $body = json_encode( $sanitizedParams );
        }

        $this->bbam_write( $method, $url, '1.1', $headers, $body );
        $response = $this->bbam_read();
        $this->bbam_close();
        $result = $this->bbam_parse_json( $response );
        
        if ( isset($result->error)) {
            return "Couldn't dispatch action '" . $action . "'.";
        } else {
            return $result;
        }
    }

    /**
     * Access token.
     */
    public function bbam_getAccessToken() {
        if ( ! isset( $this->token ) ) {
            $this->token = $this->bbam_do_authorization();
        }
        return $this->token;
    }

    /**
     * Authorization.
     */
    public function bbam_do_authorization() {
        $headers = array();
        $client_id = "clientapp";
        $client_secret = "123456";
        $auth_code = "Basic Y2xpZW50YXBwOjEyMzQ1Ng==";

        $body = array(
            'username'      => bbamastro_config( 'acc_username', null ) ?: '',
            'password'      => bbamastro_config( 'acc_password', null ) ?: '',
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'grant_type'    => 'password',
			'reseller_id' => bbamastro_config('reseller_id', null) ? : ''
        );

        $this->auth_url = bbamastro_config( 'auth_url', null );
        if ( $auth_code ) {
            array_push( $headers, 'Authorization: ' . $auth_code );
            array_push($headers, 'Origin: '.$this->setOriginValue());
        }
		
        $this->bbam_write( 'POST', $this->auth_url, '1.1', $headers, $body );
        $response = $this->bbam_read();
        $this->bbam_close();
        $result = $this->bbam_parse_json( $response );

        if ( $result->access_token ) {
            return $result->access_token;
        } else {
            return "Couldn't do authorization to BBA Mastro";
        }
    }
    
    private function cleanPayload($payload){
        if(isset($payload['items'])){
            for($i = 0; $i < count($payload['items']); $i++) {
                $description = $payload['items'][$i]['description'];
                $payload['items'][$i]['description'] = trim(preg_replace('/\s\s+/',' ',strip_tags($description)));
                $payload['items'][$i]['sku'] = empty($payload['items'][$i]['sku']) ? $payload['items'][$i]['id'] : $payload['items'][$i]['sku'];
            }
            $sanitizedPayload = $payload;
            return $sanitizedPayload;
        }
        return $payload;
    }

    private function setOriginValue(){
        $originValue = "https://api-dev.bbamastro.com";
        $apiUrl = bbamastro_config('api_url', null);

        switch($apiUrl) {
            case "https://api-dev.bbamastro.com":
                $originValue = "https://dashboard-dev.bbamastro.com";
            break;
            case "https://api-staging.bbamastro.com": 
                $originValue = "https://dashboard-staging.bbamastro.com";
            break;
            case "https://api-demo.bbamastro.com": 
                $originValue = "https://dashboard-demo.bbamastro.com";
            break;
            case "https://api.bbamastro.com": 
                $originValue = "https://dashboard.bbamastro.com";
            break;
        }
        return $originValue;
    }
}
