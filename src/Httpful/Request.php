<?php

declare(strict_types=1);

namespace Httpful;

use Curl\Curl;
use Httpful\Exception\ClientErrorException;
use Httpful\Exception\NetworkErrorException;
use Httpful\Exception\RequestException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use voku\helper\UTF8;

class Request implements \IteratorAggregate, RequestInterface
{
    const MAX_REDIRECTS_DEFAULT = 25;

    const SERIALIZE_PAYLOAD_ALWAYS = 1;

    const SERIALIZE_PAYLOAD_NEVER = 0;

    const SERIALIZE_PAYLOAD_SMART = 2;

    /**
     * "Request"-template object
     *
     * @var Request|null
     */
    private $_template;

    /**
     * @var UriInterface|null
     */
    private $uri;

    /**
     * @var string
     */
    private $client_key = '';

    /**
     * @var string
     */
    private $client_cert = '';

    /**
     * @var string
     */
    private $client_encoding = '';

    /**
     * @var string|null
     */
    private $client_passphrase;

    /**
     * @var float|int|null
     */
    private $timeout;

    /**
     * @var float|int|null
     */
    private $connection_timeout;

    /**
     * @var string
     */
    private $method = Http::GET;

    /**
     * Map of all registered headers, as original name => array of values
     *
     * @var array
     */
    private $headers = [];

    /**
     * Map of lowercase header name => original name at registration
     *
     * @var array
     */
    private $headerNames = [];

    /**
     * @var string
     */
    private $raw_headers = '';

    /**
     * @var bool
     */
    private $strict_ssl = false;

    /**
     * @var string
     */
    private $content_type = '';

    /**
     * @var string
     */
    private $expected_type = '';

    /**
     * @var array
     */
    private $additional_curl_opts = [];

    /**
     * @var bool
     */
    private $auto_parse = true;

    /**
     * @var int
     */
    private $serialize_payload_method = self::SERIALIZE_PAYLOAD_SMART;

    /**
     * @var string
     */
    private $username = '';

    /**
     * @var string
     */
    private $password = '';

    /**
     * @var mixed|null
     */
    private $serialized_payload;

    /**
     * @var array
     */
    private $payload = [];

    /**
     * @var array
     */
    private $params = [];

    /**
     * @var callable|null
     */
    private $parse_callback;

    /**
     * @var callable|LoggerInterface|null
     */
    private $error_handler;

    /**
     * @var callable[]
     */
    private $send_callbacks = [];

    /**
     * @var bool
     */
    private $follow_redirects = false;

    /**
     * @var int
     */
    private $max_redirects = self::MAX_REDIRECTS_DEFAULT;

    /**
     * @var array
     */
    private $payload_serializers = [];

    /**
     * Curl Object
     *
     * @var Curl|null
     */
    private $_curl;

    /**
     * @var bool
     */
    private $_debug = false;

    /**
     * @var array|null
     */
    private $_info;

    /**
     * @var string|null
     */
    private $_protocol_version;

    /**
     * The Client::get, Client::post, ... syntax is preferred as it is more readable.
     *
     * @param string|null $method   Http Method
     * @param string|null $mime     Mime Type to Use
     * @param static|null $template "Request"-template object
     */
    public function __construct(
        string $method = null,
        string $mime = null,
        self $template = null
    ) {
        $this->_template = $template;

        // fallback
        if (!isset($this->_template)) {
            $this->_template = new static(Http::GET, null, $this);
            $this->_template->disableStrictSSL();
        }

        $this->_setDefaultsFromTemplate()
            ->_method($method)
            ->contentType($mime, Mime::PLAIN)
            ->expectsType($mime, Mime::PLAIN);
    }

    /**
     * Does the heavy lifting.  Uses de facto HTTP
     * library cURL to set up the HTTP request.
     * Note: It does NOT actually send the request
     *
     * @throws \Exception
     *
     * @return static
     *
     * @internal
     */
    public function _curlPrep(): self
    {
        // Check for required stuff.
        if ($this->uri === null) {
            throw new RequestException($this, 'Attempting to send a request before defining a URI endpoint.');
        }

        if ($this->params === []) {
            $this->_uriPrep();
        }

        if ($this->payload !== []) {
            $this->serialized_payload = $this->_serializePayload($this->payload);
        }

        if ($this->send_callbacks !== []) {
            foreach ($this->send_callbacks as $callback) {
                \call_user_func($callback, $this);
            }
        }

        $curl = new Curl();
        $curl->setUrl($this->uri);

        $ch = $curl->getCurl();

        if ($ch === false) {
            throw new NetworkErrorException('Unable to connect to "' . $this->uri . '". => "curl_init" === false');
        }

        $curl->setOpt(\CURLOPT_IPRESOLVE, \CURL_IPRESOLVE_V4);

        $curl->setOpt(\CURLOPT_CUSTOMREQUEST, $this->method);
        if ($this->method === Http::HEAD) {
            $curl->setOpt(\CURLOPT_NOBODY, true);
        }

        if ($this->hasBasicAuth()) {
            $curl->setOpt(\CURLOPT_USERPWD, $this->username . ':' . $this->password);
        }

        if ($this->hasClientSideCert()) {
            if (!\file_exists($this->client_key)) {
                throw new RequestException($this, 'Could not read Client Key');
            }

            if (!\file_exists($this->client_cert)) {
                throw new RequestException($this, 'Could not read Client Certificate');
            }

            $curl->setOpt(\CURLOPT_SSLCERTTYPE, $this->client_encoding);
            $curl->setOpt(\CURLOPT_SSLKEYTYPE, $this->client_encoding);
            $curl->setOpt(\CURLOPT_SSLCERT, $this->client_cert);
            $curl->setOpt(\CURLOPT_SSLKEY, $this->client_key);
            if ($this->client_passphrase !== null) {
                $curl->setOpt(\CURLOPT_SSLKEYPASSWD, $this->client_passphrase);
            }
            // $curl->setOpt(CURLOPT_SSLCERTPASSWD,  $this->client_cert_passphrase);
        }

        if ($this->hasTimeout()) {
            if (\defined('CURLOPT_TIMEOUT_MS')) {
                $curl->setOpt(\CURLOPT_TIMEOUT_MS, $this->timeout * 1000);
            } else {
                $curl->setOpt(\CURLOPT_TIMEOUT, $this->timeout);
            }
        }

        if ($this->hasConnectionTimeout()) {
            if (\defined('CURLOPT_CONNECTTIMEOUT_MS')) {
                $curl->setOpt(\CURLOPT_CONNECTTIMEOUT_MS, $this->connection_timeout * 1000);
            } else {
                $curl->setOpt(\CURLOPT_CONNECTTIMEOUT, $this->connection_timeout);
            }
        }

        if ($this->follow_redirects === true) {
            $curl->setOpt(\CURLOPT_FOLLOWLOCATION, true);
            $curl->setOpt(\CURLOPT_MAXREDIRS, $this->max_redirects);
        }

        $curl->setOpt(\CURLOPT_SSL_VERIFYPEER, $this->strict_ssl);
        // zero is safe for all curl versions
        $verifyValue = $this->strict_ssl + 0;
        // support for value 1 removed in cURL 7.28.1 value 2 valid in all versions
        if ($verifyValue > 0) {
            ++$verifyValue;
        }
        $curl->setOpt(\CURLOPT_SSL_VERIFYHOST, $verifyValue);
        $curl->setOpt(\CURLOPT_RETURNTRANSFER, true);

        // set Content-Length to the size of the payload if present
        if ($this->payload !== []) {
            $curl->setOpt(\CURLOPT_POSTFIELDS, $this->serialized_payload);

            if (!$this->isUpload()) {
                $this->headers['Content-Length'] = $this->_determineLength($this->serialized_payload);
            }
        }

        $headers = [];
        // except header removes any HTTP 1.1 Continue from response headers
        $headers[] = 'Expect:';

        if (!isset($this->headers['User-Agent'])) {
            $headers[] = $this->buildUserAgent();
        }

        $headers[] = 'Content-Type: ' . $this->content_type;

        // allow custom Accept header if set
        if (!isset($this->headers['Accept'])) {
            // http://pretty-rfc.herokuapp.com/RFC2616#header.accept
            $accept = 'Accept: */*; q=0.5, text/plain; q=0.8, text/html;level=3;';

            if (!empty($this->expected_type)) {
                $accept .= 'q=0.9, ' . $this->expected_type;
            }

            $headers[] = $accept;
        }

        // Solve a bug on squid proxy, NONE/411 when miss content length.
        if (!isset($this->headers['Content-Length']) && !$this->isUpload()) {
            $this->headers['Content-Length'] = 0;
        }

        foreach ($this->headers as $header => $value) {
            if (\is_array($value)) {
                foreach ($value as $valueInner) {
                    $headers[] = "${header}: ${valueInner}";
                }
            } else {
                $headers[] = "${header}: ${value}";
            }
        }

        $url = \parse_url((string) $this->uri);

        if (\is_array($url) === false) {
            throw new ClientErrorException('Unable to connect to "' . $this->uri . '". => "parse_url" === false');
        }

        $path = ($url['path'] ?? '/') . (isset($url['query']) ? '?' . $url['query'] : '');
        $this->raw_headers = "{$this->method} ${path} HTTP/1.1\r\n";
        $host = ($url['host'] ?? 'localhost') . (isset($url['port']) ? ':' . $url['port'] : '');
        $this->raw_headers .= "Host: ${host}\r\n";
        $this->raw_headers .= \implode("\r\n", $headers);
        $this->raw_headers .= "\r\n";

        $curl->setOpt(\CURLOPT_HTTPHEADER, $headers);

        if ($this->_debug) {
            $curl->setOpt(\CURLOPT_VERBOSE, true);
        }

        $curl->setOpt(\CURLOPT_HEADER, 1);

        // If there are some additional curl opts that the user wants to set, we can tack them in here.
        foreach ($this->additional_curl_opts as $curlOpt => $curlVal) {
            $curl->setOpt($curlOpt, $curlVal);
        }

        if ($this->_protocol_version !== null) {
            switch ($this->_protocol_version) {
                case '0.0':
                    $curl->setOpt(\CURLOPT_HTTP_VERSION, \CURL_HTTP_VERSION_NONE);

                    break;
                case '1.0':
                    $curl->setOpt(\CURLOPT_HTTP_VERSION, \CURL_HTTP_VERSION_1_0);

                    break;
                case '1.1':
                    $curl->setOpt(\CURLOPT_HTTP_VERSION, \CURL_HTTP_VERSION_1_1);

                    break;
                case '2.0':
                    $curl->setOpt(\CURLOPT_HTTP_VERSION, \CURL_HTTP_VERSION_2_0);

                    break;
            }
        }

        $this->_curl = $curl;

        return $this;
    }

