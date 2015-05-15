<?php

require_once(dirname(__FILE__) . '/oauthservice.php');
// Define classes that extend OAuthService as shown below.  Utilize the 'OAuthService_' prefix for class names.

// Asana OAuth 2.0 example

class OAuthService_Asana extends OAuthService {
    public $oauth_version = 2;
    
    public $consumerkey = '';
    public $consumersecret = '';
    public $apibaseurl = 'https://app.asana.com/api/1.0';
    public $tokenurl = 'https://app.asana.com/-/oauth_token';
    public $authorizeurl = 'https://app.asana.com/-/oauth_authorize';
    public $redirect_baseuri = 'https://your_project_url/api/oauth-callback.php/Asana';
    public $response_type = 'code';
    public $grant_type = 'authorization_code'; // authorization_code
    public $app_url = 'https://your_project_url/';
}

// Twitter example: using app-only authorization

class OAuthService_TwitterClient extends OAuthService {
    public $oauth_version = 2;
    
    public $consumerkey = '';
    public $consumersecret = '';
    public $apibaseurl = 'https://api.twitter.com/1.1';
    public $tokenurl = 'https://api.twitter.com/oauth2/token';

    // Use "client_credentials" when doing app-only tokens, using only the consumer key and secret for your app
    public $grant_type = 'client_credentials';
}

// Twitter example: using user context authorization

class OAuthService_Twitter extends OAuthService {
    public $oauth_version = 1;
    public $signature_method = 'HMAC-SHA1';
    
    // your oauth app key (or client_id)
    public $consumerkey = '';
    // your oauth app secret
    public $consumersecret = '';
    
    // main URL that the api is based off of
    public $apibaseurl = 'https://api.twitter.com/1.1';
    
    // API's OAuth 1.0 URL for getting a "request token" to redirect the user
    public $requesttokenurl = 'https://api.twitter.com/oauth/request_token';
    // API's OAuth 1.0 URL to redirect the browser to for authorization
    public $authorizeurl = 'https://api.twitter.com/oauth/authorize';
    // The URL on your application that OAuth 1.0 should make a callback to with the token verifier
    public $redirect_baseuri = 'https://your_project_url/api/oauth-callback.php/Twitter';
    // API's OAuth 1.0 URL for getting the "access token" using the callback data
    public $tokenurl = 'https://api.twitter.com/oauth/access_token';

    // where your app lives (redirects here after authorization)
    public $app_url = 'https://your_project_url/';
    
}
