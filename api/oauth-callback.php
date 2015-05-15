<?php

require_once 'lib/oauth-proxy-config.php';

if ($_OAUTH_PATH_INFO !== '') {
    // Nothing should be after the callback service name
    header('HTTP/1.1 400 Bad Request');
    exit;
}

/// TODO: add security features (whitelist ip for service)
$response = FALSE;
$newToken = FALSE;

switch ($_OAUTH_SERVICE->oauth_version) {
    case 2:
        if ($_OAUTH_SERVICE->response_type == 'code' && $_OAUTH_SERVICE->grant_type == 'authorization_code') {
            $exchange_code = $_GET['code'];
            $response = $_OAUTH_SERVICE->authorize($exchange_code);
        } else {
            header('HTTP/1.1 401 Bad Request');
            exit;
        }
        break;
    case 1:
        $token = $_GET['oauth_token'];
        $verifier = $_GET['oauth_verifier'];
        $response = $_OAUTH_SERVICE->authorize($token, $verifier);
        break;
    default:
        header('HTTP/1.1 500 Server Error');
        header('X-OAuthServiceInfo-Error: Unsupported OAuth Version');
        exit;
}

$newToken = $response->data;
if (!$newToken) {
    header('HTTP/1.1 401 Unauthorized');
    header('X-OAuthServiceInfo-Error: ' . "($response->errorno) $response->errormsg");
    echo $response->responseBody;
    exit;
}
ProxyApiConfig::storeTokens();
header('Location: ' . $_OAUTH_SERVICE->getAppUrl());