    /**
     * @param string|null $str payload
     *
     * @return int length of payload in bytes
     *
     * @internal
     */
    public function _determineLength($str): int
    {
        if ($str === null) {
            return 0;
        }

        return \strlen($str);
    }

    /**
     * Takes care of building the query string to be used in the request URI.
     *
     * Any existing query string parameters, either passed as part of the URI
     * via uri() method, or passed via get() and friends will be preserved,
     * with additional parameters (added via params() or param()) appended.
     *
     * @internal
     */
    public function _uriPrep()
    {
        if ($this->uri === null) {
            throw new ClientErrorException('Unable to connect. => "uri" === null');
        }

        $url = \parse_url((string) $this->uri);
        $originalParams = [];

        if ($url !== false) {
            if (
                isset($url['query'])
                &&
                $url['query']
            ) {
                \parse_str($url['query'], $originalParams);
            }

            $params = \array_merge($originalParams, $this->params);
        } else {
            $params = $this->params;
        }

        $queryString = \http_build_query($params);

        if (\strpos((string) $this->uri, '?') !== false) {
            $this->setUri($this->uri->withQuery(
                \substr(
                    (string) $this->uri,
                    0,
                    \strpos((string) $this->uri, '?')
                )
            ));
        }

        if (\count($params)) {
            $this->setUri($this->uri->withQuery($queryString));
        }
    }

    /**
     * Add an additional header to the request
     * and return an immutable version from this object.
     *
     * @param string $header_name
     * @param string $value
     *
     * @return static
     */
    public function addHeader($header_name, $value): self
    {
        $new = clone $this;

        $new->headers[$header_name] = $value;

        return $new;
    }

    /**
     * Add group of headers all at once.
     *
     * Note: This is here just as a convenience in very specific cases.
     * The preferred "readable" way would be to leverage the support for custom header methods.
     *
     * @param string[] $headers
     *
     * @return static
     */
    public function addHeaders(array $headers): self
    {
        $new = clone $this;

        foreach ($headers as $header => $value) {
            $new->_setHeaders([$header => $value]);
        }

        return $new;
    }

    /**
     * Semi-reluctantly added this as a way to add in curl opts
     * that are not otherwise accessible from the rest of the API.
     *
     * @param int   $curl_opt
     * @param mixed $curl_opt_val
     *
     * @return static
     */
    public function addOnCurlOption($curl_opt, $curl_opt_val): self
    {
        $this->additional_curl_opts[$curl_opt] = $curl_opt_val;

        return $this;
    }

    /**
     * @return static
     *
     * @see Request::serializePayload()
     */
    public function alwaysSerializePayload(): self
    {
        return $this->serializePayload(static::SERIALIZE_PAYLOAD_ALWAYS);
    }

    /**
     * @param array $files
     *
     * @return static
     */
    public function attach($files): self
    {
        $fInfo = \finfo_open(\FILEINFO_MIME_TYPE);

        if ($fInfo === false) {
            throw new \Exception('finfo_open() did not work');
        }

        foreach ($files as $key => $file) {
            $mimeType = \finfo_file($fInfo, $file);
            if ($mimeType !== false) {
                $this->payload[$key] = \curl_file_create($file, $mimeType, \basename($file));
            }
        }

        \finfo_close($fInfo);

        $this->contentType(Mime::UPLOAD);

        return $this;
    }

    /**
     * User Basic Auth.
     *
     * Only use when over SSL/TSL/HTTPS.
     *
     * @param string $username
     * @param string $password
     *
     * @return static
     */
    public function basicAuth($username, $password): self
    {
        $this->username = $username;
        $this->password = $password;

        return $this;
    }

    /**
     * Callback invoked after payload has been serialized but before the request has been built.
     *
     * @param callable $callback (Request $request)
     *
     * @return static
     */
    public function beforeSend(callable $callback): self
    {
        $this->send_callbacks[] = $callback;

        return $this;
    }

