<?php

// header('HTTP/1.1 401 Unauthorized');
// exit;

require_once 'lib/oauth-proxy-config.php';

$_OAUTH_PROXY = new ProxyApi($_OAUTH_SERVICE);
// $result = $_OAUTH_PROXY->proxyApiCallWithBearerAccessToken2();
$result = $_OAUTH_PROXY->proxyApiCall();

if (isset($result->refreshResult) && $result->refreshResult) {
    // access token was refreshed, so update the session
    ProxyApiConfig::storeTokens();
}

// TODO: based on config, we will call the appropriate oauth api with appropriate authentication token, etc.
