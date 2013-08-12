<?php
namespace AllPlayers\Component;

use Guzzle\Log\ClosureLogAdapter;
use Guzzle\Http\Client;
use Guzzle\Http\Message\RequestInterface;
use Guzzle\Http\Message\Response;
use Guzzle\Plugin\Log\LogPlugin;

use InvalidArgumentException;
use UnexpectedValueException;


/**
 * Handy RESTful wrapper.
 */
class HttpClient
{
    /**
     * AllPlayers.com endpoint URL. e.g. https://www.allplayers.com/api/v1/rest.
     *
     * @var string
     */
    public $urlPrefix = null;

    /**
     * Format string.
     *
     * @var string
     *
     * @todo Make this a mime-type.
     */
    public $format = 'application/json';

    /**
     * Guzzle instance.
     *
     * @var Guzzle\Http\Client;
     */
    protected $client = null;

    /**
     * Control wheter or not to print debug information. Use with care, may dump
     * sensetive information.
     *
     * @var bool
     */
    public $debug = false;

    /**
     * HTTP Response code of last request.
     *
     * @var int
     */
    public $responseCode = null;

    /**
     * Cookies to be reused between requests.
     *
     * @var CookiePlugin
     */
    public $cookiePlugin;

    public $lastResponse;

    /**
     * LogPlugin registred with Guzzle.
     *
     * Usage:
     *   $this->logPlugin->getLogAdapter()->log('hi mom', LOG_INFO, array('extra stuff'));
     *
     * @var LogPlugin
     */
    protected $logPlugin;

    /**
     * Headers variable for setting on an http request.
     *
     * @var array
     */
    protected $headers = array();

    /**
     * @param string $url_prefix
     *   e.g. https://www.allplayers.com/api/v1/rest.
     * @param LogPlugin $log_plugin
     *   (optional)
     */
    public function __construct($url_prefix, LogPlugin $log_plugin = null)
    {
        // Validate $url argument.
        if (!filter_var($url_prefix, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)) {
            throw new InvalidArgumentException(
                'Invalid argument 1: url_prefix must be a full URL, including path to an API endpoint.'
            );
        }
        $this->urlPrefix = $url_prefix;

        $this->client = new Client($url_prefix);

        if ($log_plugin) {
          // Register passed in LogPlugin.
          $this->logPlugin = $log_plugin;
          $this->client->addSubscriber($this->logPlugin);
        } else {
          // Create a noop logger for consistency, but don't register it for performance.
          $this->logPlugin = new LogPlugin(new ClosureLogAdapter(function(){}));
        }

        return $this->client;
    }

    /**
     * Get the Guzzle client.
     *
     * @return Guzzle\Http\ClientInterface
     */
    public function getClient() {
        return $this->client;
     }

    /**
     * Adds headers to the http request.
     *
     * @param string $key
     *   e.g. "User-Agent"
     * @param string $val
     *   e.g. "Chrome"
     */
    public function addHeader($key, $val)
    {
        $this->headers[$key] = $val;
    }

    /**
     * Handle all RESTful requests.
     *
     * @param string $verb
     * @param string $path
     * @param array $query
     * @param mixed $params
     * @param array $headers
     * @param boolean $allow_redirects
     *
     * @return array|stdClass
     *   Array or object from decodeResponse().
     */
    protected function httpRequest(
        $verb,
        $path,
        $query = array(),
        $params = null,
        $headers = array(),
        $allow_redirects = true
    ) {
        $url = "$this->urlPrefix/$path";

        $default_headers = array(
            'Cache-Control' => 'no-cache, must-revalidate, post-check=0, pre-check=0',
            'Accept' => $this->format,
            'Content-Type' => $this->format,
        );
        $headers = array_merge($default_headers, $headers, $this->headers);

        $body = ($params) ? json_encode($params) : null;

        $request = $this->getClient()->createRequest($verb, $url, $headers, $body);

        // Add Query String
        $request->getQuery()->merge($query);

        // @TODO - Redirects when not GET throw exceptions.
        // @see https://github.com/guzzle/guzzle/issues/120
        if ($verb !== 'GET') {
            $request->getCurlOptions()->set('body_as_string', true);
        }

        try {
            $response = $request->send();
        } catch (\Guzzle\Http\Exception\CurlException $e) {
            switch ($e->getErrorNo()) {
                case 47:
                    // @TODO - Cookies are not updated on GET redirect, re-issue works.
                    // @see https://github.com/guzzle/guzzle/issues/149
                    $request->getCurlOptions()->set('CURLOPT_MAXREDIRS', 5);
                    $response = $request->send();
                    break;
                default:
                    throw $e;
            }
        }
        $this->lastResponse = $response;

        $this->responseCode = $response->getStatusCode();
        $this->responseBody = $response->getBody();

        return $this->decodeResponse($response);
    }

