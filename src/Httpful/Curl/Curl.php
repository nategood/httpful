<?php declare(strict_types=1);

namespace Httpful\Curl;

use Httpful\Request;
use Httpful\Uri;
use Httpful\UriResolver;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * @internal
 */
final class Curl
{
    const DEFAULT_TIMEOUT = 30;

    /**
     * @var bool
     */
    public $error = false;

    /**
     * @var int
     */
    public $errorCode = 0;

    /**
     * @var string|null
     */
    public $errorMessage;

    /**
     * @var bool
     */
    public $curlError = false;

    /**
     * @var int
     */
    public $curlErrorCode = 0;

    /**
     * @var string|null
     */
    public $curlErrorMessage;

    /**
     * @var bool
     */
    public $httpError = false;

    /**
     * @var int
     */
    public $httpStatusCode = 0;

    /**
     * @var bool|string
     */
    public $rawResponse;

    /**
     * @var callable|null
     */
    public $beforeSendCallback;

    /**
     * @var callable|null
     */
    public $downloadCompleteCallback;

    /**
     * @var string|null
     */
    public $downloadFileName;

    /**
     * @var callable|null
     */
    public $successCallback;

    /**
     * @var callable|null
     */
    public $errorCallback;

    /**
     * @var callable|null
     */
    public $completeCallback;

    /**
     * @var false|resource|null
     */
    public $fileHandle;

    /**
     * @var int
     */
    public $attempts = 0;

    /**
     * @var int
     */
    public $retries = 0;

    /**
     * @var RequestInterface|null
     */
    public $request;

    /**
     * @var resource
     */
    private $curl;

    /**
     * @var int|string|null
     */
    private $id;

    /**
     * @var string
     */
    private $rawResponseHeaders = '';

    /**
     * @var array
     */
    private $responseCookies = [];

    /**
     * @var bool
     */
    private $childOfMultiCurl = false;

    /**
     * @var int
     */
    private $remainingRetries = 0;

    /**
     * @var callable|null
     */
    private $retryDecider;

    /**
     * @var UriInterface|null
     */
    private $url;

    /**
     * @var array
     */
    private $cookies = [];

    /**
     * @var \stdClass|null
     */
    private $headerCallbackData;

