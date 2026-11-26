<?php 
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BBAM_Api extends WC_Cart {
    protected $token;
    protected $apiUrl;
    protected $authUrl;
    protected $cURLHandle;

    private $clientId = "clientapp";
    private $clientSecret = "123456";
    private $authCode = "Basic Y2xpZW50YXBwOjEyMzQ1Ng==";

    /**
     * Per-request timing of every BBA backend call, e.g.
     * [ ['label' => 'auth', 'ms' => 123.4], ['label' => 'carrier/quote', 'ms' => 456.7] ]
     *
     * @var array
     */
    public static $apiTimings = [];

    public function __construct(){
        $this->authUrl = BBAM_Utils::getConfig('auth_url', null);
        $this->apiUrl  = BBAM_Utils::getConfig('api_url', null);
    }

    public static function resetTimings(){
        self::$apiTimings = [];
    }

    public static function recordTiming($label, $milliseconds){
        self::$apiTimings[] = [
            'label' => (string) $label,
            'ms'    => round((float) $milliseconds, 1),
        ];
    }

    public static function getTimings(){
        return self::$apiTimings;
    }
    
    protected function generateCurlRequest($method, $url, $body, $headers = []){
        $options = [
            CURLOPT_URL             => $url,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_HEADER          => false,
            CURLOPT_CONNECTTIMEOUT  => 5,
            CURLOPT_TIMEOUT         => (int) apply_filters('bbam_api_timeout', 25),
            CURLOPT_ENCODING        => '', // advertise gzip/deflate support
        ];

        if (defined('CURL_HTTP_VERSION_2TLS')) {
            $options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_2TLS;
        }

        switch($method) {
            case 'POST': 
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = $body;
                break;
            case 'GET': 
                $options[CURLOPT_HTTPGET] = true;
                break;
        }

        if(is_array($headers)) {
            $options[CURLOPT_HTTPHEADER] = $headers;
        }

        curl_setopt_array( $this->getCURLHandle(), $options );
    }

    private function getCURLHandle(){
        if(!isset($this->cURLHandle) || is_null($this->cURLHandle)) {
            $this->cURLHandle = curl_init();
        }
        return $this->cURLHandle;
    }

    private function executeCurlSession(){
        return curl_exec( $this->getCURLHandle() );
    }

    private function closeCURLSession(){
        curl_close($this->getCURLHandle());
        $this->cURLHandle = null;
    }

    /**
     * Access token cached for the lifetime of the current PHP request, so a
     * single quote refresh (packaging + quote calls) authenticates at most once.
     *
     * @var string|null
     */
    private static $tokenMemo = null;

    protected function getAccessToken(){
        if (self::$tokenMemo) {
            return self::$tokenMemo;
        }

        $token   = BBAM_Token::get_token();
        $expires = (int) BBAM_Token::get_token_created_time();

        // The stored value is an absolute unix timestamp for when the token
        // dies. Refresh 2 minutes early so we never send one mid-expiry.
        if ($token && $expires > time() + 120) {
            self::$tokenMemo = $token;
            return $token;
        }

        return $this->refreshToken();
    }

    /**
     * Force a fresh authentication and persist the token alongside an absolute
     * expiry timestamp. Safe to call from WP-Cron to keep the token warm so
     * live checkout requests never pay the auth round-trip.
     *
     * @return string|null
     */
    public function refreshToken(){
        $tokenObject = $this->authenticate();

        if (!$tokenObject || empty($tokenObject->access_token)) {
            BBAM_Token::save_token(false);
            BBAM_Token::update_token_created_time(0);
            self::$tokenMemo = null;
            return null;
        }

        $ttl = isset($tokenObject->expires_in) ? (int) $tokenObject->expires_in : 3000;
        BBAM_Token::save_token($tokenObject->access_token);
        BBAM_Token::update_token_created_time(time() + $ttl);
        self::$tokenMemo = $tokenObject->access_token;

        return $tokenObject->access_token;
    }

    
    public function authenticate() {
        $headers = [];
        $body = [
            'username'      => BBAM_Utils::getConfig('acc_username', null) ?: '',
            'password'      => BBAM_Utils::getConfig('acc_password', null) ?: '',
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type'    => 'password',
            'reseller_id'   => BBAM_Utils::getConfig('reseller_id', null) ?: ''
        ];

        $headers[] = 'Authorization: ' . $this->authCode;
        $headers[] = 'Origin: ' . BBAM_Utils::setHeaderOriginValue();

        $logger = wc_get_logger();
        $context = ['source' => 'auth-debug'];

        $logger->info('=== AUTH REQUEST START ===', $context);
        $logger->info('URL: ' . $this->authUrl, $context);
        $logger->info('Payload: ' . print_r($body, true), $context);

        $this->generateCurlRequest('POST', $this->authUrl, $body, $headers);

        $requestStart = microtime(true);
        $response = $this->executeCurlSession();
        self::recordTiming('auth', (microtime(true) - $requestStart) * 1000);

        // Get cURL information before closing
        $httpCode  = curl_getinfo($this->getCURLHandle(), CURLINFO_HTTP_CODE);
        $curlError = curl_error($this->getCURLHandle());

        $this->closeCURLSession();

        $logger->info('HTTP Code: ' . $httpCode, $context);
        $logger->info('cURL Error: ' . ($curlError ?: 'None'), $context);
        $logger->info('Raw Response: ' . $response, $context);

        if ($response === false || empty($response)) {
            $logger->error('Authentication failed. Empty response from API.', $context);
            return null;
        }

        $result = json_decode($response);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $logger->error('JSON Decode Error: ' . json_last_error_msg(), $context);
            return null;
        }

        $logger->info('Decoded Response: ' . print_r($result, true), $context);

        if (
            $result &&
            is_object($result) &&
            isset($result->access_token)
        ) {
            $logger->info('Authentication successful.', $context);
            return $result;
        }

        if ($result && isset($result->error)) {
            $logger->error('API Error: ' . $result->error, $context);

            if (isset($result->error_description)) {
                $logger->error('Description: ' . $result->error_description, $context);
            }
        }

        BBAM_Utils::logger($response, 'AUTHENTICATION ERROR', 'bba-api-request-error');

        return null;
    }

    public function sendApiRequest($method, $action, $queries = [], $params = [], $asJson = false){
        $url = $this->apiUrl.BBAM_Utils::trimAction($action);
        if(!empty($queries)) {
            $urlQuery = BBAM_Utils::createUrlQuery($queries);
            $url = $url.'?'.implode('&', $urlQuery);
        }

        $headers = ['Authorization: Bearer '.$this->getAccessToken()];
        array_push($headers, 'Origin: '.BBAM_Utils::setHeaderOriginValue());
		
        $body = '';
        if($asJson){
            array_push( $headers, 'Content-Type: application/json' );
            $body = json_encode($params);
        }

        $this->generateCurlRequest($method, $url, $body, $headers);
        $requestStart = microtime(true);
        $response = $this->executeCurlSession();
        self::recordTiming(trim($action, '/'), (microtime(true) - $requestStart) * 1000);
        $this->closeCURLSession();
        $result = json_decode($response);

        if(isset($result->error)) {
            BBAM_Utils::logger($result, 'API REQUEST ERROR', 'bba-api-request-error');
            return null; // ERROR OCCURED
        }

        return $result; 
    }
}