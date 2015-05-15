<?php

define(OAUTH_ERROR_VERSION, -1);
define(OAUTH_ERROR_SIGNATURE_METHOD, -10);
define(OAUTH_ERROR_GRANT_TYPE, -301);
define(OAUTH_ERROR_REFRESH_TOKEN, -400);
define(OAUTH_ERROR_CALLBACK_CONFIRMATION, -500);

class OAuthServiceApiResponse {
    
    public $success = FALSE;
    public $errorno = null;
    public $errormsg = null;
    
    public $responseCode = null;
    public $responseMessage = null;
    public $responseBody = null;
    
    public $contentType = null;
    public $data = null;
}

abstract class OAuthService {

    public $consumerkey = '';
    public $consumersecret = '';
    public $apibaseurl = '';
    public $tokenurl = '';
    public $authorizeurl = '';
    public $redirect_baseuri = '';
    public $app_url = '';
    public $tryrefreshstatuscodes = array(401);
    public $oauth_version = 2;
    
    public $accessToken = FALSE;
    
    // oauth 2 only
    public $response_type = '';
    public $grant_type = '';
    public $refreshToken = FALSE;
    
    // oauth 1 only
    public $requesttokenurl = '';
    public $signature_method = 'HMAC-SHA1';
    public $requestAccessToken = FALSE;
    public $requestAccessTokenSecret = FALSE;
    public $accessTokenSecret = FALSE;

    public function __construct() {
    }
    
    protected function getAccessTokenViaClientCredentials() {
        if (!$this->accessToken) {
            $bearerCredentials = $this->consumerkey . ':' . $this->consumersecret;
            $bearerEncoded = base64_encode($bearerCredentials);
            
            $headers = array(
                'Authorization: Basic ' . $bearerEncoded,
                'Content-Type: application/x-www-form-urlencoded;charset=UTF-8'
            );
            $post = http_build_query(array(
                'grant_type' => 'client_credentials'
            ));
            $response = $this->apiTokenCall('POST', $post, $headers);
            
            if ($response->success && $response->responseCode == 200) {
                $responseData = json_decode($response->responseBody);
                $access_token = $responseData->access_token;
                $this->accessToken = $access_token;
                $response->data = $this->accessToken;
            } else {
                $response->success = FALSE;
            }
            return $response;
        } else {
            $response = new OAuthServiceApiResponse();
            $response->success = true;
            $response->data = $this->accessToken;
            return $response;
        }
    }

    public function apiCall($method, $query, $apipath, $post, $apiheaders, $allow_refresh = true) {
        $apicallurl = $this->apibaseurl . $apipath;
        $apiurl = $apicallurl . ($query ? "?$query" : '');
        switch ($this->oauth_version) {
            case 2:
                switch ($this->grant_type) {
                    case 'authorization_code':
                        if ($this->accessToken) {
                            $newheaders = array_merge($apiheaders, array(
                                'Authorization: Bearer ' . $this->accessToken
                            ));
                        } else {
                            $newheaders = $apiheaders;
                        }
                        break;
                    case 'client_credentials':
                        $response = $this->getAccessTokenViaClientCredentials();
                        if ($response && (!$response->success || $response->responseCode != 200)) {
                            // failed to retrieve a bearer access token
                            $response->errormsg = "Could not obtainer bearer access token: $response->errormsg";
                            $response->success = FALSE;
                            return $response;
                        }
                        if ($this->accessToken) {
                            $newheaders = array_merge($apiheaders, array(
                                'Authorization: Bearer ' . $this->accessToken
                            ));
                        } else {
                            $newheaders = $apiheaders;
                        }
                        break;
                    default:
                        return $this->getError(OAUTH_ERROR_GRANT_TYPE);
                }
                break;
            case 1:
                $allow_refresh = false;
                $params = array();
                parse_str( $post, $params );
                parse_str( $query, $params );
                // echo "\nparams: " . print_r( $params, true ) . "\n";
                $authHeader = $this->getSignedAuthorizationHeader($method, $apicallurl, $params);
                if (!$authHeader) {
                    return $this->getError(OAUTH_ERROR_SIGNATURE_METHOD);
                }
                $newheaders = array_merge( $apiheaders, array( $authHeader ) );
                // print_r( $newheaders );
                // print_r( $GLOBALS['_OAUTH_SERVICE'] );
                // exit;
                break;
            default:
                return $this->getError(OAUTH_ERROR_VERSION);
        }
        
        $result = $this->httpCall($method, $apiurl, $post, $newheaders);
        if ($allow_refresh && ($result->responseCode < 200 || $result->responseCode >= 300) && in_array($result->responseCode, $this->tryrefreshstatuscodes)) {
            
            // if failed request, and refresh is allowed, we can try to get a new oauth 2.0 access token using the refresh token
            
            switch ($this->grant_type) {
                case 'authorization_code':
                    $oldrefresh = $this->refreshToken;
                    $oldtoken = $this->accessToken;
                    $newtoken = $this->refresh();
                    // try refresh token
                    if ($newtoken) {
                        // one more try after successful refresh
                        $newresult = $this->apiCall($method, $query, $apipath, $post, $apiheaders, false);
                        $newresult->refreshAttempt = true;
                        $newresult->refreshResult = true;
                        $newresult->oldtoken = $oldtoken;
                        return $newresult;
                    } else {
                        $result->refreshAttempt = true;
                        $result->refreshResult = false;
                        // refresh failed, return original result
                        return $result;
                    }
                    break;
                case 'client_credentials':
                    $oldtoken = $this->accessToken;
                    $response = $this->getAccessTokenViaClientCredentials();
                    if ($response && (!$response->success || $response->responseCode != 200)) {
                        // failed to retrieve a bearer access token
                        // return original result with extended error info
                        $result->errormsg = "Could not obtainer bearer access token: $response->errormsg | $result->errormsg";
                        $newresult->refreshAttempt = true;
                        $newresult->refreshResult = false;
                        return $result;
                    }
                    // retry with new token
                    $result->oldtoken = $oldtoken;
                    $newresult->refreshAttempt = true;
                    $newresult->refreshResult = true;
                    break;
                default:
                    return $result;
            }
        } else {
            return $result;
        }
    }
    
