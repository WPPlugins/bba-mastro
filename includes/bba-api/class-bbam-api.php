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

    public function __construct(){
        $this->authUrl = BBAM_Utils::getConfig('auth_url', null);
        $this->apiUrl  = BBAM_Utils::getConfig('api_url', null);
    }
    
    protected function generateCurlRequest($method, $url, $body, $headers = []){
        $options = [
            CURLOPT_URL => $url, 
            CURLOPT_RETURNTRANSFER  => true, 
            CURLOPT_HEADER => false
        ];

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

    protected function getAccessToken(){
        if(!isset($this->token)) {
            $this->token =  $this->authenticate();
        }
        return $this->token;
    }

    private function authenticate(){
        $headers = [];
        $body = [
            'username'  => BBAM_Utils::getConfig('acc_username', null) ? : '',
            'password'  => BBAM_Utils::getConfig('acc_password', null) ? : '',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'password',
            'reseller_id' => BBAM_Utils::getConfig('reseller_id', null) ? : ''
        ];

        array_push($headers, 'Authorization: '.$this->authCode);
        array_push($headers, 'Origin: '.BBAM_Utils::setHeaderOriginValue());

        $this->generateCurlRequest('POST', $this->authUrl, $body, $headers);
        $response = $this->executeCurlSession();
        $this->closeCURLSession();
        $result = json_decode($response);

        if(isset($result->access_token)) {
            return $result->access_token;
        }

        BBAM_Utils::logger($result, 'AUTHENTICATION ERROR', 'bba-api-requet-error');
        return null; // ERROR OCCURRED
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
        $response = $this->executeCurlSession();
        $this->closeCURLSession();
        $result = json_decode($response);

        if(isset($result->error)) {
            BBAM_Utils::logger($result, 'API REQUEST ERROR', 'bba-api-requet-error');
            return null; // ERROR  OCCURRED
        }

        return $result; 
    }
}