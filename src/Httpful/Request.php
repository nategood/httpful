<?php

declare(strict_types=1);

namespace Httpful;

use Curl\Curl;
use Httpful\Exception\ConnectionErrorException;
use Psr\Log\LoggerInterface;
use voku\helper\UTF8;

final class Request implements \IteratorAggregate
{
    const MAX_REDIRECTS_DEFAULT = 25;

    const SERIALIZE_PAYLOAD_ALWAYS = 1;

    const SERIALIZE_PAYLOAD_NEVER = 0;

    const SERIALIZE_PAYLOAD_SMART = 2;

    /**
     * Template Request object
     *
     * @var Request|null
     */
    private $_template;

    /**
     * @var string
     */
    private $uri = '';

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
    private $method = Helper::GET;

    /**
     * @var int[]|string[]
     */
    private $headers = [];

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
    private $error_callback;

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
     * We made the constructor protected to force the factory style.  This was
     * done to keep the syntax cleaner and better the support the idea of
     * "default templates".  Very basic and flexible as it is only intended
     * for internal use.
     *
     * @param array $attrs hash of initial attribute values
     */
    public function __construct($attrs = null)
    {
        if (!\is_array($attrs)) {
            return;
        }

        foreach ($attrs as $attr => $value) {
            $this->{$attr} = $value;
        }
    }

    /**
     * Magic method allows for neatly setting other headers in a
     * similar syntax as the other setters.  This method also allows
     * for the sends* syntax.
     *
     * @param string $method "missing" method name called
     *                       the method name called should be the name of the header that you
     *                       are trying to set in camel case without dashes e.g. to set a
     *                       header for Content-Type you would use contentType() or more commonly
     *                       to add a custom header like X-My-Header, you would use xMyHeader().
     *                       To promote readability, you can optionally prefix these methods with
     *                       "with"  (e.g. withXMyHeader("blah") instead of xMyHeader("blah")).
     * @param array  $args   in this case, there should only ever be 1 argument provided
     *                       and that argument should be a string value of the header we're setting
     *
     * @return self|null
     */
    public function __call($method, $args)
    {
        // This method supports the sends* methods like sendsJson, sendsForm ...
        if (\strpos($method, 'sends') === 0) {
            $mime = \substr($method, 5);
            if (Mime::supportsMimeType($mime)) {
                $this->contentType(Mime::getFullMime($mime));

                return $this;
            }
        }
        if (\strpos($method, 'expects') === 0) {
            $mime = \substr($method, 7);
            if (Mime::supportsMimeType($mime)) {
                $this->expectsType(Mime::getFullMime($mime));

                return $this;
            }
        }

        // This method also adds the custom header support as described in the method comments.
        if (\count($args) === 0) {
            return null;
        }

        // Strip the sugar.  If it leads with "with", strip.
        // This is okay because: No defined HTTP headers begin with with,
        // and if you are defining a custom header, the standard is to prefix it
        // with an "X-", so that should take care of any collisions.
        if (\strpos($method, 'with') === 0) {
            $method = \substr($method, 4);
        }

        // Precede upper case letters with dashes, uppercase the first letter of method.
        $header = \ucwords(\implode('-', (array) \preg_split('/([A-Z][^A-Z]*)/', $method, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY)));
        $this->addHeader($header, $args[0]);

        return $this;
    }

