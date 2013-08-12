<?php
namespace AllPlayers\Component;

use AllPlayers\Client;
use Guzzle\Plugin\Cookie\CookieJar\ArrayCookieJar;
use Guzzle\Plugin\Log\LogPlugin;
use Guzzle\Plugin\Cookie\CookiePlugin;
use Guzzle\Plugin\Oauth\OauthPlugin;

use ErrorException;

/**
 * Factory methods for initializing the AllPlayers Client.
 */
class ClientFactory
{
    /**
     * Factory method to create a new Client with Session authentication
     *
     * @param string $url_prefix
     *   e.g. https://www.allplayers.com/api/v1/rest.
     * @param CookiePlugin $cookie_plugin
     *   (optional)
     * @param Client $client
     *   (optional, if not specified new client will be created.
     * @param LogPlugin $log_plugin
     *   (optional)
     *
     * @return Client new http client object
     */
    public static function SessionFactory($url_prefix, CookiePlugin $cookie_plugin = null, Client $client = null, LogPlugin $log_plugin = null)
    {
        if (!$client) {
            $client = new Client($url_prefix, $log_plugin);
        }
        $auth_plugin = ($cookie_plugin) ? $cookie_plugin : new CookiePlugin(new ArrayCookieJar());
        $client->getClient()->addSubscriber($auth_plugin);

        return $client;
    }

    /**
     * Factory method to create a new Client with Oauth authentication
     *
     * @param string $url_prefix
     *   e.g. https://www.allplayers.com/api/v1/rest.
     * @param array $oauth_config
     *   consumer_key(required), consumer_secret(required), token, token_secret
     * @param Client $client
     *   (optional, if not specified new client will be created.
     * @param LogPlugin $log_plugin
     *   (optional)
     *
     * @return Client new http client object
     */
    public static function OAuthFactory($url_prefix, $oauth_config, Client $client = null, LogPlugin $log_plugin = null)
    {
        if (!$client) {
            $client = new Client($url_prefix, $log_plugin);
        }
        $auth_plugin = new OauthPlugin($oauth_config);
        $client->getClient()->addSubscriber($auth_plugin);

        return $client;
    }

    /**
     * Factory method to create a new Client with Basic authentication
     *
     * @param string $url_prefix
     *   e.g. https://www.allplayers.com/api/v1/rest.
     * @param string $username
     * @param string $password
     * @param Client $client
     *   (optional, if not specified new client will be created.
     * @param LogPlugin $log_plugin
     *   (optional)
     *
     * @return Client new http client object
     */
    public static function BasicAuthFactory($url_prefix, $username, $password, Client $client = null, LogPlugin $log_plugin = null)
    {
        if (!$client) {
            $client = new Client($url_prefix, $log_plugin);
        }
        $auth_plugin = new CurlAuthPlugin($username, $password);
        $client->getClient()->addSubscriber($auth_plugin);

        return $client;
    }
}