    public function apiTokenCall($method, $post, $apiheaders) {
        $apiurl = $this->tokenurl;
        return $this->httpCall($method, $apiurl, $post, $apiheaders);
    }
    
    protected function httpCall($method, $apiurl, $post, $apiheaders) {
        $api_request = curl_init($apiurl);
        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $apiheaders, //array_merge( $apiheaders, 'Expect:' ),
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_FOLLOWLOCATION => true,
            // CURLOPT_HEADER => true
        );
        curl_setopt_array(
            $api_request, 
            $options
        );
        $result = new OAuthServiceApiResponse();
        $rawresponse = curl_exec($api_request);
        $result->responseCode = curl_getinfo($api_request, CURLINFO_HTTP_CODE);

        if ($rawresponse === FALSE) {
            $result->success = FALSE;
            $result->errorno = curl_errno($api_request);
            $result->errormsg = curl_error($api_request);
        } else {
            $result->success = TRUE;
        }
        $result->responseMessage = substr($rawresponse, 0, strpos($rawresponse, "\r\n"));
        $result->contentType = curl_getinfo($api_request, CURLINFO_CONTENT_TYPE);
        $result->responseBody = $rawresponse;
        // $result->responseBody = substr($rawresponse, strpos($rawresponse, "\r\n\r\n") + 4);
        curl_close($api_request);