    /**
     * Does the heavy lifting.  Uses de facto HTTP
     * library cURL to set up the HTTP request.
     * Note: It does NOT actually send the request
     *
     * @throws \Exception
     *
     * @return self
     *
     * @internal
     */
    public function _curlPrep(): self
    {
        // Check for required stuff.
        if (!$this->uri) {
            throw new \Exception('Attempting to send a request before defining a URI endpoint.');
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
            throw new ConnectionErrorException('Unable to connect to "' . $this->uri . '". => "curl_init" === false');
        }

        $curl->setOpt(\CURLOPT_IPRESOLVE, \CURL_IPRESOLVE_V4);

        $curl->setOpt(\CURLOPT_CUSTOMREQUEST, $this->method);
        if ($this->method === Helper::HEAD) {
            $curl->setOpt(\CURLOPT_NOBODY, true);
        }

        if ($this->hasBasicAuth()) {
            $curl->setOpt(\CURLOPT_USERPWD, $this->username . ':' . $this->password);
        }

        if ($this->hasClientSideCert()) {
            if (!\file_exists($this->client_key)) {
                throw new \Exception('Could not read Client Key');
            }

            if (!\file_exists($this->client_cert)) {
                throw new \Exception('Could not read Client Certificate');
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
        //Support for value 1 removed in cURL 7.28.1 value 2 valid in all versions
        if ($verifyValue > 0) {
            ++$verifyValue;
        }
        $curl->setOpt(\CURLOPT_SSL_VERIFYHOST, $verifyValue);
        $curl->setOpt(\CURLOPT_RETURNTRANSFER, true);

        // https://github.com/nategood/httpful/issues/84
        // set Content-Length to the size of the payload if present
        if ($this->payload !== []) {
            $curl->setOpt(\CURLOPT_POSTFIELDS, $this->serialized_payload);

            if (!$this->isUpload()) {
                $this->headers['Content-Length'] = $this->_determineLength($this->serialized_payload);
            }
        }

        $headers = [];
        // https://github.com/nategood/httpful/issues/37
        // except header removes any HTTP 1.1 Continue from response headers
        $headers[] = 'Expect:';

        if (!isset($this->headers['User-Agent'])) {
            $headers[] = $this->buildUserAgent();
        }

        $headers[] = "Content-Type: {$this->content_type}";

        // allow custom Accept header if set
        if (!isset($this->headers['Accept'])) {
            // http://pretty-rfc.herokuapp.com/RFC2616#header.accept
            $accept = 'Accept: */*; q=0.5, text/plain; q=0.8, text/html;level=3;';

            if (!empty($this->expected_type)) {
                $accept .= "q=0.9, {$this->expected_type}";
            }

            $headers[] = $accept;
        }

        // Solve a bug on squid proxy, NONE/411 when miss content length.
        if (!isset($this->headers['Content-Length']) && !$this->isUpload()) {
            $this->headers['Content-Length'] = 0;
        }

        foreach ($this->headers as $header => $value) {
            $headers[] = "${header}: ${value}";
        }

        $url = \parse_url($this->uri);

        if (\is_array($url) === false) {
            throw new ConnectionErrorException('Unable to connect to "' . $this->uri . '". => "parse_url" === false');
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
        $url = \parse_url($this->uri);
        $originalParams = [];

        if ($url !== false) {
            if (
                isset($url['query'])
                &&
                $url['query']
            ) {
                \parse_str($url['query'], $originalParams);
            }

            $params = \array_merge($originalParams, (array) $this->params);
        } else {
            $params = (array) $this->params;
        }

        $queryString = \http_build_query($params);

        if (\strpos($this->uri, '?') !== false) {
            $this->uri = \substr(
                $this->uri,
                0,
                \strpos($this->uri, '?')
            );
        }

        if (\count($params)) {
            $this->uri .= '?' . $queryString;
        }
    }

    /**
     * Add an additional header to the request.
     *
     * @param string $header_name
     * @param string $value
     *
     * @return self
     *
     * @see Request::__call()
     */
    public function addHeader($header_name, $value): self
    {
        $this->headers[$header_name] = $value;

        return $this;
    }

    /**
     * Add group of headers all at once.
     *
     * Note: This is here just as a convenience in very specific cases.
     * The preferred "readable" way would be to leverage the support for custom header methods.
     *
     * @param string[] $headers
     *
     * @return self
     */
    public function addHeaders(array $headers): self
    {
        foreach ($headers as $header => $value) {
            $this->addHeader($header, $value);
        }

        return $this;
    }

    /**
     * Semi-reluctantly added this as a way to add in curl opts
     * that are not otherwise accessible from the rest of the API.
     *
     * @param int   $curl_opt
     * @param mixed $curl_opt_val
     *
     * @return self
     */
    public function addOnCurlOption($curl_opt, $curl_opt_val): self
    {
        $this->additional_curl_opts[$curl_opt] = $curl_opt_val;

        return $this;
    }

    /**
     * @return self
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
     * @return self
     */
    public function attach($files): self
    {
        $fInfo = \finfo_open(\FILEINFO_MIME_TYPE);

        foreach ($files as $key => $file) {
            $mimeType = \finfo_file($fInfo, $file);
            $this->payload[$key] = \curl_file_create($file, $mimeType, basename($file));
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
     * @return self
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
     * @return self
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
     * @return self
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
     * @param string|null $mime use a constant from Mime::*
     *
     * @return self
     */
    public function contentType($mime): self
    {
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
     * @return self
     */
    public function contentTypeJson(): self
    {
        $this->content_type = Mime::getFullMime(Mime::JSON);
        if ($this->isUpload()) {
            $this->neverSerializePayload();
        }

        return $this;
    }

    /**
     * Get default for a value based on the template objectl
     *
     * @param string|null $attr Name of attribute (e.g. mime, headers)
     *                          if null just return the whole template object;
     *
     * @return mixed default value
     */
    public function getTemplateAttribute($attr)
    {
        if ($this->_template === null) {
            $this->_initializeDefaultTemplate();
        }

        if (isset($attr)) {
            return $this->_template->{$attr};
        }

        return $this->_template;
    }

    /**
     * HTTP Method Delete
     *
     * @param string      $uri  optional uri to use
     * @param string|null $mime
     *
     * @return self
     */
    public static function delete(string $uri, string $mime = null): self
    {
        return (new self())->init(Helper::DELETE)
            ->uri($uri)
            ->mime($mime);
    }

    /**
     * User Digest Auth.
     *
     * @param string $username
     * @param string $password
     *
     * @return self
     */
    public function digestAuth($username, $password): self
    {
        $this->addOnCurlOption(\CURLOPT_HTTPAUTH, \CURLAUTH_DIGEST);

        return $this->basicAuth($username, $password);
    }

    /**
     * @return self
     *
     * @see Request::_autoParse()
     */
    public function disableAutoParsing(): self
    {
        return $this->_autoParse(false);
    }

    /**
     * @return self
     */
    public function disableStrictSSL(): self
    {
        return $this->_strictSSL(false);
    }

    /**
     * @return self
     *
     * @see Request::followRedirects()
     */
    public function doNotFollowRedirects(): self
    {
        return $this->followRedirects(false);
    }

    /**
     * @return self
     *
     * @see Request::_autoParse()
     */
    public function enableAutoParsing(): self
    {
        return $this->_autoParse(true);
    }

    /**
     * @return self
     */
    public function enableStrictSSL(): self
    {
        return $this->_strictSSL(true);
    }

    /**
     * @return self
     */
    public function expectsCsv(): self
    {
        return $this->expectsType(Mime::CSV);
    }

    /**
     * @return self
     */
    public function expectsForm(): self
    {
        return $this->expectsType(Mime::FORM);
    }

    /**
     * @return self
     */
    public function expectsHtml(): self
    {
        return $this->expectsType(Mime::HTML);
    }

    /**
     * @return self
     */
    public function expectsJavascript(): self
    {
        return $this->expectsType(Mime::JS);
    }

    /**
     * @return self
     */
    public function expectsJs(): self
    {
        return $this->expectsType(Mime::JS);
    }

    /**
     * @return self
     */
    public function expectsJson(): self
    {
        return $this->expectsType(Mime::JSON);
    }

    /**
     * @return self
     */
    public function expectsPlain(): self
    {
        return $this->expectsType(Mime::PLAIN);
    }

    /**
     * @return self
     */
    public function expectsText(): self
    {
        return $this->expectsType(Mime::PLAIN);
    }

    /**
     * @param string|null $mime
     *
     * @return self
     */
    public function expectsType($mime): self
    {
        if (empty($mime)) {
            return $this;
        }

        $this->expected_type = Mime::getFullMime($mime);

        return $this;
    }

    /**
     * @return self
     */
    public function expectsUpload(): self
    {
        return $this->expectsType(Mime::UPLOAD);
    }

    /**
     * @return self
     */
    public function expectsXhtml(): self
    {
        return $this->expectsType(Mime::XHTML);
    }

    /**
     * @return self
     */
    public function expectsXml(): self
    {
        return $this->expectsType(Mime::XML);
    }

    /**
     * @return self
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
     * @return self
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
     * @param string $uri  optional uri to use
     * @param string $mime expected
     *
     * @return self
     */
    public static function get(string $uri, string $mime = null): self
    {
        return (new self())->init(Helper::GET)
            ->uri($uri)
            ->mime($mime);
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
    public function getErrorCallback()
    {
        return $this->error_callback;
    }

    /**
     * @return string
     */
    public function getExpectedType(): string
    {
        return $this->expected_type;
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
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
    public function getUri(): string
    {
        return $this->uri;
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
     * @param string $uri optional uri to use
     *
     * @return self
     */
    public static function head($uri): self
    {
        return (new self())->init(Helper::HEAD)
            ->uri($uri)
            ->mime(Mime::PLAIN);
    }

    /**
     * Let's you configure default settings for this
     * class from a template Request object.  Simply construct a
     * Request object as much as you want to and then pass it to
     * this method.  It will then lock in those settings from
     * that template object.
     * The most common of which may be default mime
     * settings or strict ssl settings.
     * Again some slight memory overhead incurred here but in the grand
     * scheme of things as it typically only occurs once
     *
     * @param self $template
     *
     * @return self
     */
    public function useTemplate(self $template): self
    {
        $this->_template = clone $template;

        $this->_setDefaultsFromTemplate();

        return $this;
    }

    /**
     * Factory style constructor works nicer for chaining.  This
     * should also really only be used internally.  The Request::get,
     * Request::post syntax is preferred as it is more readable.
     *
     * @param string $method Http Method
     * @param string $mime   Mime Type to Use
     *
     * @return self
     */
    public function init($method = null, $mime = null): self
    {
        // Setup the default template if needed.
        if (!isset($this->_template)) {
            $this->_initializeDefaultTemplate();
        }

        $request = new self();

        return $request
            ->_setDefaultsFromTemplate()
            ->method($method)
            ->contentType($mime)
            ->expectsType($mime);
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
     * Set the method.  Shouldn't be called often as the preferred syntax
     * for instantiation is the method specific factory methods.
     *
     * @param string|null $method
     *
     * @return self
     */
    public function method($method): self
    {
        if (empty($method)) {
            return $this;
        }

        $this->method = $method;

        return $this;
    }

    /**
     * Helper function to set the Content type and Expected as same in
     * one swoop
     *
     * @param string|null $mime mime type to use for content type and expected return type
     *
     * @return self
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
     * @return self
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
     * @return self
     */
    public function ntlmAuth($username, $password): self
    {
        $this->addOnCurlOption(\CURLOPT_HTTPAUTH, \CURLAUTH_NTLM);

        return $this->basicAuth($username, $password);
    }

    /**
     * HTTP Method Options
     *
     * @param string $uri optional uri to use
     *
     * @return self
     */
    public static function options($uri): self
    {
        return (new self())->init(Helper::OPTIONS)->uri($uri);
    }

    /**
     * Add additional parameter to be appended to the query string.
     *
     * @param string $key
     * @param string $value
     *
     * @return self this
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
     * @return self this
     */
    public function params(array $params): self
    {
        $this->params = \array_merge($this->params, $params);

        return $this;
    }

    /**
     * @param callable $callback
     *
     * @return self
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
     * @param string $uri     optional uri to use
     * @param mixed  $payload data to send in body of request
     * @param string $mime    MIME to use for Content-Type
     *
     * @return self
     */
    public static function patch(string $uri, $payload = null, string $mime = null): self
    {
        return (new self())->init(Helper::PATCH)
            ->uri($uri)
            ->_setBody($payload, $mime);
    }

    /**
     * HTTP Method Post
     *
     * @param string $uri     optional uri to use
     * @param mixed  $payload data to send in body of request
     * @param string $mime    MIME to use for Content-Type
     *
     * @return self
     */
    public static function post(string $uri, $payload = null, string $mime = null): self
    {
        return (new self())->init(Helper::POST)
            ->uri($uri)
            ->_setBody($payload, $mime);
    }

    /**
     * HTTP Method Put
     *
     * @param string $uri     optional uri to use
     * @param mixed  $payload data to send in body of request
     * @param string $mime    MIME to use for Content-Type
     *
     * @return self
     */
    public static function put(string $uri, $payload = null, string $mime = null): self
    {
        return (new self())->init(Helper::PUT)
            ->uri($uri)
            ->_setBody($payload, $mime);
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
     * @return self
     */
    public function registerPayloadSerializer($mime, callable $callback): self
    {
        $this->payload_serializers[Mime::getFullMime($mime)] = $callback;

        return $this;
    }

    /**
     * Actually send off the request, and parse the response
     *
     * @throws ConnectionErrorException when unable to parse or communicate w server
     *
     * @return Response with parsed results
     */
    public function send(): Response
    {
        if (!$this->hasBeenInitialized()) {
            $this->_curlPrep();
        }

        if ($this->_curl === null) {
            throw new ConnectionErrorException('Unable to connect to "' . $this->uri . '". => "curl" === null');
        }

        switch ($this->method) {
            case Helper::DELETE:
                $result = $this->_curl->delete($this->uri);
                break;
            case Helper::GET:
                $result = $this->_curl->get($this->uri);
                break;
            case Helper::POST:
                $result = $this->_curl->post($this->uri);
                break;
            case Helper::PUT:
                $result = $this->_curl->put($this->uri);
                break;
            case Helper::HEAD:
                $result = $this->_curl->head($this->uri);
                break;
            case Helper::PATCH:
                $result = $this->_curl->patch($this->uri);
                break;
            case Helper::OPTIONS:
                $result = $this->_curl->options($this->uri);
                break;
            default:
                $result = $this->_curl->exec();
        }

        $response = $this->_buildResponse($result);

        $this->_curl->close();
        $this->_curl = null;

        return $response;
    }

    /**
     * @param string|null $mime
     *
     * @return self
     */
    public function mimeType($mime): self
    {
        return $this->mime($mime);
    }

    /**
     * @return self
     */
    public function sendsCsv(): self
    {
        return $this->contentType(Mime::CSV);
    }

    /**
     * @return self
     */
    public function sendsForm(): self
    {
        return $this->contentType(Mime::FORM);
    }

    /**
     * @return self
     */
    public function sendsHtml(): self
    {
        return $this->contentType(Mime::HTML);
    }

    /**
     * @return self
     */
    public function sendsJavascript(): self
    {
        return $this->contentType(Mime::JS);
    }

    /**
     * @return self
     */
    public function sendsJs(): self
    {
        return $this->contentType(Mime::JS);
    }

    /**
     * @return self
     */
    public function sendsJson(): self
    {
        return $this->contentType(Mime::JSON);
    }

    /**
     * @return self
     */
    public function sendsPlain(): self
    {
        return $this->contentType(Mime::PLAIN);
    }

    /**
     * @return self
     */
    public function sendsText(): self
    {
        return $this->contentType(Mime::PLAIN);
    }

    /**
     * @return self
     */
    public function sendsUpload(): self
    {
        return $this->contentType(Mime::UPLOAD);
    }

    /**
     * @return self
     */
    public function sendsXhtml(): self
    {
        return $this->contentType(Mime::XHTML);
    }

    /**
     * @return self
     */
    public function sendsXml(): self
    {
        return $this->contentType(Mime::XML);
    }

    /**
     * @return self
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
     * @return self
     */
    public function serializePayload($mode): self
    {
        $this->serialize_payload_method = $mode;

        return $this;
    }

    /**
     * @param callable $callback
     *
     * @return self
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
     * @return self
     */
    public function setConnectionTimeout($connection_timeout): self
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
     * @param callable|LoggerInterface|null $error_callback
     *
     * @return self
     */
    public function setErrorCallback($error_callback): self
    {
        $this->error_callback = $error_callback;

        return $this;
    }

    /**
     * Use a custom function to parse the response.
     *
     * @param callable $callback Takes the raw body of
     *                           the http response and returns a mixed
     *
     * @return self
     */
    public function setParseCallback(callable $callback): self
    {
        $this->parse_callback = $callback;

        return $this;
    }

    /**
     * @param callable|null $send_callback
     *
     * @return self
     */
    public function setSendCallback($send_callback): self
    {
        if (!empty($send_callback)) {
            $this->send_callbacks[] = $send_callback;
        }

        return $this;
    }

    /**
     * @param string $uri
     *
     * @return self
     */
    public function setUri(string $uri): self
    {
        $this->uri = $uri;

        return $this;
    }

    /**
     * Sets user agent.
     *
     * @param string $userAgent
     *
     * @return self
     */
    public function setUserAgent($userAgent): self
    {
        return $this->addHeader('User-Agent', $userAgent);
    }

    /**
     * This method is the default behavior
     *
     * @return self
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
     * @return self
     */
    public function timeout($timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * @param string $uri
     *
     * @return self
     */
    public function uri($uri): self
    {
        $this->uri = $uri;

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
     * @return self
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
     * @return self
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
     * @return self
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
     * @return self
     */
    public function withUserAgent($userAgent): self
    {
        $return = $this->__call('withUserAgent', [$userAgent]);

        if ($return === null) {
            return $this;
        }

        return $return;
    }

    /**
     * @param string $error
     */
    private function _error($error)
    {
        if (isset($this->error_callback)) {
            if ($this->error_callback instanceof LoggerInterface) {
                // PSR-3 https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md
                $this->error_callback->error($error);
            } elseif (\is_callable($this->error_callback)) {
                // error callback
                \call_user_func($this->error_callback, $error);
            }
        } else {
            /** @noinspection ForgottenDebugOutputInspection */
            \error_log($error);
        }
    }

    /**
     * This is the default template to use if no
     * template has been provided.  The template
     * tells the class which default values to use.
     * While there is a slight overhead for object
     * creation once per execution (not once per
     * Request instantiation), it promotes readability
     * and flexibility within the class.
     */
    private function _initializeDefaultTemplate()
    {
        // This is the only place you will see this constructor syntax.
        // It is only done here to prevent infinite recursion.
        // Do not use this syntax elsewhere.
        // It goes against the whole readability and transparency idea.
        $this->_template = new self(['method' => Helper::GET]);

        // This is more like it...
        $this->_template->disableStrictSSL();
    }

    /**
     * Turn payload from structured data into
     * a string based on the current Mime type.
     * This uses the auto_serialize option to determine
     * it's course of action.  See serialize method for more.
     * Renamed from _detectPayload to _serializePayload as of
     * 2012-02-15.
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
            \is_scalar($payload_first = \array_values($payload)[0])
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

        return Setup::setupMimeType($this->content_type)->serialize($payload);
    }

    /**
     * Set the defaults on a newly instantiated object
     * Doesn't copy variables prefixed with _
     *
     * @return self
     */
    private function _setDefaultsFromTemplate(): self
    {
        if ($this->_template === null) {
            $this->_initializeDefaultTemplate();
        }

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
     * @param bool $auto_parse perform automatic "smart"
     *                         parsing based on Content-Type or "expectedType"
     *                         If not auto parsing, Response->body returns the body
     *                         as a string
     *
     * @return self
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
     *@throws ConnectionErrorException
     *
     * @return Response
     */
    private function _buildResponse($result): Response
    {
        if ($this->_curl === null) {
            throw new ConnectionErrorException('Unable to build the response for "' . $this->uri . '". => "curl" === null');
        }

        if ($result === false) {
            $curlErrorNumber = $this->_curl->getErrorCode();
            if ($curlErrorNumber) {
                $curlErrorString = $this->_curl->getErrorMessage();

                $this->_error($curlErrorString);

                $exception = new ConnectionErrorException(
                    'Unable to connect to "' . $this->uri . '": ' . $curlErrorNumber . ' ' . $curlErrorString,
                    $curlErrorNumber,
                    null,
                    $this->_curl
                );

                $exception->setCurlErrorNumber($curlErrorNumber)->setCurlErrorString($curlErrorString);

                throw $exception;
            }

            $this->_error('Unable to connect to "' . $this->uri . '".');

            throw new ConnectionErrorException('Unable to connect to "' . $this->uri . '".');
        }

        $info = $this->_curl->getInfo();

        $headers = $this->_curl->getRawResponseHeaders();

        $body = UTF8::remove_left(
            (string)$this->_curl->getRawResponse(),
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
        $info['protocol_version'] = $protocol_version;

        return new Response(
            (string) $body,
            $headers,
            $this,
            $info
        );
    }

    /**
     * Set the body of the request.
     *
     * @param mixed|null  $payload
     * @param string|null $mimeType currently, sets the sends AND expects mime type although this
     *                              behavior may change in the next minor release (as it is a potential breaking change)
     *
     * @return self
     */
    private function _setBody($payload, string $mimeType = null): self
    {
        $this->mime($mimeType);

        if (!empty($payload)) {
            $this->payload[] = $payload;
        }

        // Don't call _serializePayload yet.
        // Wait until we actually send off the request to convert payload to string.
        // At that time, the `serialized_payload` is set accordingly.

        return $this;
    }

    /**
     * Do we strictly enforce SSL verification?
     *
     * @param bool $strict
     *
     * @return self
     */
    private function _strictSSL($strict): self
    {
        $this->strict_ssl = $strict;

        return $this;
    }
}
