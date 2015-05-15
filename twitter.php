<?php

$consumerkey = 'tIEjYVbQ85TqMVivTxpQzA';
$consumersecret = 'yMy1MVVUUpDnG8WIndjp2MgO7edIv7cJJPMBUzRCM';
$apibaseurl = 'https://api.twitter.com/1.1';
$tokenurl = 'https://api.twitter.com/oauth2/token';

$apimethod = $_SERVER['REQUEST_METHOD'];
$query = $_SERVER['QUERY_STRING'];
$apipath = $_SERVER['PATH_INFO'];
$apiurl = $apibaseurl . $apipath . ($query ? "?$query" : '');

$bearerCredentials = $consumerkey . ':' . $consumersecret;
$bearerEncoded = base64_encode($bearerCredentials);

// Obtain the bearer access token
$tokenrequest = curl_init($tokenurl);
curl_setopt_array(
    $tokenrequest, 
    array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Basic ' . $bearerEncoded,
            'Content-Type: application/x-www-form-urlencoded;charset=UTF-8'
        ),
        CURLOPT_POSTFIELDS => http_build_query(array(
            'grant_type' => 'client_credentials'
        )),
        CURLOPT_FOLLOWLOCATION => true,
    )
);
$response = curl_exec($tokenrequest);
if ($response === FALSE || curl_getinfo($tokenrequest, CURLINFO_HTTP_CODE) != '200') {
    header('HTTP/1.0 401 Unauthorized');
} else {
    $responseData = json_decode($response);
    $access_token = $responseData->access_token;

    // Call the twitter API with the bearer access token
    $api_request = curl_init($apiurl);
    $apiheaders = array(
        'Authorization: Bearer ' . $access_token
    );
    if ($_SERVER['CONTENT_TYPE']) {
        array_push($apiheaders, 'Content-Type: ' . $_SERVER['CONTENT_TYPE']);
    }
    curl_setopt_array(
        $api_request, 
        array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $apimethod,
            CURLOPT_HTTPHEADER => $apiheaders,
            CURLOPT_POSTFIELDS => stream_get_contents(STDIN),
            CURLOPT_FOLLOWLOCATION => true,
        )
    );
    $apiresponse = curl_exec($api_request);
    $apicode = curl_getinfo($api_request, CURLINFO_HTTP_CODE);
    if ($apiresponse === FALSE || $apicode != 200) {
        if ($apicode >= 500 && $apicode < 600) {
            header('HTTP/1.0 500 Server Error');
        } else {
            header('HTTP/1.0 400 Bad Request');
        }
    } else {
        $contentType = curl_getinfo($api_request, CURLINFO_CONTENT_TYPE);
        if ($contentType) {
            header("Content-Type: " . $contentType);
        }
        echo $apiresponse;
    }
    curl_close($api_request);
}
curl_close($tokenrequest);