        return $result;
    }

    public function getSignInUrl() {
        switch ($this->oauth_version) {
            case 2:
                $response = new OAuthServiceApiResponse();
                $response->success = true;
                $response->data = $this->authorizeurl . "?" . http_build_query(array (
                    'client_id' => $this->consumerkey,
                    'redirect_uri' => $this->redirect_baseuri,
                    'response_type' => $this->response_type
                ));
                return $response;
            case 1:
                $url = $this->requesttokenurl;
                $method = 'POST';
                $params = array();
                // reset request access tokens, we ask for new ones here
                $this->requestAccessToken = FALSE;
                $this->requestAccessTokenSecret = FALSE;
                $oauth_params = array( 'oauth_callback' => $this->redirect_baseuri );
                $authorizationHeader = $this->getSignedAuthorizationHeader($method, $url, $params, true, $oauth_params);
                if ( !$authorizationHeader ) {
                    return $this->getError(OAUTH_ERROR_SIGNATURE_METHOD);
                }
                $headers = array( $authorizationHeader );
                $response = $this->httpCall($method, $url, $params, $headers);
                if ($response->success && $response->responseCode == 200) {
                    $responseData = array();
                    parse_str($response->responseBody, $responseData);
                    $this->requestAccessToken = $responseData['oauth_token'];
                    $this->requestAccessTokenSecret = $responseData['oauth_token_secret'];
                    $confirmed = $responseData['oauth_callback_confirmed'];
                    if ($confirmed !== 'true') {
                        // bad confirmation
                        $response->success = FALSE;
                        $response->errorno = OAUTH_ERROR_CALLBACK_CONFIRMATION;
                        $response->errormsg = $this->getErrorName($response->errorno);
                    } else {
                        $response->data = $this->authorizeurl . '?oauth_token=' . rawurlencode($this->requestAccessToken);
                    }
                } else {
                    $response->success = FALSE;
                }
                return $response;
        }
        return $this->getError(OAUTH_ERROR_VERSION);
    }
    
    protected function buildquery($params, $sort = false) {
        $psorted = array();
        foreach ($params as $k => $v) {
            $psorted[rawurlencode($k)] = rawurlencode($v);
        }
        if ($sort) {
            ksort($psorted);
        }
        $p = array();
        foreach ($psorted as $k => $v) {
            $p[] = $k . '=' . $v;
        }
        return implode('&', $p);
    }
    
    protected function getSignedAuthorizationHeader($method, $url, $params, $useRequestTokens = false, $additionalOAuthParams = array()) {
        $nonce = base64_encode(uniqid('', true));
        $timestamp = time();
        $oauth_params = array_merge( $additionalOAuthParams, array(
            'oauth_consumer_key' => $this->consumerkey,
            'oauth_nonce' => $nonce,
            'oauth_signature_method' => $this->signature_method,
            'oauth_timestamp' => $timestamp,
            'oauth_version' => '1.0'
        ));
        if ($useRequestTokens) {
            if ($this->requestAccessToken) {
                $oauth_params['oauth_token'] = $this->requestAccessToken;
            }
        } else {
            if ($this->accessToken) {
                $oauth_params['oauth_token'] = $this->accessToken;
            }
        }
        $signature = '';
        switch ($this->signature_method) {
            case 'HMAC-SHA1':
                $oauth_params['oauth_signature'] = $this->sign_hmac_sha1($method, $url, array_merge( $params, $oauth_params ), $useRequestTokens);
                break;
            default:
                // fail, unsupported signature type
                return FALSE;
        }
        $header = 'Authorization: OAuth';
        foreach ($oauth_params as $k => $v) {
            $header .= ' ' . rawurlencode($k) . '="' . rawurlencode($v) . "\", \n";
        }
        $header = substr($header, 0, strlen($header) - 3);
        return $header;
    }
    
    protected function sign_hmac_sha1($method, $url, $params, $useRequestTokens = false) {
        $e_method = rawurlencode(strtoupper($method));
        $e_url = rawurlencode($url);
        $e_params = rawurlencode($this->buildquery($params, true));
        $base = "$e_method&$e_url&$e_params";
        if ( $useRequestTokens ) {
            $tokenSecret = $this->requestAccessTokenSecret ? $this->requestAccessTokenSecret : '';
        } else {
            $tokenSecret = $this->accessTokenSecret ? $this->accessTokenSecret : '';
        }
        $key = rawurlencode($this->consumersecret) . '&' . rawurlencode($tokenSecret);
        // echo "\nbase: $base\n";
        // echo "\nkey: $key\n";
        // print_r( $GLOBALS['_OAUTH_SERVICE'] );
        return base64_encode(hash_hmac('sha1', $base, $key, true));
    }
    
    public function getAppUrl() {
        return $this->app_url;
    }
    
    public function authorize($codeOrToken, $verifier = null) {
        switch ($this->oauth_version) {
            case 2:
                switch ($this->grant_type) {
                    case 'authorization_code':
                        $headers = array(
                            'Content-Type: application/x-www-form-urlencoded;charset=UTF-8'
                        );
                        $post = http_build_query(array(
                            'grant_type' => 'authorization_code',
                            'client_id' => $this->consumerkey,
                            'client_secret' => $this->consumersecret,
                            'redirect_uri' => $this->redirect_baseuri,
                            'code' => $codeOrToken
                        ));
                        $response = $this->httpCall('POST', $this->tokenurl, $post, $headers);
                        if ( $response->success && $response->responseCode == 200 ) {
                            $data = json_decode($response->responseBody);
                            $this->accessToken = $data->access_token;
                            $this->refreshToken = $data->refresh_token;
                            $response->data = $this->accessToken;
                        } else {
                            $response->success = FALSE;
                        }
                        return $response;
                }
                return $this->getError(OAUTH_ERROR_GRANT_TYPE);
            case 1:
                $url = $this->tokenurl;
                $method = 'POST';
                $params = array(
                    'oauth_verifier' => $verifier
                );
                $authorizationHeader = $this->getSignedAuthorizationHeader($method, $url, $params, true);
                if ( !$authorizationHeader ) {
                    return $this->getError(OAUTH_ERROR_SIGNATURE_METHOD);
                }
                $headers = array( $authorizationHeader );
                $response = $this->httpCall($method, $url, $params, $headers);
                if ($response->success && $response->responseCode == 200) {
                    $responseData = array();
                    parse_str($response->responseBody, $responseData);
                    $this->accessToken = $responseData['oauth_token'];
                    $this->accessTokenSecret = $responseData['oauth_token_secret'];
                    $this->requestAccessToken = FALSE;
                    $this->requestAccessTokenSecret = FALSE;
                    $response->data = $this->accessToken;
                } else {
                    $response->success = FALSE;
                }
                return $response;
        }
        return $this->getError(OAUTH_ERROR_VERSION);
    }
    
    public function refresh() {
        if (!$this->refreshToken) {
            return $this->getError(OAUTH_ERROR_REFRESH_TOKEN);
        }
        $headers = array(
            'Content-Type: application/x-www-form-urlencoded;charset=UTF-8'
        );
        $post = http_build_query(array(
            'grant_type' => 'refresh_token',
            'client_id' => $this->consumerkey,
            'client_secret' => $this->consumersecret,
            'redirect_uri' => $this->redirect_baseuri,
            'refresh_token' => $this->refreshToken
        ));
        $response = $this->httpCall('POST', $this->tokenurl, $post, $headers);
        if ( $response->success && $response->responseCode == 200 ) {
            $data = json_decode($response->responseBody);
            $this->accessToken = $data->access_token;
            if ($data->refresh_token) {
                // only reset if there is one that is specified in the result
                $this->refreshToken = $data->refresh_token;
            }
            $response->data = $this->accessToken;
        } else {
            $response->success = FALSE;
        }
        return $response;
    }
    
    protected function getError($errorcode) {
        $badResponse = new OAuthServiceApiResponse();
        $badResponse->success = FALSE;
        $badResponse->errorno = $errorcode;
        $badResponse->errormsg = $this->getErrorName($errorcode);
        return $badResponse;
    }
    
    protected function getErrorName($errorcode) {
        switch ($errorcode) {
            case OAUTH_ERROR_VERSION:
                return 'Unsupported OAuth version';
            case OAUTH_ERROR_SIGNATURE_METHOD:
                return 'Unsupported signature method';
            case OAUTH_ERROR_GRANT_TYPE:
                return 'Unsupported OAuth 2.0 grant_type';
            case OAUTH_ERROR_REFRESH_TOKEN:
                return 'Refresh token is not available';
            case OAUTH_ERROR_CALLBACK_CONFIRMATION:
                return 'OAuth callback token confirmation failed';
            default:
                return '';
        }
    }
    
}


