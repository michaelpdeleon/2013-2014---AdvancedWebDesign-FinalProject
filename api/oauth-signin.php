<?php

require_once 'lib/oauth-proxy-config.php';

// redirect to the sign in url
$response = $_OAUTH_SERVICE->getSignInUrl();
$redirecturl = $response->data;
if (!$redirecturl) {
    header('HTTP/1.1 500 Server Error');
    header('X-OAuthServiceInfo-Error: ' . "($response->errorno) $response->errormsg");
    echo $response->responseBody;
    exit;
}
if ( $_OAUTH_SERVICE->oauth_version == 1) {
    ProxyApiConfig::storeTokens();
}
header('Location: ' . $redirecturl);