    /**
     * @param string $base_url
     */
    public function __construct($base_url = '')
    {
        if (!\extension_loaded('curl')) {
            throw new \ErrorException('cURL library is not loaded');
        }

        $this->curl = \curl_init();
        $this->initialize($base_url);
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * @return bool
     */
    public function attemptRetry()
    {
        // init
        $attempt_retry = false;

        if ($this->error) {
            if ($this->retryDecider === null) {
                $attempt_retry = $this->remainingRetries >= 1;
            } else {
                $attempt_retry = \call_user_func($this->retryDecider, $this);
            }
            if ($attempt_retry) {
                ++$this->retries;
                if ($this->remainingRetries) {
                    --$this->remainingRetries;
                }
            }
        }

        return $attempt_retry;
    }

    /**
     * @param callable $callback
     *
     * @return $this
     */
    public function beforeSend($callback)
    {
        $this->beforeSendCallback = $callback;

        return $this;
    }

    /**
     * @param mixed $function
     *
     * @return void
     */

    /**
     * @param callable|null $function
     * @param mixed         ...$args
     *
     * @return $this
     */
    public function call($function, ...$args)
    {
        if (\is_callable($function)) {
            \array_unshift($args, $this);
            \call_user_func_array($function, $args);
        }

        return $this;
    }

    /**
     * @return void
     */
    public function close()
    {
        if (\is_resource($this->curl)) {
            \curl_close($this->curl);
        }
    }

    /**
     * @param callable $callback
     *
     * @return $this
     */
    public function complete($callback)
    {
        $this->completeCallback = $callback;

        return $this;
    }

    /**
     * @param callable|string $filename_or_callable
     *
     * @return $this
     */
    public function download($filename_or_callable)
    {
        // Use tmpfile() or php://temp to avoid "Too many open files" error.
        if (\is_callable($filename_or_callable)) {
            $this->downloadCompleteCallback = $filename_or_callable;
            $this->downloadFileName = null;
            $this->fileHandle = \tmpfile();
        } else {
            $filename = $filename_or_callable;

            // Use a temporary file when downloading. Not using a temporary file can cause an error when an existing
            // file has already fully completed downloading and a new download is started with the same destination save
            // path. The download request will include header "Range: bytes=$file_size-" which is syntactically valid,
            // but unsatisfiable.
            $download_filename = $filename . '.pccdownload';
            $this->downloadFileName = $download_filename;

            // Attempt to resume download only when a temporary download file exists and is not empty.
            if (
                \is_file($download_filename)
                &&
                $file_size = \filesize($download_filename)
            ) {
                $first_byte_position = $file_size;
                $range = $first_byte_position . '-';
                $this->setRange($range);
                $this->fileHandle = \fopen($download_filename, 'ab');
            } else {
                $this->fileHandle = \fopen($download_filename, 'wb');
            }

            // Move the downloaded temporary file to the destination save path.
            $this->downloadCompleteCallback = static function ($instance, $fh) use ($download_filename, $filename) {
                // Close the open file handle before renaming the file.
                if (\is_resource($fh)) {
                    \fclose($fh);
                }

                \rename($download_filename, $filename);
            };
        }

        if ($this->fileHandle === false) {
            throw new \Httpful\Exception\ClientErrorException('Unable to write to file:' . $this->downloadFileName);
        }

        $this->setFile($this->fileHandle);

        return $this;
    }

    /**
     * @param callable $callback
     *
     * @return $this
     */
    public function error($callback)
    {
        $this->errorCallback = $callback;

        return $this;
    }

    /**
     * @param false|resource|null $ch
     *
     * @return mixed returns the value provided by parseResponse
     */
    public function exec($ch = null)
    {
        ++$this->attempts;

        if ($ch === false || $ch === null) {
            $this->responseCookies = [];
            $this->call($this->beforeSendCallback);
            $this->rawResponse = \curl_exec($this->curl);
            $this->curlErrorCode = \curl_errno($this->curl);
            $this->curlErrorMessage = \curl_error($this->curl);
        } elseif ($ch !== null) {
            $this->rawResponse = \curl_multi_getcontent($ch);
            $this->curlErrorMessage = \curl_error($ch);
        }

        $this->curlError = $this->curlErrorCode !== 0;

        // Transfer the header callback data and release the temporary store to avoid memory leak.
        if ($this->headerCallbackData === null) {
            $this->headerCallbackData = new \stdClass();
        }
        $this->rawResponseHeaders = $this->headerCallbackData->rawResponseHeaders;
        $this->responseCookies = $this->headerCallbackData->responseCookies;
        $this->headerCallbackData->rawResponseHeaders = '';
        $this->headerCallbackData->responseCookies = [];

        // Include additional error code information in error message when possible.
        if ($this->curlError && \function_exists('curl_strerror')) {
            $this->curlErrorMessage = \curl_strerror($this->curlErrorCode) . (empty($this->curlErrorMessage) ? '' : ': ' . $this->curlErrorMessage);
        }

        $this->httpStatusCode = $this->getInfo(\CURLINFO_HTTP_CODE);
        $this->httpError = \in_array((int) \floor($this->httpStatusCode / 100), [4, 5], true);
        $this->error = $this->curlError || $this->httpError;
        /** @noinspection NestedTernaryOperatorInspection */
        $this->errorCode = $this->error ? ($this->curlError ? $this->curlErrorCode : $this->httpStatusCode) : 0;
        $this->errorMessage = $this->curlError ? $this->curlErrorMessage : '';

        // Reset nobody setting possibly set from a HEAD request.
        $this->setOpt(\CURLOPT_NOBODY, false);

        // Allow multicurl to attempt retry as needed.
        if ($this->isChildOfMultiCurl()) {
            /** @noinspection PhpInconsistentReturnPointsInspection */
            return;
        }

        if ($this->attemptRetry()) {
            return $this->exec($ch);
        }

        $this->execDone();

        return $this->rawResponse;
    }

    /**
     * @return void
     */
    public function execDone()
    {
        if ($this->error) {
            $this->call($this->errorCallback);
        } else {
            $this->call($this->successCallback);
        }

        $this->call($this->completeCallback);

        // Close open file handles and reset the curl instance.
        if (\is_resource($this->fileHandle)) {
            $this->downloadComplete($this->fileHandle);
        }

        // Free some memory + help the GC to free some more memory.
        if ($this->request instanceof Request) {
            $this->request->clearHelperData();
        }

        $this->request = null;
    }

    /**
     * @return int
     */
    public function getAttempts()
    {
        return $this->attempts;
    }

    /**
     * @return callable|null
     */
    public function getBeforeSendCallback()
    {
        return $this->beforeSendCallback;
    }

    /**
     * @return callable|null
     */
    public function getCompleteCallback()
    {
        return $this->completeCallback;
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function getCookie($key)
    {
        return $this->getResponseCookie($key);
    }

    /**
     * @return false|resource
     */
    public function getCurl()
    {
        return $this->curl;
    }

    /**
     * @return int
     */
    public function getCurlErrorCode()
    {
        return $this->curlErrorCode;
    }

    /**
     * @return string|null
     */
    public function getCurlErrorMessage()
    {
        return $this->curlErrorMessage;
    }

    /**
     * @return callable|null
     */
    public function getDownloadCompleteCallback()
    {
        return $this->downloadCompleteCallback;
    }

    /**
     * @return string|null
     */
    public function getDownloadFileName()
    {
        return $this->downloadFileName;
    }

    /**
     * @return callable|null
     */
    public function getErrorCallback()
    {
        return $this->errorCallback;
    }

    /**
     * @return int
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * @return string|null
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * @return false|resource|null
     */
    public function getFileHandle()
    {
        return $this->fileHandle;
    }

    /**
     * @return int
     */
    public function getHttpStatusCode()
    {
        return $this->httpStatusCode;
    }

    /**
     * @return int|string|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int|null $opt
     *
     * @return mixed
     */
    public function getInfo($opt = null)
    {
        $args = [];
        $args[] = $this->curl;

        if (\func_num_args()) {
            $args[] = $opt;
        }

        return \curl_getinfo(...$args);
    }

    /**
     * @return bool|string
     */
    public function getRawResponse()
    {
        return $this->rawResponse;
    }

    /**
     * @return string
     */
    public function getRawResponseHeaders()
    {
        return $this->rawResponseHeaders;
    }

    /**
     * @return int
     */
    public function getRemainingRetries()
    {
        return $this->remainingRetries;
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function getResponseCookie($key)
    {
        return $this->responseCookies[$key] ?? null;
    }

    /**
     * @return array
     */
    public function getResponseCookies()
    {
        return $this->responseCookies;
    }

    /**
     * @return int
     */
    public function getRetries()
    {
        return $this->retries;
    }

    /**
     * @return callable|null
     */
    public function getRetryDecider()
    {
        return $this->retryDecider;
    }

    /**
     * @return callable|null
     */
    public function getSuccessCallback()
    {
        return $this->successCallback;
    }

    /**
     * @return UriInterface|null
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @return bool
     */
    public function isChildOfMultiCurl(): bool
    {
        return $this->childOfMultiCurl;
    }

    /**
     * @return bool
     */
    public function isCurlError(): bool
    {
        return $this->curlError;
    }

    /**
     * @return bool
     */
    public function isError(): bool
    {
        return $this->error;
    }

    /**
     * @return bool
     */
    public function isHttpError(): bool
    {
        return $this->httpError;
    }

    /**
     * @param callable $callback
     *
     * @return $this
     */
    public function progress($callback)
    {
        $this->setOpt(\CURLOPT_PROGRESSFUNCTION, $callback);
        $this->setOpt(\CURLOPT_NOPROGRESS, false);

        return $this;
    }

    /**
     * @return void
     */
    public function reset()
    {
        if (\function_exists('curl_reset') && \is_resource($this->curl)) {
            \curl_reset($this->curl);
        } else {
            $this->curl = \curl_init();
        }

        $this->initialize('');
    }

    /**
     * @param string $username
     * @param string $password
     *
     * @return $this
     */
    public function setBasicAuthentication($username, $password = '')
    {
        $this->setOpt(\CURLOPT_HTTPAUTH, \CURLAUTH_BASIC);
        $this->setOpt(\CURLOPT_USERPWD, $username . ':' . $password);

        return $this;
    }

    /**
     * @param bool $bool
     *
     * @return $this
     */
    public function setChildOfMultiCurl(bool $bool)
    {
        $this->childOfMultiCurl = $bool;

        return $this;
    }

    /**
     * @param int $seconds
     *
     * @return $this
     */
    public function setConnectTimeout($seconds)
    {
        $this->setOpt(\CURLOPT_CONNECTTIMEOUT, $seconds);

        return $this;
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return $this
     */
    public function setCookie($key, $value)
    {
        $this->setEncodedCookie($key, $value);
        $this->buildCookies();

        return $this;
    }

    /**
     * @param string $cookie_file
     *
     * @return $this
     */
    public function setCookieFile($cookie_file)
    {
        $this->setOpt(\CURLOPT_COOKIEFILE, $cookie_file);

        return $this;
    }

    /**
     * @param string $cookie_jar
     *
     * @return $this
     */
    public function setCookieJar($cookie_jar)
    {
        $this->setOpt(\CURLOPT_COOKIEJAR, $cookie_jar);

        return $this;
    }

    /**
     * @param string $string
     *
     * @return $this
     */
    public function setCookieString($string)
    {
        $this->setOpt(\CURLOPT_COOKIE, $string);

        return $this;
    }

    /**
     * @param array $cookies
     *
     * @return $this
     */
    public function setCookies($cookies)
    {
        foreach ($cookies as $key => $value) {
            $this->setEncodedCookie($key, $value);
        }
        $this->buildCookies();

        return $this;
    }

    /**
     * @return $this
     */
    public function setDefaultTimeout()
    {
        $this->setTimeout(self::DEFAULT_TIMEOUT);

        return $this;
    }

    /**
     * @param string $username
     * @param string $password
     *
     * @return $this
     */
    public function setDigestAuthentication($username, $password = '')
    {
        $this->setOpt(\CURLOPT_HTTPAUTH, \CURLAUTH_DIGEST);
        $this->setOpt(\CURLOPT_USERPWD, $username . ':' . $password);

        return $this;
    }

    /**
     * @param int|string $id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @param int $bytes
     *
     * @return $this
     */
    public function setMaxFilesize($bytes)
    {
        $callback = static function ($resource, $download_size, $downloaded, $upload_size, $uploaded) use ($bytes) {
            // Abort the transfer when $downloaded bytes exceeds maximum $bytes by returning a non-zero value.
            return $downloaded > $bytes ? 1 : 0;
        };

        $this->progress($callback);

        return $this;
    }

    /**
     * @param int   $option
     * @param mixed $value
     *
     * @return bool
     */
    public function setOpt($option, $value)
    {
        return \curl_setopt($this->curl, $option, $value);
    }

    /**
     * @param resource $file
     *
     * @return $this
     */
    public function setFile($file)
    {
        $this->setOpt(\CURLOPT_FILE, $file);

        return $this;
    }

    /**
     * @param array $options
     *
     * @return bool
     *              <p>Returns true if all options were successfully set. If an option could not be successfully set,
     *              false is immediately returned, ignoring any future options in the options array. Similar to
     *              curl_setopt_array().</p>
     */
    public function setOpts($options)
    {
        foreach ($options as $option => $value) {
            if (!$this->setOpt($option, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param int $port
     *
     * @return $this
     */
    public function setPort($port)
    {
        $this->setOpt(\CURLOPT_PORT, (int) $port);

        return $this;
    }

    /**
     * Set an HTTP proxy to tunnel requests through.
     *
     * @param string $proxy    - The HTTP proxy to tunnel requests through. May include port number.
     * @param int    $port     - The port number of the proxy to connect to. This port number can also be set in $proxy.
     * @param string $username - The username to use for the connection to the proxy
     * @param string $password - The password to use for the connection to the proxy
     *
     * @return $this
     */
    public function setProxy($proxy, $port = null, $username = null, $password = null)
    {
        $this->setOpt(\CURLOPT_PROXY, $proxy);

        if ($port !== null) {
            $this->setOpt(\CURLOPT_PROXYPORT, $port);
        }

        if ($username !== null && $password !== null) {
            $this->setOpt(\CURLOPT_PROXYUSERPWD, $username . ':' . $password);
        }

        return $this;
    }

    /**
     * Set the HTTP authentication method(s) to use for the proxy connection.
     *
     * @param int $auth
     *
     * @return $this
     */
    public function setProxyAuth($auth)
    {
        $this->setOpt(\CURLOPT_PROXYAUTH, $auth);

        return $this;
    }

    /**
     * Set the proxy to tunnel through HTTP proxy.
     *
     * @param bool $tunnel
     *
     * @return $this
     */
    public function setProxyTunnel($tunnel = true)
    {
        $this->setOpt(\CURLOPT_HTTPPROXYTUNNEL, $tunnel);

        return $this;
    }

    /**
     * Set the proxy protocol type.
     *
     * @param int $type
     *                  <p>CURLPROXY_*</p>
     *
     * @return $this
     */
    public function setProxyType($type)
    {
        $this->setOpt(\CURLOPT_PROXYTYPE, $type);

        return $this;
    }

    /**
     * @param string $range <p>e.g. "0-4096"</p>
     *
     * @return $this
     */
    public function setRange($range)
    {
        $this->setOpt(\CURLOPT_RANGE, $range);

        return $this;
    }

    /**
     * @param string $referer
     *
     * @return $this
     */
    public function setReferer($referer)
    {
        $this->setReferrer($referer);

        return $this;
    }

    /**
     * @param string $referrer
     *
     * @return $this
     */
    public function setReferrer($referrer)
    {
        $this->setOpt(\CURLOPT_REFERER, $referrer);

        return $this;
    }

    /**
     * Number of retries to attempt or decider callable.
     *
     * When using a number of retries to attempt, the maximum number of attempts
     * for the request is $maximum_number_of_retries + 1.
     *
     * When using a callable decider, the request will be retried until the
     * function returns a value which evaluates to false.
     *
     * @param callable|int $retry
     *
     * @return $this
     */
    public function setRetry($retry)
    {
        if (\is_callable($retry)) {
            $this->retryDecider = $retry;
        } elseif (\is_int($retry)) {
            $maximum_number_of_retries = $retry;
            $this->remainingRetries = $maximum_number_of_retries;
        }

        return $this;
    }

    /**
     * @param int $seconds
     *
     * @return $this
     */
    public function setTimeout($seconds)
    {
        $this->setOpt(\CURLOPT_TIMEOUT, $seconds);

        return $this;
    }

    /**
     * @param string $url
     * @param mixed  $mixed_data
     *
     * @return $this
     */
    public function setUrl($url, $mixed_data = '')
    {
        $built_url = new Uri($this->buildUrl($url, $mixed_data));

        if ($this->url === null) {
            $this->url = UriResolver::resolve($built_url, new Uri(''));
        } else {
            $this->url = UriResolver::resolve($this->url, $built_url);
        }

        $this->setOpt(\CURLOPT_URL, (string) $this->url);

        return $this;
    }

    /**
     * @param string $user_agent
     *
     * @return $this
     */
    public function setUserAgent($user_agent)
    {
        $this->setOpt(\CURLOPT_USERAGENT, $user_agent);

        return $this;
    }

    /**
     * @param callable $callback
     *
     * @return $this
     */
    public function success($callback)
    {
        $this->successCallback = $callback;

        return $this;
    }

    /**
     * Disable use of the proxy.
     *
     * @return $this
     */
    public function unsetProxy()
    {
        $this->setOpt(\CURLOPT_PROXY, null);

        return $this;
    }

    /**
     * @param bool          $on
     * @param resource|null $output
     *
     * @return $this
     */
    public function verbose($on = true, $output = null)
    {
        // fallback
        if ($output === null) {
            if (!\defined('STDERR')) {
                \define('STDERR', \fopen('php://stderr', 'wb'));
            }
            $output = \STDERR;
        }

        $this->setOpt(\CURLOPT_VERBOSE, $on);
        $this->setOpt(\CURLOPT_STDERR, $output);

        return $this;
    }

    /**
     * Build Cookies
     *
     * @return void
     */
    private function buildCookies()
    {
        // Avoid using http_build_query() as unnecessary encoding is performed.
        // http_build_query($this->cookies, '', '; ');
        $this->setOpt(
            \CURLOPT_COOKIE,
            \implode(
                '; ',
                \array_map(
                    static function ($k, $v) {
                        return $k . '=' . $v;
                    },
                    \array_keys($this->cookies),
                    \array_values($this->cookies)
                )
            )
        );
    }

    /**
     * @param string $url
     * @param mixed  $mixed_data
     *
     * @return string
     */
    private function buildUrl($url, $mixed_data = '')
    {
        // init
        $query_string = '';

        if (!empty($mixed_data)) {
            $query_mark = \strpos($url, '?') > 0 ? '&' : '?';
            if (\is_string($mixed_data)) {
                $query_string .= $query_mark . $mixed_data;
            } elseif (\is_array($mixed_data)) {
                $query_string .= $query_mark . \http_build_query($mixed_data, '', '&');
            }
        }

        return $url . $query_string;
    }

    /**
     * Create Header Callback
     *
     * Gather headers and parse cookies as response headers are received. Keep this function separate from the class so
     * that unset($curl) automatically calls __destruct() as expected. Otherwise, manually calling $curl->close() will
     * be necessary to prevent a memory leak.
     *
     * @param \stdClass $header_callback_data
     *
     * @return callable
     */
    private function createHeaderCallback($header_callback_data)
    {
        return static function ($ch, $header) use ($header_callback_data) {
            if (\preg_match('/^Set-Cookie:\s*([^=]+)=([^;]+)/mi', $header, $cookie) === 1) {
                $header_callback_data->responseCookies[$cookie[1]] = \trim($cookie[2], " \n\r\t\0\x0B");
            }
            $header_callback_data->rawResponseHeaders .= $header;

            return \strlen($header);
        };
    }

    /**
     * @param resource $fh
     *
     * @return void
     */
    private function downloadComplete($fh)
    {
        if (
            $this->error
            &&
            $this->downloadFileName
            &&
            \is_file($this->downloadFileName)
        ) {
            /** @noinspection PhpUsageOfSilenceOperatorInspection */
            @\unlink($this->downloadFileName);
        } elseif (
            !$this->error
            &&
            $this->downloadCompleteCallback
        ) {
            \rewind($fh);
            $this->call($this->downloadCompleteCallback, $fh);
            $this->downloadCompleteCallback = null;
        }

        if (\is_resource($fh)) {
            \fclose($fh);
        }

        // Fix "PHP Notice: Use of undefined constant STDOUT" when reading the
        // PHP script from stdin. Using null causes "Warning: curl_setopt():
        // supplied argument is not a valid File-Handle resource".
        if (!\defined('STDOUT')) {
            \define('STDOUT', \fopen('php://stdout', 'wb'));
        }

        // Reset CURLOPT_FILE with STDOUT to avoid: "curl_exec(): CURLOPT_FILE
        // resource has gone away, resetting to default".
        $this->setFile(\STDOUT);

        // Reset CURLOPT_RETURNTRANSFER to tell cURL to return subsequent
        // responses as the return value of curl_exec(). Without this,
        // curl_exec() will revert to returning boolean values.
        $this->setOpt(\CURLOPT_RETURNTRANSFER, true);
    }

    /**
     * @param string $base_url
     *
     * @return void
     */
    private function initialize($base_url)
    {
        $this->setId(\uniqid('', true));
        $this->setDefaultTimeout();
        $this->setOpt(\CURLINFO_HEADER_OUT, true);

        // Create a placeholder to temporarily store the header callback data.
        $header_callback_data = new \stdClass();
        $header_callback_data->rawResponseHeaders = '';
        $header_callback_data->responseCookies = [];
        $this->headerCallbackData = $header_callback_data;
        $this->setOpt(\CURLOPT_HEADERFUNCTION, $this->createHeaderCallback($header_callback_data));

        $this->setOpt(\CURLOPT_RETURNTRANSFER, true);
        $this->setUrl($base_url);
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return $this
     */
    private function setEncodedCookie($key, $value)
    {
        $name_chars = [];
        foreach (\str_split($key) as $name_char) {
            $name_chars[] = \rawurlencode($name_char);
        }

        $value_chars = [];
        foreach (\str_split($value) as $value_char) {
            $value_chars[] = \rawurlencode($value_char);
        }

        $this->cookies[\implode('', $name_chars)] = \implode('', $value_chars);

        return $this;
    }
}