class ProxyApi {
    
    protected $service = null;
    
    public function __construct(OAuthService $service) {
        $this->service = $service;
    }
    
    public function proxyApiCall($apiheaders = array()) {
        $apimethod = $_SERVER['REQUEST_METHOD'];
        $query = $_SERVER['QUERY_STRING'];
        $apipath = $GLOBALS['_OAUTH_PATH_INFO'];
        $apiurl = $this->service->apibaseurl . $apipath . ($query ? "?$query" : '');
        
        if ($_SERVER['CONTENT_TYPE']) {
            array_push($apiheaders, 'Content-Type: ' . $_SERVER['CONTENT_TYPE']);
        }
        
        /// TODO: figure out how to get file_get_contents for stdin working to forward post data
        // $post = file_get_contents('php://stdin');
        $post = http_build_query($_POST);
        
        $response = $this->service->apiCall($apimethod, $query, $apipath, $post, $apiheaders);
        if (!$response->success) {
            header("HTTP/1.1 500 Server Error");
            $response->responseBody = "API Request Failed - ($response->errorno) - $response->errormsg";
            // $response->responseBody += "\r\n" . print_r( $response, true );
            $response->contentType = 'text/plain';
            header("X-OAuthServiceInfo-Error: ($response->errorno) - $response->errormsg");
        } else if ($response->responseCode != 200) {
            header("HTTP/1.1 $response->responseCode $response->responseMessage");
        }
        // header("X-OAuthServiceInfo-Url: " . $apiurl);
        if ($response->contentType) {
            header("Content-Type: " . $response->contentType);
        }
        echo $response->responseBody;
        return $response;
    }
    
    public static function getRedirectUri($proxyapi_baseurl, $servicename) {
        return $proxyapi_baseurl . 'oauth-callback.php/' . $servicename;
    }
    
}
