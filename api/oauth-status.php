<?php

require_once 'lib/oauth-proxy-config.php';

$result = new stdClass();
$result->loggedIn = $_OAUTH_SERVICE->accessToken ? true : false;
header('Content-type: application/json');
echo json_encode($result);

if ($_GET['debug']) {
    print_r( $_OAUTH_SERVICE );
}