    /**
     * GET data from REST server.
     *
     * @param string $path
     *   Path to append to base to form the URI.
     * @param array $query
     *   Items to append to path as a query string.
     * @param array $headers
     *   Additional headers.
     * @param boolean $allow_redirects
     *
     * @return array
     *   Array from process_response().
     *
     * @todo $headers is not used.
     */
    public function get($path, $query = array(), $headers = array(), $allow_redirects = true)
    {
        return $this->httpRequest('GET', $path, $query, null, $headers, $allow_redirects);
    }

    /**
     * POST data to REST server.
     *
     * @param string $path
     *   Path to append to base to form the URI.
     * @param array $params
     *   Parameters to post.
     * @param array $headers
     *   Additional headers.
     *
     * @return array
     *   Array from process_response().
     *
     * @todo $headers is not used.
     */
    public function post($path, $params = array(), $headers = array())
    {
        return $this->httpRequest('POST', $path, null, $params, $headers);
    }

    /**
     * PUT data to REST server.
     *
     * @param string $path
     *   Path to append to base to form the URI.
     * @param array $params
     *   Parameters to put.
     * @param array $headers
     *   Additional headers.
     *
     * @return array
     *   Array from process_response().
     *
     * @todo $headers is not used.
     */
    public function put($path, $params = array(), $headers = array())
    {
        return $this->httpRequest('PUT', $path, null, $params, $headers);
    }

    /**
     * DELETE data from REST server.
     *
     * @param string $path
     *   Path to append to base to form the URI.
     * @param array $query
     *   Items to append to path as a query string.
     * @param array $headers
     *   Additional headers.
     *
     * @return
     *   Array from process_response().
     *
     * @todo $headers is not used.
     */
    public function delete($path, $query = array(), $headers = array())
    {
        return $this->httpRequest('DELETE', $path, $query, null, $headers);
    }

    /**
     * Process the response.
     *
     * @return mixed
     *   Decoded response from the last rest request.
     */
    public function decodeResponse(Response $response)
    {
        switch ($this->format) {
            case 'application/json':
                $ret = json_decode($response->getBody(), FALSE);
                // Bubble up decode errors.
                if (json_last_error() !== JSON_ERROR_NONE) {
                  $this->logPlugin->getLogAdapter()->log('Invalid JSON in response', LOG_INFO, $response);
                  throw new UnexpectedValueException('Failed to decode JSON response.', json_last_error());
                }
                break;
            default:
                $ret = $response->getBody();

        }
        return $ret;
    }

    /**
     * Helper function to get all items from an index endpoint.
     *
     * @param string $path
     *   Relative path to the endpoint. (e.g. /users).
     * @param array $query
     *   (Optional) URL Query parameters. Many endpoints take filters in
     *   'parameters' array.
     * @param string $fields
     *   (Optional) Specify fields you'd like the resource to return (e.g.
     *   title, status).
     * @param integer|string $page
     *   (Optional) Numeric page number or '*' to fetch all pages. Default to 0.
     *   NOTE: The '*' parameter is a simple helper for basic CLI usage, using
     *   this loop is not recommended as it could easily cause a timeout or
     *   out-of-memory error.
     * @param integer $page_size
     *   (Optional) Limit the number of results returned per page. If not set,
     *   then we default to 20.
     *   NOTE: This does not limit the overall return set when using the '*'
     *   page parameter.
     *
     * @return array
     *   Array containing the stdObjects the index lists.
     */
    public function index($path, $query = array(), $fields = null, $page = 0, $page_size = 20)
    {
        $query['fields'] = $fields;
        $query['page'] = $page;
        $query['pagesize'] = (isset($page_size)) ? $page_size : 20;

        // "limit" was renamed to "pagesize", maintain both for backwards
        // compatibility.
        $query['limit'] = $query['pagesize'];

        // Page specified, get only that page.
        if (is_numeric($page)) {
            return $this->get($path, array_filter($query));
        } elseif ($page != '*') {
            throw new InvalidArgumentException('Invalid argument 4: page must be an integer or "*".');
        }

        // Page *, loop to get all.
        $query['page'] = 0;
        $results = array();

        // Index loop.
        do {
            // Get current page.
            $page_results = $this->get($path, array_filter($query));

            // Merge into overall result.
            $results = array_merge($results, (array) $page_results);
            $query['page']++;

            // If the result count != to pagesize, we are on the last page and
            // stop looping.
        } while (count($page_results) == $query['pagesize']);

        return $results;
    }

    /**
     * $_COOKIE['CHOCOLATECHIP']
     *
     * @param string $cookie_name
     * @param string $cookie
     * @param string $auth_path
     *
     * @todo Choose a path to hit.
     */
    public function ssoSessionInit($cookie_name, $cookie, $auth_path = 'group_stores')
    {
        // @TODO - This really isn't needed with the cookie jar.
        $this->get($auth_path, array(), array(), false);
    }

    /**
     * @param string $username
     * @param string $password
     */
    public function authSessionInit($username = null, $password = null)
    {
        $this->userLogin($username, $password);
    }
}