    /**
     * @return string
     */
    public function buildUserAgent(): string
    {
        $user_agent = 'User-Agent: Http/PhpClient (cURL/';
        $curl = \curl_version();

        if (isset($curl['version'])) {
            $user_agent .= $curl['version'];
        } else {
            $user_agent .= '?.?.?';
        }

        $user_agent .= ' PHP/' . \PHP_VERSION . ' (' . \PHP_OS . ')';

        if (isset($_SERVER['SERVER_SOFTWARE'])) {
            $tmp = \preg_replace('~PHP/[\d\.]+~U', '', $_SERVER['SERVER_SOFTWARE']);
            if (\is_string($tmp)) {
                $user_agent .= ' ' . $tmp;
            }
        } else {
            if (isset($_SERVER['TERM_PROGRAM'])) {
                $user_agent .= " {$_SERVER['TERM_PROGRAM']}";
            }

            if (isset($_SERVER['TERM_PROGRAM_VERSION'])) {
                $user_agent .= "/{$_SERVER['TERM_PROGRAM_VERSION']}";
            }
        }

        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $user_agent .= " {$_SERVER['HTTP_USER_AGENT']}";
        }

        $user_agent .= ')';

        return $user_agent;
    }

    /**
     * Use Client Side Cert Authentication
     *
     * @param string      $key        file path to client key
     * @param string      $cert       file path to client cert
     * @param string|null $passphrase for client key
     * @param string      $encoding   default PEM
     *
     * @return static
     */
    public function clientSideCertAuth($cert, $key, $passphrase = null, $encoding = 'PEM'): self
    {
        $this->client_cert = $cert;
        $this->client_key = $key;
        $this->client_passphrase = $passphrase;
        $this->client_encoding = $encoding;

        return $this;
    }

    /**
     * @param string|null $mime     use a constant from Mime::*
     * @param string|null $fallback use a constant from Mime::*
     *
     * @return static
     */
    public function contentType($mime, string $fallback = null): self
    {
        if (empty($mime) && empty($fallback)) {
            return $this;
        }

        if (empty($mime)) {
            $mime = $fallback;
        }

        if (empty($mime)) {
            return $this;
        }

        $this->content_type = Mime::getFullMime($mime);
        if ($this->isUpload()) {
            $this->neverSerializePayload();
        }

        return $this;
    }

    /**
     * @return static
     */
    public function contentTypeCsv(): self
    {
        $this->content_type = Mime::getFullMime(Mime::CSV);

        return $this;
    }

    /**
     * @return static
     */
    public function contentTypeForm(): self
    {
        $this->content_type = Mime::getFullMime(Mime::FORM);

        return $this;
    }

    /**
     * @return static
     */
    public function contentTypeHtml(): self
    {
        $this->content_type = Mime::getFullMime(Mime::HTML);

        return $this;
    }

    /**
     * @return static
     */
    public function contentTypeJson(): self
    {
        $this->content_type = Mime::getFullMime(Mime::JSON);

        return $this;
    }

    /**
     * @return static
     */
    public function contentTypePlain(): self
    {
        $this->content_type = Mime::getFullMime(Mime::PLAIN);

        return $this;
    }

    /**
     * @return static
     */
    public function contentTypeXml(): self
    {
        $this->content_type = Mime::getFullMime(Mime::XML);

        return $this;
    }

    /**
     * HTTP Method Delete
     *
     * @param string|UriInterface $uri  optional uri to use
     * @param string|null         $mime
     *
     * @return static
     */
    public static function delete($uri, string $mime = null): self
    {
        if ($uri instanceof UriInterface) {
            $uri = (string) $uri;
        }

        return (new self(Http::DELETE))
            ->setUriFromString($uri)
            ->mime($mime);
    }

    /**
     * User Digest Auth.
     *
     * @param string $username
     * @param string $password
     *
     * @return static
     */
    public function digestAuth($username, $password): self
    {
        $this->addOnCurlOption(\CURLOPT_HTTPAUTH, \CURLAUTH_DIGEST);

        return $this->basicAuth($username, $password);
    }

    /**
     * @return static
     *
     * @see Request::_autoParse()
     */
    public function disableAutoParsing(): self
    {
        return $this->_autoParse(false);
    }

    /**
     * @return static
     */
    public function disableStrictSSL(): self
    {
        return $this->_strictSSL(false);
    }

    /**
     * @return static
     *
     * @see Request::followRedirects()
     */
    public function doNotFollowRedirects(): self
    {
        return $this->followRedirects(false);
    }

    /**
     * @return static
     *
     * @see Request::_autoParse()
     */
    public function enableAutoParsing(): self
    {
        return $this->_autoParse(true);
    }

    /**
     * @return static
     */
    public function enableStrictSSL(): self
    {
        return $this->_strictSSL(true);
    }

    /**
     * @return static
     */
    public function expectsCsv(): self
    {
        return $this->expectsType(Mime::CSV);
    }

    /**
     * @return static
     */
    public function expectsForm(): self
    {
        return $this->expectsType(Mime::FORM);
    }

    /**
     * @return static
     */
    public function expectsHtml(): self
    {
        return $this->expectsType(Mime::HTML);
    }

    /**
     * @return static
     */
    public function expectsJavascript(): self
    {
        return $this->expectsType(Mime::JS);
    }

    /**
     * @return static
     */
    public function expectsJs(): self
    {
        return $this->expectsType(Mime::JS);
    }

    /**
     * @return static
     */
    public function expectsJson(): self
    {
        return $this->expectsType(Mime::JSON);
    }

    /**
     * @return static
     */
    public function expectsPlain(): self
    {
        return $this->expectsType(Mime::PLAIN);
    }

    /**
     * @return static
     */
    public function expectsText(): self
    {
        return $this->expectsType(Mime::PLAIN);
    }

    /**
     * @param string|null $mime     use a constant from Mime::*
     * @param string|null $fallback use a constant from Mime::*
     *
     * @return static
     */
    public function expectsType($mime, string $fallback = null): self
    {
        if (empty($mime) && empty($fallback)) {
            return $this;
        }

        if (empty($mime)) {
            $mime = $fallback;
        }

        if (empty($mime)) {
            return $this;
        }

        $this->expected_type = Mime::getFullMime($mime);

        return $this;
    }

    /**
     * @return static
     */
    public function expectsUpload(): self
    {
        return $this->expectsType(Mime::UPLOAD);
    }

    /**
     * @return static
     */
    public function expectsXhtml(): self
    {
        return $this->expectsType(Mime::XHTML);
    }

    /**
     * @return static
     */
    public function expectsXml(): self
    {
        return $this->expectsType(Mime::XML);
    }

    /**
     * @return static
     */
    public function expectsYaml(): self
    {
        return $this->expectsType(Mime::YAML);
    }

    /**
     * If the response is a 301 or 302 redirect, automatically
     * send off another request to that location
     *
     * @param bool $follow follow or not to follow or maximal number of redirects
     *
     * @return static
     */
    public function followRedirects(bool $follow = true): self
    {
        if ($follow === true) {
            $this->max_redirects = static::MAX_REDIRECTS_DEFAULT;
        } elseif ($follow === false) {
            $this->max_redirects = 0;
        } else {
            $this->max_redirects = \max(0, $follow);
        }

        $this->follow_redirects = $follow;

        return $this;
    }

    /**
     * HTTP Method Get
     *
     * @param string|UriInterface $uri  optional uri to use
     * @param string              $mime expected
     *
     * @return static
     */
    public static function get($uri, string $mime = null): self
    {
        if ($uri instanceof UriInterface) {
            $uri = (string) $uri;
        }

        return (new self(Http::GET))
            ->setUriFromString($uri)
            ->mime($mime);
    }

    /**
     * Gets the body of the message.
     *
     * @return StreamInterface returns the body as a stream
     */
    public function getBody(): StreamInterface
    {
        return Http::stream($this->payload);
    }

    /**
     * Retrieves a message header value by the given case-insensitive name.
     *
     * This method returns an array of all the header values of the given
     * case-insensitive header name.
     *
     * If the header does not appear in the message, this method MUST return an
     * empty array.
     *
     * @param string $name case-insensitive header field name
     *
     * @return string[] An array of string values as provided for the given
     *                  header. If the header does not appear in the message, this method MUST
     *                  return an empty array.
     */
    public function getHeader($name): array
    {
        $name = \strtolower($name);
        if (!isset($this->headerNames[$name])) {
            return [];
        }

        $name = $this->headerNames[$name];

        return $this->headers[$name];
    }

    /**
     * Retrieves a comma-separated string of the values for a single header.
     *
     * This method returns all of the header values of the given
     * case-insensitive header name as a string concatenated together using
     * a comma.
     *
     * NOTE: Not all header values may be appropriately represented using
     * comma concatenation. For such headers, use getHeader() instead
     * and supply your own delimiter when concatenating.
     *
     * If the header does not appear in the message, this method MUST return
     * an empty string.
     *
     * @param string $name case-insensitive header field name
     *
     * @return string A string of values as provided for the given header
     *                concatenated together using a comma. If the header does not appear in
     *                the message, this method MUST return an empty string.
     */
    public function getHeaderLine($name): string
    {
        return \implode(', ', $this->getHeader($name));
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Retrieves the HTTP method of the request.
     *
     * @return string returns the request method
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Retrieves the HTTP protocol version as a string.
     *
     * The string MUST contain only the HTTP version number (e.g., "1.1", "1.0").
     *
     * @return string HTTP protocol version
     */
    public function getProtocolVersion(): string
    {
        return $this->_protocol_version ?? '';
    }

    /**
     * Retrieves the message's request target.
     *
     * Retrieves the message's request-target either as it will appear (for
     * clients), as it appeared at request (for servers), or as it was
     * specified for the instance (see withRequestTarget()).
     *
     * In most cases, this will be the origin-form of the composed URI,
     * unless a value was provided to the concrete implementation (see
     * withRequestTarget() below).
     *
     * If no URI is available, and no request-target has been specifically
     * provided, this method MUST return the string "/".
     *
     * @return string
     */
    public function getRequestTarget(): string
    {
        if ($this->uri === null) {
            return '/';
        }

        $target = $this->uri->getPath();

        if (!$target) {
            $target = '/';
        }

        if ($this->uri->getQuery() !== '') {
            $target .= '?' . $this->uri->getQuery();
        }

        return $target;
    }

    /**
     * @return \Httpful\Uri|\Psr\Http\Message\UriInterface|null
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * Checks if a header exists by the given case-insensitive name.
     *
     * @param string $name case-insensitive header field name
     *
     * @return bool Returns true if any header names match the given header
     *              name using a case-insensitive string comparison. Returns false if
     *              no matching header name is found in the message.
     */
    public function hasHeader($name): bool
    {
        return isset($this->headerNames[\strtolower($name)]);
    }

    /**
     * Return an instance with the specified header appended with the given value.
     *
     * Existing values for the specified header will be maintained. The new
     * value(s) will be appended to the existing list. If the header did not
     * exist previously, it will be added.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new header and/or value.
     *
     * @param string          $name  case-insensitive header field name to add
     * @param string|string[] $value header value(s)
     *
     * @throws \InvalidArgumentException for invalid header names or values
     *
     * @return static
     */
    public function withAddedHeader($name, $value)
    {
        if (!\is_string($name) || $name === '') {
            throw new \InvalidArgumentException('Header name must be an RFC 7230 compatible string.');
        }

        $new = clone $this;

        $new->_setHeaders([$name => $value]);

        return $new;
    }

    /**
     * Return an instance with the specified message body.
     *
     * The body MUST be a StreamInterface object.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return a new instance that has the
     * new body stream.
     *
     * @param StreamInterface $body
     *
     * @throws \InvalidArgumentException when the body is not valid
     *
     * @return static
     *
     * @internal
     */
    public function withBody(StreamInterface $body)
    {
        $stream = Http::stream($body);

        return $this->_setBody($stream->getContents(), null);
    }

    /**
     * @param string $body
     *
     * @return static
     */
    public function withBodyFromString(string $body)
    {
        $stream = Http::stream($body);

        return $this->_setBody($stream->getContents(), null);
    }

    /**
     * @param array $body
     *
     * @return static
     */
    public function withBodyFromArray(array $body)
    {
        return $this->_setBody($body, null);
    }

    /**
     * @param string $name
     * @param string $value
     *
     * @return static
     */
    public function withCookie(string $name, string $value): self
    {
        return $this->withHeader('Cookie', "${name}=${value}");
    }

    /**
     * @param string $name
     * @param string $value
     *
     * @return static
     */
    public function withAddedCookie(string $name, string $value): self
    {
        return $this->withAddedHeader('Cookie', "${name}=${value}");
    }

    /**
     * Return an instance with the provided value replacing the specified header.
     *
     * While header names are case-insensitive, the casing of the header will
     * be preserved by this function, and returned from getHeaders().
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new and/or updated header and value.
     *
     * @param string          $name  case-insensitive header field name
     * @param string|string[] $value header value(s)
     *
     * @throws \InvalidArgumentException for invalid header names or values
     *
     * @return static
     */
    public function withHeader($name, $value): self
    {
        $value = $this->_validateAndTrimHeader($name, $value);
        $normalized = \strtolower($name);

        $new = clone $this;

        if (isset($new->headerNames[$normalized])) {
            unset($new->headers[$new->headerNames[$normalized]]);
        }

        $new->headerNames[$normalized] = $name;
        $new->headers[$name] = $value;

        return $new;
    }

    /**
     * @param string[] $header
     *
     * @return static
     */
    public function withHeaders(array $header)
    {
        $new = clone $this;

        foreach ($header as  $name => $value) {
            $new = $new->withHeader($name, $value);
        }

        return $new;
    }

    /**
     * Return an instance with the provided HTTP method.
     *
     * While HTTP method names are typically all uppercase characters, HTTP
     * method names are case-sensitive and thus implementations SHOULD NOT
     * modify the given string.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * changed request method.
     *
     * @param string $method case-sensitive method
     *
     * @throws \InvalidArgumentException for invalid HTTP methods
     *
     * @return static
     */
    public function withMethod($method)
    {
        $new = clone $this;

        $new->_method($method);

        return $new;
    }

    /**
     * Return an instance with the specified HTTP protocol version.
     *
     * The version string MUST contain only the HTTP version number (e.g.,
     * "1.1", "1.0").
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new protocol version.
     *
     * @param string $version HTTP protocol version
     *
     * @return static
     */
    public function withProtocolVersion($version)
    {
        $new = clone $this;

        $new->_protocol_version = $version;

        return $new;
    }

    /**
     * Return an instance with the specific request-target.
     *
     * If the request needs a non-origin-form request-target — e.g., for
     * specifying an absolute-form, authority-form, or asterisk-form —
     * this method may be used to create an instance with the specified
     * request-target, verbatim.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * changed request target.
     *
     * @see http://tools.ietf.org/html/rfc7230#section-5.3 (for the various
     *     request-target forms allowed in request messages)
     *
     * @param mixed $requestTarget
     *
     * @return static
     */
    public function withRequestTarget($requestTarget)
    {
        if (\preg_match('#\\s#', $requestTarget)) {
            throw new \InvalidArgumentException('Invalid request target provided; cannot contain whitespace');
        }

        $new = clone $this;

        if ($new->uri !== null) {
            $new->setUri($new->uri->withPath($requestTarget));
        }

        return $new;
    }

    /**
     * Returns an instance with the provided URI.
     *
     * This method MUST update the Host header of the returned request by
     * default if the URI contains a host component. If the URI does not
     * contain a host component, any pre-existing Host header MUST be carried
     * over to the returned request.
     *
     * You can opt-in to preserving the original state of the Host header by
     * setting `$preserveHost` to `true`. When `$preserveHost` is set to
     * `true`, this method interacts with the Host header in the following ways:
     *
     * - If the Host header is missing or empty, and the new URI contains
     *   a host component, this method MUST update the Host header in the returned
     *   request.
     * - If the Host header is missing or empty, and the new URI does not contain a
     *   host component, this method MUST NOT update the Host header in the returned
     *   request.
     * - If a Host header is present and non-empty, this method MUST NOT update
     *   the Host header in the returned request.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new UriInterface instance.
     *
     * @see http://tools.ietf.org/html/rfc3986#section-4.3
     *
     * @param UriInterface $uri          new request URI to use
     * @param bool         $preserveHost preserve the original state of the Host header
     *
     * @return static
     */
    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        if ($this->uri === $uri) {
            return $this;
        }

        $new = clone $this;

        $new->setUri($uri);

        return $new;
    }

    /**
     * Return an instance without the specified header.
     *
     * Header resolution MUST be done without case-sensitivity.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that removes
     * the named header.
     *
     * @param string $name case-insensitive header field name to remove
     *
     * @return static
     */
    public function withoutHeader($name): self
    {
        $normalized = \strtolower($name);
        if (!isset($this->headerNames[$normalized])) {
            return $this;
        }

        $name = $this->headerNames[$normalized];

        $new = clone $this;

        unset($new->headers[$name], $new->headerNames[$normalized]);

        return $new;
    }

    /**
     * @return string
     */
    public function getContentType(): string
    {
        return $this->content_type;
    }

    /**
     * @return callable|LoggerInterface|null
     */
    public function getErrorHandler()
    {
        return $this->error_handler;
    }

    /**
     * @return string
     */
    public function getExpectedType(): string
    {
        return $this->expected_type;
    }

    /**
     * @return string
     */
    public function getHttpMethod(): string
    {
        return $this->method;
    }

    /**
     * @return \ArrayObject
     */
    public function getIterator(): \ArrayObject
    {
        // init
        $elements = new \ArrayObject();

        foreach (\get_object_vars($this) as $f => $v) {
            $elements[$f] = $v;
        }

        return $elements;
    }

    /**
     * @return callable|null
     */
    public function getParseCallback()
    {
        return $this->parse_callback;
    }

    /**
     * @return array
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @return string
     */
    public function getRawHeaders(): string
    {
        return $this->raw_headers;
    }

    /**
     * @return callable[]
     */
    public function getSendCallback(): array
    {
        return $this->send_callbacks;
    }

    /**
     * @return int
     */
    public function getSerializePayloadMethod(): int
    {
        return $this->serialize_payload_method;
    }

    /**
     * @return mixed|null
     */
    public function getSerializedPayload()
    {
        return $this->serialized_payload;
    }

    /**
     * @return string
     */
    public function getUriString(): string
    {
        return (string) $this->uri;
    }

    /**
     * Is this request setup for basic auth?
     *
     * @return bool
     */
    public function hasBasicAuth(): bool
    {
        return $this->password && $this->username;
    }

    /**
     * @return bool has the internal curl request been initialized?
     */
    public function hasBeenInitialized(): bool
    {
        return isset($this->_curl->curl);
    }

    /**
     * @return bool is this request setup for client side cert?
     */
    public function hasClientSideCert(): bool
    {
        return $this->client_cert && $this->client_key;
    }

    /**
     * @return bool does the request have a connection timeout?
     */
    public function hasConnectionTimeout(): bool
    {
        return isset($this->connection_timeout);
    }

    /**
     * Is this request setup for digest auth?
     *
     * @return bool
     */
    public function hasDigestAuth(): bool
    {
        return $this->password
               &&
               $this->username
               &&
               $this->additional_curl_opts[\CURLOPT_HTTPAUTH] === \CURLAUTH_DIGEST;
    }

    /**
     * @return bool
     */
    public function hasParseCallback(): bool
    {
        return isset($this->parse_callback)
               &&
               \is_callable($this->parse_callback);
    }

    /**
     * @return bool is this request setup for using proxy?
     */
    public function hasProxy(): bool
    {
        /**
         *  We must be aware that proxy variables could come from environment also.
         *  In curl extension, http proxy can be specified not only via CURLOPT_PROXY option,
         *  but also by environment variable called http_proxy.
         */
        return (
                   isset($this->additional_curl_opts[\CURLOPT_PROXY])
                   &&
                   \is_string($this->additional_curl_opts[\CURLOPT_PROXY])
               )
               ||
               \getenv('http_proxy');
    }

    /**
     * @return bool does the request have a timeout?
     */
    public function hasTimeout(): bool
    {
        return isset($this->timeout);
    }

    /**
     * HTTP Method Head
     *
     * @param string|UriInterface $uri optional uri to use
     *
     * @return static
     */
    public static function head($uri): self
    {
        if ($uri instanceof UriInterface) {
            $uri = (string) $uri;
        }

        return (new self(Http::HEAD))
            ->setUriFromString($uri)
            ->mime(Mime::PLAIN);
    }

    /**
     * @return bool
     */
    public function isAutoParse(): bool
    {
        return $this->auto_parse;
    }

    /**
     * @return bool
     */
    public function isStrictSSL(): bool
    {
        return $this->strict_ssl;
    }

    /**
     * @return bool
     */
    public function isUpload(): bool
    {
        return $this->content_type === Mime::UPLOAD;
    }

    /**
     * Helper function to set the Content type and Expected as same in one swoop.
     *
     * @param string|null $mime mime type to use for content type and expected return type
     *
     * @return static
     */
    public function mime($mime): self
    {
        if (empty($mime)) {
            return $this;
        }

        $this->expected_type = Mime::getFullMime($mime);
        $this->content_type = $this->expected_type;

        if ($this->isUpload()) {
            $this->neverSerializePayload();
        }

        return $this;
    }

    /**
     * @param string|null $mime
     *
     * @return static
     */
    public function mimeType($mime): self
    {
        return $this->mime($mime);
    }

    /**
     * @return static
     *
     * @see Request::serializePayload()
     */
    public function neverSerializePayload(): self
    {
        return $this->serializePayload(static::SERIALIZE_PAYLOAD_NEVER);
    }

    /**
     * @param string $username
     * @param string $password
     *
     * @return static
     */
    public function ntlmAuth($username, $password): self
    {
        $this->addOnCurlOption(\CURLOPT_HTTPAUTH, \CURLAUTH_NTLM);

        return $this->basicAuth($username, $password);
    }

    /**
     * HTTP Method Options
     *
     * @param string|UriInterface $uri optional uri to use
     *
     * @return static
     */
    public static function options($uri): self
    {
        if ($uri instanceof UriInterface) {
            $uri = $uri->__toString();
        }

        return (new self(Http::OPTIONS))->setUriFromString($uri);
    }

    /**
     * Add additional parameter to be appended to the query string.
     *
     * @param string $key
     * @param string $value
     *
     * @return static this
     */
    public function param($key, $value): self
    {
        if ($key && $value) {
            $this->params[$key] = $value;
        }

        return $this;
    }

    /**
     * Add additional parameters to be appended to the query string.
     *
     * Takes an associative array of key/value pairs as an argument.
     *
     * @param array $params
     *
     * @return static this
     */
    public function params(array $params): self
    {
        $this->params = \array_merge($this->params, $params);

        return $this;
    }

    /**
     * @param callable $callback
     *
     * @return static
     *
     * @see Request::parseResponsesWith()
     */
    public function parseResponsesWith(callable $callback): self
    {
        return $this->setParseCallback($callback);
    }

    /**
     * HTTP Method Patch
     *
     * @param string|UriInterface $uri     optional uri to use
     * @param mixed               $payload data to send in body of request
     * @param string              $mime    MIME to use for Content-Type
     *
     * @return static
     */
    public static function patch($uri, $payload = null, string $mime = null): self
    {
        if ($uri instanceof UriInterface) {
            $uri = $uri->__toString();
        }

        return (new self(Http::PATCH))
            ->setUriFromString($uri)
            ->_setBody($payload, null, $mime);
    }

    /**
     * HTTP Method Post
     *
     * @param string|UriInterface $uri     optional uri to use
     * @param mixed               $payload data to send in body of request
     * @param string              $mime    MIME to use for Content-Type
     *
     * @return static
     */
    public static function post($uri, $payload = null, string $mime = null): self
    {
        if ($uri instanceof UriInterface) {
            $uri = (string) $uri;
        }

        return (new self(Http::POST))
            ->setUriFromString($uri)
            ->_setBody($payload, null, $mime);
    }

    /**
     * HTTP Method Put
     *
     * @param string|UriInterface $uri     optional uri to use
     * @param mixed               $payload data to send in body of request
     * @param string              $mime    MIME to use for Content-Type
     *
     * @return static
     */
    public static function put($uri, $payload = null, string $mime = null): self
    {
        if ($uri instanceof UriInterface) {
            $uri = (string) $uri;
        }

        return (new self(Http::PUT))
            ->setUriFromString($uri)
            ->_setBody($payload, null, $mime);
    }

    /**
     * Register a callback that will be used to serialize the payload
     * for a particular mime type.  When using "*" for the mime
     * type, it will use that parser for all responses regardless of the mime
     * type.  If a custom '*' and 'application/json' exist, the custom
     * 'application/json' would take precedence over the '*' callback.
     *
     * @param string   $mime     mime type we're registering
     * @param callable $callback takes one argument, $payload,
     *                           which is the payload that we'll be
     *
     * @return static
     */
    public function registerPayloadSerializer($mime, callable $callback): self
    {
        $this->payload_serializers[Mime::getFullMime($mime)] = $callback;

        return $this;
    }

    /**
     * Actually send off the request, and parse the response
     *
     *@throws NetworkErrorException when unable to parse or communicate w server
     *
     * @return Response with parsed results
     */
    public function send(): Response
    {
        if (!$this->hasBeenInitialized()) {
            $this->_curlPrep();
        }

        if ($this->_curl === null) {
            throw new NetworkErrorException('Unable to connect to "' . $this->uri . '". => "curl" === null');
        }

        $result = $this->_curl->exec();
        $response = $this->_buildResponse($result);

        $this->_curl->close();
        $this->_curl = null;

        return $response;
    }

    /**
     * @return static
     */
    public function sendsCsv(): self
    {
        return $this->contentType(Mime::CSV);
    }

    /**
     * @return static
     */
    public function sendsForm(): self
    {
        return $this->contentType(Mime::FORM);
    }

    /**
     * @return static
     */
    public function sendsHtml(): self
    {
        return $this->contentType(Mime::HTML);
    }

    /**
     * @return static
     */
    public function sendsJavascript(): self
    {
        return $this->contentType(Mime::JS);
    }

    /**
     * @return static
     */
    public function sendsJs(): self
    {
        return $this->contentType(Mime::JS);
    }

    /**
     * @return static
     */
    public function sendsJson(): self
    {
        return $this->contentType(Mime::JSON);
    }

    /**
     * @return static
     */
    public function sendsPlain(): self
    {
        return $this->contentType(Mime::PLAIN);
    }

    /**
     * @return static
     */
    public function sendsText(): self
    {
        return $this->contentType(Mime::PLAIN);
    }

    /**
     * @return static
     */
    public function sendsUpload(): self
    {
        return $this->contentType(Mime::UPLOAD);
    }

    /**
     * @return static
     */
    public function sendsXhtml(): self
    {
        return $this->contentType(Mime::XHTML);
    }

    /**
     * @return static
     */
    public function sendsXml(): self
    {
        return $this->contentType(Mime::XML);
    }

    /**
     * @return static
     */
    public function sendsYaml(): self
    {
        return $this->contentType(Mime::YAML);
    }

    /**
     * Determine how/if we use the built in serialization by
     * setting the serialize_payload_method
     * The default (SERIALIZE_PAYLOAD_SMART) is...
     *  - if payload is not a scalar (object/array)
     *    use the appropriate serialize method according to
     *    the Content-Type of this request.
     *  - if the payload IS a scalar (int, float, string, bool)
     *    than just return it as is.
     * When this option is set SERIALIZE_PAYLOAD_ALWAYS,
     * it will always use the appropriate
     * serialize option regardless of whether payload is scalar or not
     * When this option is set SERIALIZE_PAYLOAD_NEVER,
     * it will never use any of the serialization methods.
     * Really the only use for this is if you want the serialize methods
     * to handle strings or not (e.g. Blah is not valid JSON, but "Blah"
     * is).  Forcing the serialization helps prevent that kind of error from
     * happening.
     *
     * @param int $mode
     *
     * @return static
     */
    public function serializePayload($mode): self
    {
        $this->serialize_payload_method = $mode;

        return $this;
    }

    /**
     * @param callable $callback
     *
     * @return static
     *
     * @see Request::registerPayloadSerializer()
     */
    public function serializePayloadWith(callable $callback): self
    {
        return $this->registerPayloadSerializer('*', $callback);
    }

    /**
     * Specify a HTTP connection timeout
     *
     * @param float|int $connection_timeout seconds to timeout the HTTP connection
     *
     * @throws \InvalidArgumentException
     *
     * @return static
     */
    public function setConnectionTimeoutInSeconds($connection_timeout): self
    {
        if (!\preg_match('/^\d+(\.\d+)?/', (string) $connection_timeout)) {
            throw new \InvalidArgumentException(
                'Invalid connection timeout provided: ' . \var_export($connection_timeout, true)
            );
        }

        $this->connection_timeout = $connection_timeout;

        return $this;
    }

    /**
     * Callback called to handle HTTP errors. When nothing is set, defaults
     * to logging via `error_log`.
     *
     * @param callable|LoggerInterface|null $error_handler
     *
     * @return static
     */
    public function setErrorHandler($error_handler): self
    {
        $this->error_handler = $error_handler;

        return $this;
    }

    /**
     * Use a custom function to parse the response.
     *
     * @param callable $callback Takes the raw body of
     *                           the http response and returns a mixed
     *
     * @return static
     */
    public function setParseCallback(callable $callback): self
    {
        $this->parse_callback = $callback;

        return $this;
    }

    /**
     * @param callable|null $send_callback
     *
     * @return static
     */
    public function setSendCallback($send_callback): self
    {
        if (!empty($send_callback)) {
            $this->send_callbacks[] = $send_callback;
        }

        return $this;
    }

    /**
     * @param UriInterface $uri
     *
     * @return static
     */
    public function setUri(UriInterface $uri): self
    {
        $this->uri = $uri;

        $this->_updateHostFromUri();

        return $this;
    }

    /**
     * @param string $body
     *
     * @return static
     */
    public function setBodyFromString(string $body): self
    {
        $this->_setBody($body);

        return $this;
    }

    /**
     * @param string $uri
     *
     * @return static
     */
    public function setUriFromString(string $uri): self
    {
        $this->setUri(new Uri($uri));

        return $this;
    }

    /**
     * Sets user agent.
     *
     * @param string $userAgent
     *
     * @return static
     */
    public function setUserAgent($userAgent): self
    {
        return $this->addHeader('User-Agent', $userAgent);
    }

    /**
     * This method is the default behavior
     *
     * @return static
     *
     * @see Request::serializePayload()
     */
    public function smartSerializePayload(): self
    {
        return $this->serializePayload(static::SERIALIZE_PAYLOAD_SMART);
    }

    /**
     * Specify a HTTP timeout
     *
     * @param float|int $timeout seconds to timeout the HTTP call
     *
     * @return static
     */
    public function timeout($timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * Use proxy configuration
     *
     * @param string   $proxy_host    Hostname or address of the proxy
     * @param int      $proxy_port    Port of the proxy. Default 80
     * @param int|null $auth_type     Authentication type or null. Accepted values are CURLAUTH_BASIC, CURLAUTH_NTLM.
     *                                Default null, no authentication
     * @param string   $auth_username Authentication username. Default null
     * @param string   $auth_password Authentication password. Default null
     * @param int      $proxy_type    Proxy-Tye for Curl. Default is "Proxy::HTTP"
     *
     * @return static
     */
    public function useProxy(
        $proxy_host,
        $proxy_port = 80,
        $auth_type = null,
        $auth_username = null,
        $auth_password = null,
        $proxy_type = Proxy::HTTP
    ): self {
        $this->addOnCurlOption(\CURLOPT_PROXY, "{$proxy_host}:{$proxy_port}");
        $this->addOnCurlOption(\CURLOPT_PROXYTYPE, $proxy_type);

        if (\in_array($auth_type, [\CURLAUTH_BASIC, \CURLAUTH_NTLM], true)) {
            $this->addOnCurlOption(\CURLOPT_PROXYAUTH, $auth_type)
                ->addOnCurlOption(\CURLOPT_PROXYUSERPWD, "{$auth_username}:{$auth_password}");
        }

        return $this;
    }

    /**
     * Shortcut for useProxy to configure SOCKS 4 proxy
     *
     * @param string   $proxy_host    Hostname or address of the proxy
     * @param int      $proxy_port    Port of the proxy. Default 80
     * @param int|null $auth_type     Authentication type or null. Accepted values are CURLAUTH_BASIC, CURLAUTH_NTLM.
     *                                Default null, no authentication
     * @param string   $auth_username Authentication username. Default null
     * @param string   $auth_password Authentication password. Default null
     *
     * @return static
     *
     * @see Request::useProxy
     */
    public function useSocks4Proxy(
        $proxy_host,
        $proxy_port = 80,
        $auth_type = null,
        $auth_username = null,
        $auth_password = null
    ): self {
        return $this->useProxy(
            $proxy_host,
            $proxy_port,
            $auth_type,
            $auth_username,
            $auth_password,
            Proxy::SOCKS4
        );
    }

    /**
     * Shortcut for useProxy to configure SOCKS 5 proxy
     *
     * @param string      $proxy_host
     * @param int         $proxy_port
     * @param int|null    $auth_type
     * @param string|null $auth_username
     * @param string|null $auth_password
     *
     * @return static
     *
     * @see Request::useProxy
     */
    public function useSocks5Proxy(
        $proxy_host,
        $proxy_port = 80,
        $auth_type = null,
        $auth_username = null,
        $auth_password = null
    ): self {
        return $this->useProxy(
            $proxy_host,
            $proxy_port,
            $auth_type,
            $auth_username,
            $auth_password,
            Proxy::SOCKS5
        );
    }

    /**
     * @param string $userAgent
     *
     * @return static
     */
    public function withUserAgent($userAgent): self
    {
        return $this->addHeader('User-Agent', $userAgent);
    }

    /**
     * Set the method.  Shouldn't be called often as the preferred syntax
     * for instantiation is the method specific factory methods.
     *
     * @param string|null $method
     *
     * @return static
     */
    private function _method($method): self
    {
        if (empty($method)) {
            return $this;
        }

        if (!\in_array($method, Http::allMethods(), true)) {
            throw new RequestException($this, 'Unknown HTTP method: \'' . \strip_tags($method) . '\'');
        }

        $this->method = $method;

        return $this;
    }

    /**
     * @param bool $auto_parse perform automatic "smart"
     *                         parsing based on Content-Type or "expectedType"
     *                         If not auto parsing, Response->body returns the body
     *                         as a string
     *
     * @return static
     */
    private function _autoParse(bool $auto_parse = true): self
    {
        $this->auto_parse = $auto_parse;

        return $this;
    }

    /**
     * Takes a curl result and generates a Response from it.
     *
     * @param false|mixed $result
     *
     * @throws NetworkErrorException
     *
     * @return Response
     */
    private function _buildResponse($result): Response
    {
        if ($this->_curl === null) {
            throw new NetworkErrorException('Unable to build the response for "' . $this->uri . '". => "curl" === null');
        }

        if ($result === false) {
            $curlErrorNumber = $this->_curl->getErrorCode();
            if ($curlErrorNumber) {
                $curlErrorString = $this->_curl->getErrorMessage();

                $this->_error($curlErrorString);

                $exception = new NetworkErrorException(
                    'Unable to connect to "' . $this->uri . '": ' . $curlErrorNumber . ' ' . $curlErrorString,
                    $curlErrorNumber,
                    null,
                    $this->_curl,
                    $this
                );

                $exception->setCurlErrorNumber($curlErrorNumber)->setCurlErrorString($curlErrorString);

                throw $exception;
            }

            $this->_error('Unable to connect to "' . $this->uri . '".');

            throw new NetworkErrorException('Unable to connect to "' . $this->uri . '".');
        }

        $this->_info = $this->_curl->getInfo();

        $headers = $this->_curl->getRawResponseHeaders();

        $body = UTF8::remove_left(
            (string) $this->_curl->getRawResponse(),
            $headers
        );

        // get the protocol + version
        $protocol_version_regex = "/HTTP\/(?<version>[\d\.]*+)/i";
        $protocol_version_matches = [];
        $protocol_version = null;
        \preg_match($protocol_version_regex, $headers, $protocol_version_matches);
        if (isset($protocol_version_matches['version'])) {
            $protocol_version = $protocol_version_matches['version'];
        }
        $this->_info['protocol_version'] = $protocol_version;

        return new Response(
            $body,
            $headers,
            $this,
            $this->_info
        );
    }

    /**
     * @param string $error
     */
    private function _error($error)
    {
        // global error handling

        $global_error_handler = Setup::getGlobalErrorHandler();
        if ($global_error_handler) {
            if ($global_error_handler instanceof LoggerInterface) {
                // PSR-3 https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md
                $global_error_handler->error($error);
            } elseif (\is_callable($global_error_handler)) {
                // error callback
                \call_user_func($global_error_handler, $error);
            }
        }

        // local error handling

        if (isset($this->error_handler)) {
            if ($this->error_handler instanceof LoggerInterface) {
                // PSR-3 https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md
                $this->error_handler->error($error);
            } elseif (\is_callable($this->error_handler)) {
                // error callback
                \call_user_func($this->error_handler, $error);
            }
        } else {
            /** @noinspection ForgottenDebugOutputInspection */
            \error_log($error);
        }
    }

    /**
     * Turn payload from structured data into a string based on the current Mime type.
     * This uses the auto_serialize option to determine it's course of action.
     *
     * See serialize method for more.
     *
     * Added in support for custom payload serializers.
     * The serialize_payload_method stuff still holds true though.
     *
     * @param array $payload
     *
     * @return mixed
     *
     * @see Request::registerPayloadSerializer()
     */
    private function _serializePayload(array $payload)
    {
        if (empty($payload)) {
            return '';
        }

        if ($this->serialize_payload_method === static::SERIALIZE_PAYLOAD_NEVER) {
            return $payload;
        }

        // When we are in "smart" mode, don't serialize strings/scalars, assume they are already serialized.
        if (
            $this->serialize_payload_method === static::SERIALIZE_PAYLOAD_SMART
            &&
            \count($payload) === 1
            &&
            \array_keys($payload)[0] === 0
            &&
            \is_scalar($payload_first = \array_values($payload)[0])
            &&
            !\is_array($payload_first)
        ) {
            return $payload_first;
        }

        // Use a custom serializer if one is registered for this mime type.
        if (
            isset($this->payload_serializers['*'])
            ||
            isset($this->payload_serializers[$this->content_type])
        ) {
            if (isset($this->payload_serializers[$this->content_type])) {
                $key = $this->content_type;
            } else {
                $key = '*';
            }

            return \call_user_func($this->payload_serializers[$key], $payload);
        }

        return Setup::setupGlobalMimeType($this->content_type)->serialize($payload);
    }

    /**
     * Set the body of the request.
     *
     * @param mixed|null  $payload
     * @param mixed|null  $key
     * @param string|null $mimeType currently, sets the sends AND expects mime type although this
     *                              behavior may change in the next minor release (as it is a potential breaking change)
     *
     * @return static
     */
    private function _setBody($payload, $key = null, string $mimeType = null): self
    {
        $this->mime($mimeType);

        if (!empty($payload)) {
            if (\is_array($payload)) {
                foreach ($payload as $keyInner => $valueInner) {
                    $this->_setBody($valueInner, $keyInner, $mimeType);
                }

                return $this;
            }

            if ($key === null) {
                $this->payload[] = $payload;
            } else {
                $this->payload[$key] = $payload;
            }
        }

        // Don't call _serializePayload yet.
        // Wait until we actually send off the request to convert payload to string.
        // At that time, the `serialized_payload` is set accordingly.

        return $this;
    }

    /**
     * Set the defaults on a newly instantiated object
     * Doesn't copy variables prefixed with _
     *
     * @return static
     */
    private function _setDefaultsFromTemplate(): self
    {
        if ($this->_template !== null) {
            foreach ($this->_template as $k => $v) {
                if ($k[0] !== '_') {
                    $this->{$k} = $v;
                }
            }
        }

        return $this;
    }

    /**
     * Do we strictly enforce SSL verification?
     *
     * @param bool $strict
     *
     * @return static
     */
    private function _strictSSL($strict): self
    {
        $this->strict_ssl = $strict;

        return $this;
    }

    private function _updateHostFromUri()
    {
        if ($this->uri === null) {
            return;
        }

        static $URL_CACHE = null;

        if ($URL_CACHE === $this->uri) {
            return;
        }

        $host = $this->uri->getHost();

        if ($host === '') {
            return;
        }

        $port = $this->uri->getPort();
        if ($port !== null) {
            $host .= ':' . $port;
        }

        if (isset($this->headerNames['host'])) {
            $header = $this->headerNames['host'];
        } else {
            $this->headerNames['host'] = $header = 'Host';
        }
        // Ensure Host is the first header.
        // See: http://tools.ietf.org/html/rfc7230#section-5.4
        $this->headers = [$header => [$host]] + $this->headers;

        $URL_CACHE = $this->uri;
    }

    /**
     * @param array $headers
     */
    private function _setHeaders(array $headers)
    {
        foreach ($headers as $header => $value) {
            $value = $this->_validateAndTrimHeader($header, $value);
            $normalized = \strtolower($header);

            if (isset($this->headerNames[$normalized])) {
                $header = $this->headerNames[$normalized];
                $this->headers[$header] = \array_merge($this->headers[$header], $value);
            } else {
                $this->headerNames[$normalized] = $header;
                $this->headers[$header] = $value;
            }
        }
    }

    /**
     * Make sure the header complies with RFC 7230.
     *
     * Header names must be a non-empty string consisting of token characters.
     *
     * Header values must be strings consisting of visible characters with all optional
     * leading and trailing whitespace stripped. This method will always strip such
     * optional whitespace. Note that the method does not allow folding whitespace within
     * the values as this was deprecated for almost all instances by the RFC.
     *
     * header-field = field-name ":" OWS field-value OWS
     * field-name   = 1*( "!" / "#" / "$" / "%" / "&" / "'" / "*" / "+" / "-" / "." / "^"
     *              / "_" / "`" / "|" / "~" / %x30-39 / ( %x41-5A / %x61-7A ) )
     * OWS          = *( SP / HTAB )
     * field-value  = *( ( %x21-7E / %x80-FF ) [ 1*( SP / HTAB ) ( %x21-7E / %x80-FF ) ] )
     *
     * @see https://tools.ietf.org/html/rfc7230#section-3.2.4
     *
     * @param mixed $header
     * @param mixed $values
     *
     * @return string[]
     */
    private function _validateAndTrimHeader($header, $values): array
    {
        if (
            !\is_string($header)
            ||
            \preg_match("@^[!#$%&'*+.^_`|~0-9A-Za-z-]+$@", $header) !== 1
        ) {
            throw new \InvalidArgumentException('Header name must be an RFC 7230 compatible string.');
        }

        if (!\is_array($values)) {
            // This is simple, just one value.
            if (
                (!\is_numeric($values) && !\is_string($values))
                ||
                \preg_match("@^[ \t\x21-\x7E\x80-\xFF]*$@", (string) $values) !== 1
            ) {
                throw new \InvalidArgumentException('Header values must be RFC 7230 compatible strings.');
            }

            return [\trim((string) $values, " \t")];
        }

        if (empty($values)) {
            throw new \InvalidArgumentException('Header values must be a string or an array of strings, empty array given.');
        }

        // Assert Non empty array
        $returnValues = [];
        foreach ($values as $v) {
            if (
                (!\is_numeric($v) && !\is_string($v))
                ||
                \preg_match("@^[ \t\x21-\x7E\x80-\xFF]*$@", (string) $v) !== 1
            ) {
                throw new \InvalidArgumentException('Header values must be RFC 7230 compatible strings.');
            }

            $returnValues[] = \trim((string) $v, " \t");
        }

        return $returnValues;
    }
}
