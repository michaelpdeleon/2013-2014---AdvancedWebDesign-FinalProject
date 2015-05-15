<?php

require_once(dirname(__FILE__) . '/oauth-config.php');
$path = $_SERVER['PATH_INFO'];
$pregmatches = array();
if (preg_match('@^/([a-zA-Z0-9_]+)(/?.*)@s', $path, $pregmatches) !== 1) {
    header('HTTP/1.1 400 Bad Request');
    exit;
}
$classname = "OAuthService_" . $pregmatches[1];
if (!class_exists($classname) || !is_subclass_of($classname, 'OAuthService')) {
    header('HTTP/1.1 400 Bad Request');
    exit;
}

/// TODO: add more security (same domain referer, xhr request header, ip address remote host, session validation key)

$GLOBALS['_OAUTH_SERVICE'] = new $classname();
$GLOBALS['_OAUTH_PATH_INFO'] = $pregmatches[2];

if (session_id() === '') {
    session_start();
}
$GLOBALS['_OAUTH_SERVICE_CLASS'] = get_class($_OAUTH_SERVICE);

class ProxyApiConfig {
    public static function storeTokens() {
        global $_OAUTH_SERVICE;
        $tokendata = new stdClass();
        $tokendata->accessToken = $_OAUTH_SERVICE->accessToken;
        $tokendata->accessTokenSecret = $_OAUTH_SERVICE->accessTokenSecret;
        $tokendata->requestAccessToken = $_OAUTH_SERVICE->requestAccessToken;
        $tokendata->requestAccessTokenSecret = $_OAUTH_SERVICE->requestAccessTokenSecret;
        $tokendata->refreshToken = $_OAUTH_SERVICE->refreshToken;
        $_SESSION[get_class($_OAUTH_SERVICE)] = $tokendata;
    }
    
    public static function getTokens() {
        global $_OAUTH_SERVICE_CLASS, $_OAUTH_SERVICE;
        if (isset($_SESSION[$_OAUTH_SERVICE_CLASS]) && $_SESSION[$_OAUTH_SERVICE_CLASS]) {
            $_OAUTH_SERVICE->accessToken = $_SESSION[$_OAUTH_SERVICE_CLASS]->accessToken;
            $_OAUTH_SERVICE->accessTokenSecret = $_SESSION[$_OAUTH_SERVICE_CLASS]->accessTokenSecret;
            $_OAUTH_SERVICE->requestAccessToken = $_SESSION[$_OAUTH_SERVICE_CLASS]->requestAccessToken;
            $_OAUTH_SERVICE->requestAccessTokenSecret = $_SESSION[$_OAUTH_SERVICE_CLASS]->requestAccessTokenSecret;
            $_OAUTH_SERVICE->refreshToken = $_SESSION[$_OAUTH_SERVICE_CLASS]->refreshToken;
        }
        
    }
    
    public static function clearTokens() {
        global $_OAUTH_SERVICE_CLASS, $_OAUTH_SERVICE;
        unset($_SESSION[get_class($_OAUTH_SERVICE)]);
    }
}

ProxyApiConfig::getTokens();
