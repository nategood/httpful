<?php

declare(strict_types=1);

namespace Httpful;

use Httpful\Curl\Curl;
use Httpful\Curl\MultiCurl;
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
    private $template;

    /**
     * @var array
     */
    private $helperData = [];

    /**
     * @var UriInterface|null
     */
    private $uri;

    /**
     * @var string
     */
    private $uri_cache;

    /**
     * @var string
     */
    private $ssl_key = '';

    /**
     * @var string
     */
    private $ssl_cert = '';

    /**
     * @var string
     */
    private $ssl_key_type = '';

    /**
     * @var string|null
     */
    private $ssl_passphrase;

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
     * @var Headers
     */
    private $headers;

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
    private $cache_control = '';

    /**
     * @var string
     */
    private $content_type = '';

    /**
     * @var string
     */
    private $content_charset = '';

    /**
     * @var string
     *             <p>e.g.: "gzip" or "deflate"</p>
     */
    private $content_encoding = '';

    /**
     * @var int|null
     *               <p>e.g.: 80 or 443</p>
     */
    private $port;

    /**
     * @var int
     */
    private $keep_alive = 300;

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
     * @var string|null
     */
    private $serialized_payload;

    /**
     * @var \CURLFile[]|string|string[]
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
    private $curl;

    /**
     * MultiCurl Object
     *
     * @var MultiCurl|null
     */
    private $curlMulti;

    /**
     * @var bool
     */
    private $debug = false;

    /**
     * @var string
     */
    private $protocol_version = Http::HTTP_1_1;

    /**
     * @var bool
     */
    private $retry_by_possible_encoding_error = false;

    /**
     * @var callable|string|null
     */
    private $file_path_for_download;

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
        $this->initialize();

        $this->template = $template;
        $this->headers = new Headers();

        // fallback
        if (!isset($this->template)) {
            $this->template = new static(Http::GET, null, $this);
            $this->template = $this->template->disableStrictSSL();
        }

        $this->_setDefaultsFromTemplate()
            ->_setMethod($method)
            ->_withContentType($mime, Mime::PLAIN)
            ->_withExpectedType($mime, Mime::PLAIN);
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

        // init
        $this->initialize();
        \assert($this->curl instanceof Curl);

        if ($this->params === []) {
            $this->_uriPrep();
        }

        if ($this->payload === []) {
            $this->serialized_payload = null;
        } else {
            $this->serialized_payload = $this->_serializePayload($this->payload);

            if (
                $this->serialized_payload
                &&
                $this->content_charset
                &&
                !$this->isUpload()
            ) {
                $this->serialized_payload = UTF8::encode(
                    $this->content_charset,
                    (string) $this->serialized_payload
                );
            }
        }

        if ($this->send_callbacks !== []) {
            foreach ($this->send_callbacks as $callback) {
                /** @noinspection VariableFunctionsUsageInspection */
                \call_user_func($callback, $this);
            }
        }

        \assert($this->curl instanceof Curl);

        $this->curl->setUrl((string) $this->uri);

        $ch = $this->curl->getCurl();
        if ($ch === false) {
            throw new NetworkErrorException('Unable to connect to "' . $this->uri . '". => "curl_init" === false');
        }

        $this->curl->setOpt(\CURLOPT_IPRESOLVE, \CURL_IPRESOLVE_WHATEVER);

        if ($this->method === Http::POST) {
            // Use CURLOPT_POST to have browser-like POST-to-GET redirects for 301, 302 and 303
            $this->curl->setOpt(\CURLOPT_POST, true);
        } else {
            $this->curl->setOpt(\CURLOPT_CUSTOMREQUEST, $this->method);
        }

        if ($this->method === Http::HEAD) {
            $this->curl->setOpt(\CURLOPT_NOBODY, true);
        }

        if ($this->hasBasicAuth()) {
            $this->curl->setOpt(\CURLOPT_USERPWD, $this->username . ':' . $this->password);
        }

        if ($this->hasClientSideCert()) {
            if (!\file_exists($this->ssl_key)) {
                throw new RequestException($this, 'Could not read Client Key');
            }

            if (!\file_exists($this->ssl_cert)) {
                throw new RequestException($this, 'Could not read Client Certificate');
            }

            $this->curl->setOpt(\CURLOPT_SSLCERTTYPE, $this->ssl_key_type);
            $this->curl->setOpt(\CURLOPT_SSLKEYTYPE, $this->ssl_key_type);
            $this->curl->setOpt(\CURLOPT_SSLCERT, $this->ssl_cert);
            $this->curl->setOpt(\CURLOPT_SSLKEY, $this->ssl_key);
            if ($this->ssl_passphrase !== null) {
                $this->curl->setOpt(\CURLOPT_SSLKEYPASSWD, $this->ssl_passphrase);
            }
        }

        $this->curl->setOpt(\CURLOPT_TCP_NODELAY, true);

        if ($this->hasTimeout()) {
            $this->curl->setOpt(\CURLOPT_TIMEOUT_MS, \round($this->timeout * 1000));
        }

        if ($this->hasConnectionTimeout()) {
            $this->curl->setOpt(\CURLOPT_CONNECTTIMEOUT_MS, \round($this->connection_timeout * 1000));

            if (\DIRECTORY_SEPARATOR !== '\\' && $this->connection_timeout < 1) {
                $this->curl->setOpt(\CURLOPT_NOSIGNAL, true);
            }
        }

        if ($this->follow_redirects === true) {
            $this->curl->setOpt(\CURLOPT_FOLLOWLOCATION, true);
            $this->curl->setOpt(\CURLOPT_MAXREDIRS, $this->max_redirects);
        }

        $this->curl->setOpt(\CURLOPT_SSL_VERIFYPEER, $this->strict_ssl);
        // zero is safe for all curl versions
        $verifyValue = $this->strict_ssl + 0;
        // support for value 1 removed in cURL 7.28.1 value 2 valid in all versions
        if ($verifyValue > 0) {
            ++$verifyValue;
        }
        $this->curl->setOpt(\CURLOPT_SSL_VERIFYHOST, $verifyValue);

        $this->curl->setOpt(\CURLOPT_RETURNTRANSFER, true);

        $this->curl->setOpt(\CURLOPT_ENCODING, $this->content_encoding);

        if ($this->port !== null) {
            $this->curl->setOpt(\CURLOPT_PORT, $this->port);
        }

        $this->curl->setOpt(\CURLOPT_PROTOCOLS, \CURLPROTO_HTTP | \CURLPROTO_HTTPS);

        $this->curl->setOpt(\CURLOPT_REDIR_PROTOCOLS, \CURLPROTO_HTTP | \CURLPROTO_HTTPS);

        // set Content-Length to the size of the payload if present
        if ($this->serialized_payload) {
            $this->curl->setOpt(\CURLOPT_POSTFIELDS,  $this->serialized_payload);

            if (!$this->isUpload()) {
                $this->headers->forceSet('Content-Length', $this->_determineLength($this->serialized_payload));
            }
        }

        // init
        $headers = [];

        // Solve a bug on squid proxy, NONE/411 when miss content length.
        if (
            !$this->headers->offsetExists('Content-Length')
            &&
            !$this->isUpload()
        ) {
            $this->headers->forceSet('Content-Length', 0);
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

        if ($this->keep_alive) {
            $headers[] = 'Connection: Keep-Alive';
            $headers[] = 'Keep-Alive: ' . $this->keep_alive;
        } else {
            $headers[] = 'Connection: close';
        }

        if (!$this->headers->offsetExists('User-Agent')) {
            $headers[] = $this->buildUserAgent();
        }

        if ($this->content_charset) {
            $contentType = $this->content_type . '; charset=' . $this->content_charset;
        } else {
            $contentType = $this->content_type;
        }
        $headers[] = 'Content-Type: ' . $contentType;

        if ($this->cache_control) {
            $headers[] = 'Cache-Control: ' . $this->cache_control;
        }

        // allow custom Accept header if set
        if (!$this->headers->offsetExists('Accept')) {
            // http://pretty-rfc.herokuapp.com/RFC2616#header.accept
            $accept = 'Accept: */*; q=0.5, text/plain; q=0.8, text/html;level=3;';

            if (!empty($this->expected_type)) {
                $accept .= 'q=0.9, ' . $this->expected_type;
            }

            $headers[] = $accept;
        }

        $url = \parse_url((string) $this->uri);

        if (\is_array($url) === false) {
            throw new ClientErrorException('Unable to connect to "' . $this->uri . '". => "parse_url" === false');
        }

        $path = ($url['path'] ?? '/') . (isset($url['query']) ? '?' . $url['query'] : '');
        $this->raw_headers = "{$this->method} ${path} HTTP/{$this->protocol_version}\r\n";
        $this->raw_headers .= \implode("\r\n", $headers);
        $this->raw_headers .= "\r\n";

        // DEBUG
        //var_dump($this->_headers->toArray(), $this->_raw_headers);

        /** @noinspection AlterInForeachInspection */
        foreach ($headers as &$header) {
            $pos_tmp = \strpos($header, ': ');
            if (
                $pos_tmp !== false
                &&
                \strlen($header) - 2 === $pos_tmp
            ) {
                // curl requires a special syntax to send empty headers
                $header = \substr_replace($header, ';', -2);
            }
        }
        $this->curl->setOpt(\CURLOPT_HTTPHEADER, $headers);

        if ($this->debug) {
            $this->curl->setOpt(\CURLOPT_VERBOSE, true);
        }

        // If there are some additional curl opts that the user wants to set, we can tack them in here.
        foreach ($this->additional_curl_opts as $curlOpt => $curlVal) {
            $this->curl->setOpt($curlOpt, $curlVal);
        }

        switch ($this->protocol_version) {
            case Http::HTTP_1_0:
                $this->curl->setOpt(\CURLOPT_HTTP_VERSION, \CURL_HTTP_VERSION_1_0);

                break;
            case Http::HTTP_1_1:
                $this->curl->setOpt(\CURLOPT_HTTP_VERSION, \CURL_HTTP_VERSION_1_1);

                break;
            case Http::HTTP_2_0:
                $this->curl->setOpt(\CURLOPT_HTTP_VERSION, \CURL_HTTP_VERSION_2_0);

                break;
            default:
                $this->curl->setOpt(\CURLOPT_HTTP_VERSION, \CURL_HTTP_VERSION_NONE);

                break;
        }

        if ($this->file_path_for_download) {
            $this->curl->download($this->file_path_for_download);
            $this->curl->setOpt(\CURLOPT_CUSTOMREQUEST, 'GET');
            $this->curl->setOpt(\CURLOPT_HTTPGET, true);
            $this->disableAutoParsing();
        }

        return $this;
    }

    /**
     * @return Curl|null
     */
    public function _curl()
    {
        return $this->curl;
    }

    /**
     * @return MultiCurl|null
     */
    public function _curlMulti()
    {
        return $this->curlMulti;
    }

    /**
     * Takes care of building the query string to be used in the request URI.
     *
     * Any existing query string parameters, either passed as part of the URI
     * via uri() method, or passed via get() and friends will be preserved,
     * with additional parameters (added via params() or param()) appended.
     *
     * @internal
     *
     * @return void
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
            $this->_withUri(
                $this->uri->withQuery(
                    \substr(
                        (string) $this->uri,
                        0,
                        \strpos((string) $this->uri, '?')
                    )
                )
            );
        }

        if (\count($params)) {
            $this->_withUri($this->uri->withQuery($queryString));
        }
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

        if ($curl && isset($curl['version'])) {
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
     * @param string      $key          file path to client key
     * @param string      $cert         file path to client cert
     * @param string|null $passphrase   for client key
     * @param string      $ssl_key_type default PEM
     *
     * @return static
     */
    public function clientSideCertAuth($cert, $key, $passphrase = null, $ssl_key_type = 'PEM'): self
    {
        $this->ssl_cert = $cert;
        $this->ssl_key = $key;
        $this->ssl_key_type = $ssl_key_type;
        $this->ssl_passphrase = $passphrase;

        return $this;
    }

    /**
     * @see Request::initialize()
     *
     * @return void
     */
    public function close()
    {
        if ($this->curl && $this->hasBeenInitialized()) {
            $this->curl->close();
        }

        if ($this->curlMulti && $this->hasBeenInitializedMulti()) {
            $this->curlMulti->close();
        }
    }

    /**
     * HTTP Method Get
     *
     * @param string|UriInterface $uri
     * @param string              $file_path
     *
     * @return static
     */
    public static function download($uri, $file_path): self
    {
        if ($uri instanceof UriInterface) {
            $uri = (string) $uri;
        }

        return (new self(Http::GET))
            ->withUriFromString($uri)
            ->withDownload($file_path)
            ->withCacheControl('no-cache')
            ->withContentEncoding(Encoding::NONE);
    }

    /**
     * HTTP Method Delete
     *
     * @param string|UriInterface $uri
     * @param array|null          $params
     * @param string|null         $mime
     *
     * @return static
     */
    public static function delete($uri, array $params = null, string $mime = null): self
    {
        if ($uri instanceof UriInterface) {
            $uri = (string) $uri;
        }

        $paramsString = '';
        if ($params !== null) {
            $paramsString = \http_build_query(
                $params,
                '',
                '&',
                \PHP_QUERY_RFC3986
            );
            if ($paramsString) {
                $paramsString = (\strpos($uri, '?') !== false ? '&' : '?') . $paramsString;
            }
        }

        return (new self(Http::DELETE))
            ->withUriFromString($uri . $paramsString)
            ->withMimeType($mime);
    }

    /**
     * @return static
     *
     * @see Request::enableAutoParsing()
     */
    public function disableAutoParsing(): self
    {
        return $this->_autoParse(false);
    }

    /**
     * @return static
     *
     * @see Request::enableKeepAlive()
     */
    public function disableKeepAlive(): self
    {
        $this->keep_alive = 0;

        return $this;
    }

    /**
     * @return static
     */
    public function disableRetryByPossibleEncodingError(): self
    {
        $this->retry_by_possible_encoding_error = false;

        return $this;
    }

    /**
     * @return static
     *
     * @see Request::enableStrictSSL()
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
     * @see Request::disableAutoParsing()
     */
    public function enableAutoParsing(): self
    {
        return $this->_autoParse(true);
    }

    /**
     * @param int $seconds
     *
     * @return static
     *
     * @see Request::disableKeepAlive()
     */
    public function enableKeepAlive(int $seconds = 300): self
    {
        if ($seconds <= 0) {
            throw new \InvalidArgumentException(
                'Invalid keep-alive input: ' . \var_export($seconds, true)
            );
        }

        $this->keep_alive = $seconds;

        return $this;
    }

    /**
     * @return static
     */
    public function enableRetryByPossibleEncodingError(): self
    {
        $this->retry_by_possible_encoding_error = true;

        return $this;
    }

    /**
     * @return static
     *
     * @see Request::disableStrictSSL()
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
        return $this->withExpectedType(Mime::CSV);
    }

    /**
     * @return static
     */
    public function expectsForm(): self
    {
        return $this->withExpectedType(Mime::FORM);
    }

    /**
     * @return static
     */
    public function expectsHtml(): self
    {
        return $this->withExpectedType(Mime::HTML);
    }

    /**
     * @return static
     */
    public function expectsJavascript(): self
    {
        return $this->withExpectedType(Mime::JS);
    }

    /**
     * @return static
     */
    public function expectsJs(): self
    {
        return $this->withExpectedType(Mime::JS);
    }

    /**
     * @return static
     */
    public function expectsJson(): self
    {
        return $this->withExpectedType(Mime::JSON);
    }

    /**
     * @return static
     */
    public function expectsPlain(): self
    {
        return $this->withExpectedType(Mime::PLAIN);
    }

    /**
     * @return static
     */
    public function expectsText(): self
    {
        return $this->withExpectedType(Mime::PLAIN);
    }

    /**
     * @return static
     */
    public function expectsUpload(): self
    {
        return $this->withExpectedType(Mime::UPLOAD);
    }

    /**
     * @return static
     */
    public function expectsXhtml(): self
    {
        return $this->withExpectedType(Mime::XHTML);
    }

    /**
     * @return static
     */
    public function expectsXml(): self
    {
        return $this->withExpectedType(Mime::XML);
    }

    /**
     * @return static
     */
    public function expectsYaml(): self
    {
        return $this->withExpectedType(Mime::YAML);
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
        $new = clone $this;

        if ($follow === true) {
            $new->max_redirects = static::MAX_REDIRECTS_DEFAULT;
        } elseif ($follow === false) {
            $new->max_redirects = 0;
        } else {
            $new->max_redirects = \max(0, $follow);
        }

        $new->follow_redirects = $follow;

        return $new;
    }

    /**
     * HTTP Method Get
     *
     * @param string|UriInterface $uri
     * @param array|null          $params
     * @param string              $mime
     *
     * @return static
     */
    public static function get($uri, array $params = null, string $mime = null): self
    {
        if ($uri instanceof UriInterface) {
            $uri = (string) $uri;
        }

        $paramsString = '';
        if ($params !== null) {
            $paramsString = \http_build_query(
                $params,
                '',
                '&',
                \PHP_QUERY_RFC3986
            );
            if ($paramsString) {
                $paramsString = (\strpos($uri, '?') !== false ? '&' : '?') . $paramsString;
            }
        }

        return (new self(Http::GET))
            ->withUriFromString($uri . $paramsString)
            ->withMimeType($mime);
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
        if ($this->headers->offsetExists($name)) {
            $value = $this->headers->offsetGet($name);

            if (!\is_array($value)) {
                return [\trim($value, " \t")];
            }

            foreach ($value as $keyInner => $valueInner) {
                $value[$keyInner] = \trim($valueInner, " \t");
            }

            return $value;
        }

        return [];
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
        return $this->headers->toArray();
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
        return $this->protocol_version;
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
     * @return Uri|UriInterface|null
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
        return $this->headers->offsetExists($name);
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

        if (!\is_array($value)) {
            $value = [$value];
        }

        if ($new->headers->offsetExists($name)) {
            $new->headers->forceSet($name, \array_merge_recursive($new->headers->offsetGet($name), $value));
        } else {
            $new->headers->forceSet($name, $value);
        }

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
     */
    public function withBody(StreamInterface $body)
    {
        $stream = Http::stream($body);

        $new = clone $this;

        return $new->_setBody($stream, null);
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
        $new = clone $this;

        if (!\is_array($value)) {
            $value = [$value];
        }

        $new->headers->forceSet($name, $value);

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
     * @param string $method
     *                       <p>\Httpful\Http::GET, \Httpful\Http::POST, ...</p>
     *
     * @throws \InvalidArgumentException for invalid HTTP methods
     *
     * @return static
     */
    public function withMethod($method)
    {
        $new = clone $this;

        $new->_setMethod($method);

        return $new;
    }

    /**
     * Return an instance with the specified HTTP protocol version.
     *
     * The version string MUST contain only the HTTP version number (e.g.,
     * "2, 1.1", "1.0").
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new protocol version.
     *
     * @param string $version
     *                        <p>Http::HTTP_*</p>
     *
     * @return static
     */
    public function withProtocolVersion($version)
    {
        $new = clone $this;

        $new->protocol_version = $version;

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
            $new->_withUri($new->uri->withPath($requestTarget));
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
        $new = clone $this;

        return $new->_withUri($uri, $preserveHost);
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
        $new = clone $this;

        $new->headers->forceUnset($name);

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
        return \is_string($this->payload) ? [$this->payload] : $this->payload;
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
     * @return bool has the internal curl (non multi) request been initialized?
     */
    public function hasBeenInitialized(): bool
    {
        if (!$this->curl) {
            return false;
        }

        return \is_resource($this->curl->getCurl());
    }

    /**
     * @return bool has the internal curl (multi) request been initialized?
     */
    public function hasBeenInitializedMulti(): bool
    {
        if (!$this->curlMulti) {
            return false;
        }

        return \is_resource($this->curlMulti->getMultiCurl());
    }

    /**
     * @return bool is this request setup for client side cert?
     */
    public function hasClientSideCert(): bool
    {
        return $this->ssl_cert && $this->ssl_key;
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
     * @param string|UriInterface $uri
     *
     * @return static
     */
    public static function head($uri): self
    {
        if ($uri instanceof UriInterface) {
            $uri = (string) $uri;
        }

        return (new self(Http::HEAD))
            ->withUriFromString($uri)
            ->withMimeType(Mime::PLAIN);
    }

    /**
     * @see Request::close()
     *
     * @return void
     */
    public function initializeMulti()
    {
        if (!$this->curlMulti || $this->hasBeenInitializedMulti()) {
            $this->curlMulti = new MultiCurl();
        }
    }

    /**
     * @see Request::close()
     *
     * @return void
     */
    public function initialize()
    {
        if (!$this->curl || !$this->hasBeenInitialized()) {
            $this->curl = new Curl();
        }
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
    public function isJson(): bool
    {
        return $this->content_type === Mime::JSON;
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
     * @return static
     *
     * @see Request::serializePayloadMode()
     */
    public function neverSerializePayload(): self
    {
        return $this->serializePayloadMode(static::SERIALIZE_PAYLOAD_NEVER);
    }

    /**
     * HTTP Method Options
     *
     * @param string|UriInterface $uri
     *
     * @return static
     */
    public static function options($uri): self
    {
        if ($uri instanceof UriInterface) {
            $uri = (string) $uri;
        }

        return (new self(Http::OPTIONS))->withUriFromString($uri);
    }

    /**
     * HTTP Method Patch
     *
     * @param string|UriInterface $uri
     * @param mixed               $payload data to send in body of request
     * @param string              $mime    MIME to use for Content-Type
     *
     * @return static
     */
    public static function patch($uri, $payload = null, string $mime = null): self
    {
        if ($uri instanceof UriInterface) {
            $uri = (string) $uri;
        }

        return (new self(Http::PATCH))
            ->withUriFromString($uri)
            ->_setBody($payload, null, $mime);
    }

    /**
     * HTTP Method Post
     *
     * @param string|UriInterface $uri
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
            ->withUriFromString($uri)
            ->_setBody($payload, null, $mime);
    }

    /**
     * HTTP Method Put
     *
     * @param string|UriInterface $uri
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
            ->withUriFromString($uri)
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
        $new = clone $this;

        $new->payload_serializers[Mime::getFullMime($mime)] = $callback;

        return $new;
    }

    /**
     * @return void
     */
    public function reset()
    {
        $this->headers = new Headers();

        $this->close();
        $this->initialize();
    }

    /**
     * Actually send off the request, and parse the response.
     *
     * @param callable|null $onSuccessCallback
     * @param callable|null $onCompleteCallback
     * @param callable|null $onBeforeSendCallback
     * @param callable|null $onErrorCallback
     *
     * @throws NetworkErrorException when unable to parse or communicate w server
     *
     * @return MultiCurl
     */
    public function initMulti(
        $onSuccessCallback = null,
        $onCompleteCallback = null,
        $onBeforeSendCallback = null,
        $onErrorCallback = null
    ) {
        $this->initializeMulti();
        \assert($this->curlMulti instanceof MultiCurl);

        if ($onSuccessCallback !== null) {
            $this->curlMulti->success(
                static function (Curl $instance) use ($onSuccessCallback) {
                    if ($instance->request instanceof self) {
                        $response = $instance->request->_buildResponse($instance->rawResponse, $instance);
                    } else {
                        $response = $instance->rawResponse;
                    }

                    $onSuccessCallback(
                        $response,
                        $instance->request,
                        $instance
                    );
                }
            );
        }

        if ($onCompleteCallback !== null) {
            $this->curlMulti->complete(
                static function (Curl $instance) use ($onCompleteCallback) {
                    if ($instance->request instanceof self) {
                        $response = $instance->request->_buildResponse($instance->rawResponse, $instance);
                    } else {
                        $response = $instance->rawResponse;
                    }

                    $onCompleteCallback(
                        $response,
                        $instance->request,
                        $instance
                    );
                }
            );
        }

        if ($onBeforeSendCallback !== null) {
            $this->curlMulti->beforeSend(
                static function (Curl $instance) use ($onBeforeSendCallback) {
                    if ($instance->request instanceof self) {
                        $response = $instance->request->_buildResponse($instance->rawResponse, $instance);
                    } else {
                        $response = $instance->rawResponse;
                    }

                    $onBeforeSendCallback(
                        $response,
                        $instance->request,
                        $instance
                    );
                }
            );
        }

        if ($onErrorCallback !== null) {
            $this->curlMulti->error(
                static function (Curl $instance) use ($onErrorCallback) {
                    if ($instance->request instanceof self) {
                        $response = $instance->request->_buildResponse($instance->rawResponse, $instance);
                    } else {
                        $response = $instance->rawResponse;
                    }

                    $onErrorCallback(
                        $response,
                        $instance->request,
                        $instance
                    );
                }
            );
        }

        return $this->curlMulti;
    }

    /**
     * Actually send off the request, and parse the response.
     *
     * @throws NetworkErrorException when unable to parse or communicate w server
     *
     * @return Response
     */
    public function send(): Response
    {
        $this->_curlPrep();
        \assert($this->curl instanceof Curl);

        $result = $this->curl->exec();

        if (
            $result === false
            &&
            $this->retry_by_possible_encoding_error
        ) {
            // Possibly a gzip issue makes curl unhappy.
            if (
                $this->curl->errorCode === \CURLE_WRITE_ERROR
                ||
                $this->curl->errorCode === \CURLE_BAD_CONTENT_ENCODING
            ) {

                // Docs say 'identity,' but 'none' seems to work (sometimes?).
                $this->curl->setOpt(\CURLOPT_ENCODING, 'none');

                $result = $this->curl->exec();

                if ($result === false) {
                    /** @noinspection NotOptimalIfConditionsInspection */
                    if (
                        /* @phpstan-ignore-next-line | FP? */
                        $this->curl->errorCode === \CURLE_WRITE_ERROR
                        ||
                        $this->curl->errorCode === \CURLE_BAD_CONTENT_ENCODING
                    ) {
                        $this->curl->setOpt(\CURLOPT_ENCODING, 'identity');

                        $result = $this->curl->exec();
                    }
                }
            }
        }

        if (!$this->keep_alive) {
            $this->close();
        }

        return $this->_buildResponse($result);
    }

    /**
     * @return static
     */
    public function sendsCsv(): self
    {
        return $this->withContentType(Mime::CSV);
    }

    /**
     * @return static
     */
    public function sendsForm(): self
    {
        return $this->withContentType(Mime::FORM);
    }

    /**
     * @return static
     */
    public function sendsHtml(): self
    {
        return $this->withContentType(Mime::HTML);
    }

    /**
     * @return static
     */
    public function sendsJavascript(): self
    {
        return $this->withContentType(Mime::JS);
    }

    /**
     * @return static
     */
    public function sendsJs(): self
    {
        return $this->withContentType(Mime::JS);
    }

    /**
     * @return static
     */
    public function sendsJson(): self
    {
        return $this->withContentType(Mime::JSON);
    }

    /**
     * @return static
     */
    public function sendsPlain(): self
    {
        return $this->withContentType(Mime::PLAIN);
    }

    /**
     * @return static
     */
    public function sendsText(): self
    {
        return $this->withContentType(Mime::PLAIN);
    }

    /**
     * @return static
     */
    public function sendsUpload(): self
    {
        return $this->withContentType(Mime::UPLOAD);
    }

    /**
     * @return static
     */
    public function sendsXhtml(): self
    {
        return $this->withContentType(Mime::XHTML);
    }

    /**
     * @return static
     */
    public function sendsXml(): self
    {
        return $this->withContentType(Mime::XML);
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
     * @param int $mode Request::SERIALIZE_PAYLOAD_*
     *
     * @return static
     */
    public function serializePayloadMode(int $mode): self
    {
        $this->serialize_payload_method = $mode;

        return $this;
    }

    /**
     * This method is the default behavior
     *
     * @return static
     *
     * @see Request::serializePayloadMode()
     */
    public function smartSerializePayload(): self
    {
        return $this->serializePayloadMode(static::SERIALIZE_PAYLOAD_SMART);
    }

    /**
     * Specify a HTTP timeout
     *
     * @param float|int $timeout seconds to timeout the HTTP call
     *
     * @return static
     */
    public function withTimeout($timeout): self
    {
        if (!\preg_match('/^\d+(\.\d+)?/', (string) $timeout)) {
            throw new \InvalidArgumentException(
                'Invalid timeout provided: ' . \var_export($timeout, true)
            );
        }

        $new = clone $this;

        $new->timeout = $timeout;

        return $new;
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
     * @see Request::withProxy
     */
    public function useSocks4Proxy(
        $proxy_host,
        $proxy_port = 80,
        $auth_type = null,
        $auth_username = null,
        $auth_password = null
    ): self {
        return $this->withProxy(
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
     * @see Request::withProxy
     */
    public function useSocks5Proxy(
        $proxy_host,
        $proxy_port = 80,
        $auth_type = null,
        $auth_username = null,
        $auth_password = null
    ): self {
        return $this->withProxy(
            $proxy_host,
            $proxy_port,
            $auth_type,
            $auth_username,
            $auth_password,
            Proxy::SOCKS5
        );
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
     * @param array<string,string> $files
     *
     * @return static
     */
    public function withAttachment($files): self
    {
        $new = clone $this;

        $fInfo = \finfo_open(\FILEINFO_MIME_TYPE);
        if ($fInfo === false) {
            /** @noinspection ForgottenDebugOutputInspection */
            \error_log('finfo_open() did not work', \E_USER_WARNING);

            return $new;
        }

        foreach ($files as $key => $file) {
            $mimeType = \finfo_file($fInfo, $file);
            if ($mimeType !== false) {
                if (\is_string($new->payload)) {
                    $new->payload = []; // reset
                }
                $new->payload[$key] = \curl_file_create($file, $mimeType, \basename($file));
            }
        }

        \finfo_close($fInfo);

        return $new->_withContentType(Mime::UPLOAD);
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
    public function withBasicAuth($username, $password): self
    {
        $new = clone $this;
        $new->username = $username;
        $new->password = $password;

        return $new;
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
     * Specify a HTTP connection timeout
     *
     * @param float|int $connection_timeout seconds to timeout the HTTP connection
     *
     * @throws \InvalidArgumentException
     *
     * @return static
     */
    public function withConnectionTimeoutInSeconds($connection_timeout): self
    {
        if (!\preg_match('/^\d+(\.\d+)?/', (string) $connection_timeout)) {
            throw new \InvalidArgumentException(
                'Invalid connection timeout provided: ' . \var_export($connection_timeout, true)
            );
        }

        $new = clone $this;

        $new->connection_timeout = $connection_timeout;

        return $new;
    }

    /**
     * @param string $cache_control
     *                              <p>e.g. 'no-cache', 'public', ...</p>
     *
     * @return static
     */
    public function withCacheControl(string $cache_control): self
    {
        $new = clone $this;

        if (empty($cache_control)) {
            return $new;
        }

        $new->cache_control = $cache_control;

        return $new;
    }

    /**
     * @param string $charset
     *                        <p>e.g. "UTF-8"</p>
     *
     * @return static
     */
    public function withContentCharset(string $charset): self
    {
        $new = clone $this;

        if (empty($charset)) {
            return $new;
        }

        $new->content_charset = UTF8::normalize_encoding($charset);

        return $new;
    }

    /**
     * @param int $port
     *
     * @return static
     */
    public function withPort(int $port): self
    {
        $new = clone $this;

        $new->port = $port;
        if ($new->uri) {
            $new->uri = $new->uri->withPort($port);
            $new->_updateHostFromUri();
        }

        return $new;
    }

    /**
     * @param string $encoding
     *
     * @return static
     */
    public function withContentEncoding(string $encoding): self
    {
        $new = clone $this;

        $new->content_encoding = $encoding;

        return $new;
    }

    /**
     * @param string|null $mime     use a constant from Mime::*
     * @param string|null $fallback use a constant from Mime::*
     *
     * @return static
     */
    public function withContentType($mime, string $fallback = null): self
    {
        $new = clone $this;

        return $new->_withContentType($mime, $fallback);
    }

    /**
     * @return static
     */
    public function withContentTypeCsv(): self
    {
        $new = clone $this;
        $new->content_type = Mime::getFullMime(Mime::CSV);

        return $new;
    }

    /**
     * @return static
     */
    public function withContentTypeForm(): self
    {
        $new = clone $this;
        $new->content_type = Mime::getFullMime(Mime::FORM);

        return $new;
    }

    /**
     * @return static
     */
    public function withContentTypeHtml(): self
    {
        $new = clone $this;
        $new->content_type = Mime::getFullMime(Mime::HTML);

        return $new;
    }

    /**
     * @return static
     */
    public function withContentTypeJson(): self
    {
        $new = clone $this;
        $new->content_type = Mime::getFullMime(Mime::JSON);

        return $new;
    }

    /**
     * @return static
     */
    public function withContentTypePlain(): self
    {
        $new = clone $this;
        $new->content_type = Mime::getFullMime(Mime::PLAIN);

        return $new;
    }

    /**
     * @return static
     */
    public function withContentTypeXml(): self
    {
        $new = clone $this;
        $new->content_type = Mime::getFullMime(Mime::XML);

        return $new;
    }

    /**
     * @return static
     */
    public function withContentTypeYaml(): self
    {
        return $this->withContentType(Mime::YAML);
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
     * Semi-reluctantly added this as a way to add in curl opts
     * that are not otherwise accessible from the rest of the API.
     *
     * @param int   $curl_opt
     * @param mixed $curl_opt_val
     *
     * @return static
     */
    public function withCurlOption($curl_opt, $curl_opt_val): self
    {
        $new = clone $this;

        $new->additional_curl_opts[$curl_opt] = $curl_opt_val;

        return $new;
    }

    /**
     * User Digest Auth.
     *
     * @param string $username
     * @param string $password
     *
     * @return static
     */
    public function withDigestAuth($username, $password): self
    {
        $new = clone $this;

        $new = $new->withCurlOption(\CURLOPT_HTTPAUTH, \CURLAUTH_DIGEST);

        return $new->withBasicAuth($username, $password);
    }

    /**
     * Callback called to handle HTTP errors. When nothing is set, defaults
     * to logging via `error_log`.
     *
     * @param callable|LoggerInterface|null $error_handler
     *
     * @return static
     */
    public function withErrorHandler($error_handler): self
    {
        $new = clone $this;

        $new->error_handler = $error_handler;

        return $new;
    }

    /**
     * @param string|null $mime     use a constant from Mime::*
     * @param string|null $fallback use a constant from Mime::*
     *
     * @return static
     */
    public function withExpectedType($mime, string $fallback = null): self
    {
        $new = clone $this;

        return $new->_withExpectedType($mime, $fallback);
    }

    /**
     * @param string[]|string[][] $header
     *
     * @return static
     */
    public function withHeaders(array $header): self
    {
        $new = clone $this;

        foreach ($header as $name => $value) {
            $new = $new->withAddedHeader($name, $value);
        }

        return $new;
    }

    /**
     * Helper function to set the Content type and Expected as same in one swoop.
     *
     * @param string|null $mime
     *                          <p>\Httpful\Mime::JSON, \Httpful\Mime::XML, ...</p>
     *
     * @return static
     */
    public function withMimeType($mime): self
    {
        $new = clone $this;

        return $new->_withMimeType($mime);
    }

    /**
     * @param string $username
     * @param string $password
     *
     * @return static
     */
    public function withNtlmAuth($username, $password): self
    {
        $new = clone $this;

        $new->withCurlOption(\CURLOPT_HTTPAUTH, \CURLAUTH_NTLM);

        return $new->withBasicAuth($username, $password);
    }

    /**
     * Add additional parameter to be appended to the query string.
     *
     * @param int|string|null $key
     * @param int|string|null $value
     *
     * @return static
     */
    public function withParam($key, $value): self
    {
        $new = clone $this;

        if (
            isset($key, $value)
            &&
            $key !== ''
        ) {
            $new->params[$key] = $value;
        }

        return $new;
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
    public function withParams(array $params): self
    {
        $new = clone $this;

        $new->params = \array_merge($new->params, $params);

        return $new;
    }

    /**
     * Use a custom function to parse the response.
     *
     * @param callable $callback Takes the raw body of
     *                           the http response and returns a mixed
     *
     * @return static
     */
    public function withParseCallback(callable $callback): self
    {
        $new = clone $this;

        $new->parse_callback = $callback;

        return $new;
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
    public function withProxy(
        $proxy_host,
        $proxy_port = 80,
        $auth_type = null,
        $auth_username = null,
        $auth_password = null,
        $proxy_type = Proxy::HTTP
    ): self {
        $new = clone $this;

        $new = $new->withCurlOption(\CURLOPT_PROXY, "{$proxy_host}:{$proxy_port}");
        $new = $new->withCurlOption(\CURLOPT_PROXYTYPE, $proxy_type);

        if (\in_array($auth_type, [\CURLAUTH_BASIC, \CURLAUTH_NTLM], true)) {
            $new = $new->withCurlOption(\CURLOPT_PROXYAUTH, $auth_type);
            $new = $new->withCurlOption(\CURLOPT_PROXYUSERPWD, "{$auth_username}:{$auth_password}");
        }

        return $new;
    }

    /**
     * @param string|null $key
     * @param mixed|null  $fallback
     *
     * @return mixed
     */
    public function getHelperData($key = null, $fallback = null)
    {
        if ($key !== null) {
            return $this->helperData[$key] ?? $fallback;
        }

        return $this->helperData;
    }

    /**
     * @return void
     */
    public function clearHelperData()
    {
        $this->helperData = [];
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return static
     */
    public function addHelperData(string $key, $value): self
    {
        $this->helperData[$key] = $value;

        return $this;
    }

    /**
     * @param callable|null $send_callback
     *
     * @return static
     */
    public function withSendCallback($send_callback): self
    {
        $new = clone $this;

        if (!empty($send_callback)) {
            $new->send_callbacks[] = $send_callback;
        }

        return $new;
    }

    /**
     * @param callable $callback
     *
     * @return static
     */
    public function withSerializePayload(callable $callback): self
    {
        return $this->registerPayloadSerializer('*', $callback);
    }

    /**
     * @param string $file_path
     *
     * @return Request
     */
    public function withDownload($file_path): self
    {
        $new = clone $this;

        $new->file_path_for_download = $file_path;

        return $new;
    }

    /**
     * @param string $uri
     * @param bool   $useClone
     *
     * @return static
     */
    public function withUriFromString(string $uri, bool $useClone = true): self
    {
        if ($useClone) {
            $new = clone $this;

            return $new->withUri(new Uri($uri));
        }

        return $this->_withUri(new Uri($uri));
    }

    /**
     * Sets user agent.
     *
     * @param string $userAgent
     *
     * @return static
     */
    public function withUserAgent($userAgent): self
    {
        return $this->withHeader('User-Agent', $userAgent);
    }

    /**
     * Takes a curl result and generates a Response from it.
     *
     * @param false|mixed $result
     * @param Curl|null   $curl
     *
     * @throws NetworkErrorException
     *
     * @return Response
     *
     * @internal
     */
    public function _buildResponse($result, Curl $curl = null): Response
    {
        // fallback
        if ($curl === null) {
            $curl = $this->curl;
        }

        if ($curl === null) {
            throw new NetworkErrorException('Unable to build the response for "' . $this->uri . '". => "curl" === null');
        }

        if ($result === false) {
            $curlErrorNumber = $curl->getErrorCode();
            if ($curlErrorNumber) {
                $curlErrorString = (string) $curl->getErrorMessage();

                $this->_error($curlErrorString);

                $exception = new NetworkErrorException(
                    'Unable to connect to "' . $this->uri . '": ' . $curlErrorNumber . ' ' . $curlErrorString,
                    $curlErrorNumber,
                    null,
                    $curl,
                    $this
                );

                $exception->setCurlErrorNumber($curlErrorNumber)->setCurlErrorString($curlErrorString);

                throw $exception;
            }

            $this->_error('Unable to connect to "' . $this->uri . '".');

            throw new NetworkErrorException('Unable to connect to "' . $this->uri . '".');
        }

        $curl_info = $curl->getInfo();

        $headers = $curl->getRawResponseHeaders();
        $rawResponse = $curl->getRawResponse();

        if ($rawResponse === false) {
            $body = '';
        } elseif ($rawResponse === true && $this->file_path_for_download && \is_string($this->file_path_for_download)) {
            $body = \file_get_contents($this->file_path_for_download);
            if ($body === false) {
                throw new \ErrorException('file_get_contents return false for: ' . $this->file_path_for_download);
            }
        } else {
            $body = UTF8::remove_left(
                (string) $rawResponse,
                $headers
            );
        }

        // get the protocol + version
        $protocol_version_regex = "/HTTP\/(?<version>[\d\.]*+)/i";
        $protocol_version_matches = [];
        $protocol_version = null;
        \preg_match($protocol_version_regex, $headers, $protocol_version_matches);
        if (isset($protocol_version_matches['version'])) {
            $protocol_version = $protocol_version_matches['version'];
        }
        $curl_info['protocol_version'] = $protocol_version;

        // DEBUG
        //var_dump($body, $headers);

        return new Response(
            $body,
            $headers,
            $this,
            $curl_info
        );
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
        $new = clone $this;

        $new->auto_parse = $auto_parse;

        return $new;
    }

    /**
     * @param string|null $str payload
     *
     * @return int length of payload in bytes
     */
    private function _determineLength($str): int
    {
        if ($str === null) {
            return 0;
        }

        return \strlen($str);
    }

    /**
     * @param string $error
     *
     * @return void
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
                /** @noinspection VariableFunctionsUsageInspection */
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
     * @param array|string $payload
     *
     * @return mixed
     *
     * @see Request::registerPayloadSerializer()
     */
    private function _serializePayload($payload)
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
            \is_array($payload)
            &&
            \count($payload) === 1
            &&
            \array_keys($payload)[0] === 0
            &&
            \is_scalar($payload_first = \array_values($payload)[0])
        ) {
            return $payload_first;
        }

        // Use a custom serializer if one is registered for this mime type.
        $issetContentType = isset($this->payload_serializers[$this->content_type]);
        if (
            $issetContentType
            ||
            isset($this->payload_serializers['*'])
        ) {
            if ($issetContentType) {
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
        $this->_withMimeType($mimeType);

        if (!empty($payload)) {
            if (\is_array($payload)) {
                foreach ($payload as $keyInner => $valueInner) {
                    $this->_setBody($valueInner, $keyInner, $mimeType);
                }

                return $this;
            }

            if ($payload instanceof StreamInterface) {
                $this->payload = (string) $payload;
            } elseif ($key === null) {
                if (\is_string($this->payload)) {
                    $tmpPayload = $this->payload;
                    $this->payload = [];
                    $this->payload[] = $tmpPayload;
                }

                $this->payload[] = $payload;
            } else {
                if (\is_string($this->payload)) {
                    $tmpPayload = $this->payload;
                    $this->payload = [];
                    $this->payload[] = $tmpPayload;
                }

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
        if ($this->template !== null) {
            if (\function_exists('gzdecode')) {
                $this->template->content_encoding = 'gzip';
            } elseif (\function_exists('gzinflate')) {
                $this->template->content_encoding = 'deflate';
            }

            foreach ($this->template as $k => $v) {
                if ($k[0] !== '_') {
                    $this->{$k} = $v;
                }
            }
        }

        return $this;
    }

    /**
     * Set the method.  Shouldn't be called often as the preferred syntax
     * for instantiation is the method specific factory methods.
     *
     * @param string|null $method
     *
     * @return static
     */
    private function _setMethod($method): self
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
     * Do we strictly enforce SSL verification?
     *
     * @param bool $strict
     *
     * @return static
     */
    private function _strictSSL($strict): self
    {
        $new = clone $this;

        $new->strict_ssl = $strict;

        return $new;
    }

    /**
     * @return void
     */
    private function _updateHostFromUri()
    {
        if ($this->uri === null) {
            return;
        }

        if ($this->uri_cache === \serialize($this->uri)) {
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

        // Ensure Host is the first header.
        // See: http://tools.ietf.org/html/rfc7230#section-5.4
        $this->headers = new Headers(['Host' => [$host]] + $this->withoutHeader('Host')->getHeaders());

        $this->uri_cache = \serialize($this->uri);
    }

    /**
     * @param string|null $mime     use a constant from Mime::*
     * @param string|null $fallback use a constant from Mime::*
     *
     * @return static
     */
    private function _withContentType($mime, string $fallback = null): self
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
     * @param string|null $mime     use a constant from Mime::*
     * @param string|null $fallback use a constant from Mime::*
     *
     * @return static
     */
    private function _withExpectedType($mime, string $fallback = null): self
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
     * Helper function to set the Content type and Expected as same in one swoop.
     *
     * @param string|null $mime mime type to use for content type and expected return type
     *
     * @return static
     */
    private function _withMimeType($mime): self
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
     * @param UriInterface $uri
     * @param bool         $preserveHost
     *
     * @return static
     */
    private function _withUri(UriInterface $uri, $preserveHost = false): self
    {
        if ($this->uri === $uri) {
            return $this;
        }

        $this->uri = $uri;

        if (!$preserveHost) {
            $this->_updateHostFromUri();
        }

        return $this;
    }
}
