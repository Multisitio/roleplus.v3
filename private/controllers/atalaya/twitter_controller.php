<?php
require_once VENDOR_PATH . 'autoload.php';
use Noweh\TwitterApi\Client;
/**
 */
class TwitterController extends AtalayaController
{
    # https://github.com/noweh/twitter-api-v2-php
    public function __call($name, $params) {
        View::select(null, null);

        $access_token = Config::get('twitter.access_token');
        $access_token_secret = Config::get('twitter.access_token_secret');
        $account_id = Config::get('twitter.account_id');
        $bearer_token = Config::get('twitter.bearer_token');
        $consumer_key = Config::get('twitter.consumer_key');
        $consumer_secret = Config::get('twitter.consumer_secret');

        // Dame el código PHP para enviar Twits con el API 2 de Twitter?

        $settings = [
            'account_id' => $account_id,
            'consumer_key' => $consumer_key,
            'consumer_secret' => $consumer_secret,
            'bearer_token' => $bearer_token,
            'access_token' => $access_token,
            'access_token_secret' => $access_token_secret
        ];
        
        $client = new Client($settings);
        $return = $client->tweet()->performRequest('POST', ['text' => 'This is a test....']);
    }
}
