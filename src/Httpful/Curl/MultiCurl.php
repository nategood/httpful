<?php declare(strict_types=1);

namespace Httpful\Curl;

/**
 * @internal
 */
final class MultiCurl
{
    /**
     * @var resource
     */
    private $multiCurl;

    /**
     * @var Curl[]
     */
    private $curls = [];

    /**
     * @var Curl[]
     */
    private $activeCurls = [];

    /**
     * @var bool
     */
    private $isStarted = false;

    /**
     * @var int
     */
    private $concurrency = 25;

    /**
     * @var int
     */
    private $nextCurlId = 0;

    /**
     * @var callable|null
     */
    private $beforeSendCallback;

    /**
     * @var callable|null
     */
    private $successCallback;

    /**
     * @var callable|null
     */
    private $errorCallback;

    /**
     * @var callable|null
     */
    private $completeCallback;

    /**
     * @var callable|int
     */
    private $retry;

    /**
     * @var array
     */
    private $cookies = [];

    public function __construct()
    {
        $multiCurl = \curl_multi_init();
        if ($multiCurl === false) {
            throw new \RuntimeException('curl_multi_init() returned false!');
        }

        $this->multiCurl = $multiCurl;
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * Add a Curl instance to the handle queue.
     *
     * @param Curl $curl
     *
     * @return $this
     */
    public function addCurl(Curl $curl)
    {
        $this->queueHandle($curl);

        return $this;
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
     * @return void
     */
    public function close()
    {
        foreach ($this->curls as $curl) {
            $curl->close();
        }

        if (\is_resource($this->multiCurl)) {
            \curl_multi_close($this->multiCurl);
        }
    }

    /**
     * @param callable $callback
     *
     * @return $this;
     */
    public function complete($callback)
    {
        $this->completeCallback = $callback;

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
     * @param Curl            $curl
     * @param callable|string $mixed_filename
     *
     * @return Curl
     */
    public function addDownload(Curl $curl, $mixed_filename)
    {
        $this->queueHandle($curl);

        // Use tmpfile() or php://temp to avoid "Too many open files" error.
        if (\is_callable($mixed_filename)) {
            $curl->downloadCompleteCallback = $mixed_filename;
            $curl->downloadFileName = null;
            $curl->fileHandle = \tmpfile();
        } else {
            $filename = $mixed_filename;

            // Use a temporary file when downloading. Not using a temporary file can cause an error when an existing
            // file has already fully completed downloading and a new download is started with the same destination save
            // path. The download request will include header "Range: bytes=$filesize-" which is syntactically valid,
            // but unsatisfiable.
            $download_filename = $filename . '.pccdownload';
            $curl->downloadFileName = $download_filename;

            // Attempt to resume download only when a temporary download file exists and is not empty.
            if (\is_file($download_filename) && $filesize = \filesize($download_filename)) {
                $first_byte_position = $filesize;
                $range = $first_byte_position . '-';
                $curl->setRange($range);
                $curl->fileHandle = \fopen($download_filename, 'ab');

                // Move the downloaded temporary file to the destination save path.
                $curl->downloadCompleteCallback = static function ($instance, $fh) use ($download_filename, $filename) {
                    // Close the open file handle before renaming the file.
                    if (\is_resource($fh)) {
                        \fclose($fh);
                    }

                    \rename($download_filename, $filename);
                };
            } else {
                $curl->fileHandle = \fopen('php://temp', 'wb');
                $curl->downloadCompleteCallback = static function ($instance, $fh) use ($filename) {
                    \file_put_contents($filename, \stream_get_contents($fh));
                };
            }
        }

        if ($curl->fileHandle === false) {
            throw new \Httpful\Exception\ClientErrorException('Unable to write to file:' . $curl->downloadFileName);
        }

        $curl->setFile($curl->fileHandle);
        $curl->setOpt(\CURLOPT_CUSTOMREQUEST, 'GET');
        $curl->setOpt(\CURLOPT_HTTPGET, true);

        return $curl;
    }

    /**
     * @param int $concurrency
     *
     * @return $this
     */
    public function setConcurrency($concurrency)
    {
        $this->concurrency = $concurrency;

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
        $this->cookies[$key] = $value;

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
            $this->cookies[$key] = $value;
        }

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
     * @param callable|int $mixed
     *
     * @return $this
     */
    public function setRetry($mixed)
    {
        $this->retry = $mixed;

        return $this;
    }

    /**
     * @return $this|null
     */
    public function start()
    {
        if ($this->isStarted) {
            return null;
        }

        $this->isStarted = true;

        $concurrency = $this->concurrency;
        if ($concurrency > \count($this->curls)) {
            $concurrency = \count($this->curls);
        }

        for ($i = 0; $i < $concurrency; ++$i) {
            $curlOrNull = \array_shift($this->curls);
            if ($curlOrNull !== null) {
                $this->initHandle($curlOrNull);
            }
        }

        $active = null;
        do {
            // Wait for activity on any curl_multi connection when curl_multi_select (libcurl) fails to correctly block.
            // https://bugs.php.net/bug.php?id=63411
            if ($active && \curl_multi_select($this->multiCurl) === -1) {
                \usleep(250);
            }

            \curl_multi_exec($this->multiCurl, $active);

            while (!(($info_array = \curl_multi_info_read($this->multiCurl)) === false)) {
                if ($info_array['msg'] === \CURLMSG_DONE) {
                    foreach ($this->activeCurls as $key => $curl) {
                        $curlRes = $curl->getCurl();
                        if ($curlRes === false) {
                            continue;
                        }

                        if ($curlRes === $info_array['handle']) {
                            // Set the error code for multi handles using the "result" key in the array returned by
                            // curl_multi_info_read(). Using curl_errno() on a multi handle will incorrectly return 0
                            // for errors.
                            $curl->curlErrorCode = $info_array['result'];
                            $curl->exec($curlRes);

                            if ($curl->attemptRetry()) {
                                // Remove completed handle before adding again in order to retry request.
                                \curl_multi_remove_handle($this->multiCurl, $curlRes);

                                $curlm_error_code = \curl_multi_add_handle($this->multiCurl, $curlRes);
                                if ($curlm_error_code !== \CURLM_OK) {
                                    throw new \ErrorException(
                                        'cURL multi add handle error: ' . \curl_multi_strerror($curlm_error_code)
                                    );
                                }
                            } else {
                                $curl->execDone();

                                // Remove completed instance from active curls.
                                unset($this->activeCurls[$key]);

                                // Start new requests before removing the handle of the completed one.
                                while (\count($this->curls) >= 1 && \count($this->activeCurls) < $this->concurrency) {
                                    $curlOrNull = \array_shift($this->curls);
                                    if ($curlOrNull !== null) {
                                        $this->initHandle($curlOrNull);
                                    }
                                }
                                \curl_multi_remove_handle($this->multiCurl, $curlRes);

                                // Clean up completed instance.
                                $curl->close();
                            }

                            break;
                        }
                    }
                }
            }

            if (!$active) {
                $active = \count($this->activeCurls);
            }
        } while ($active > 0);

        $this->isStarted = false;

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
     * @return false|resource
     */
    public function getMultiCurl()
    {
        return $this->multiCurl;
    }

    /**
     * @param Curl $curl
     *
     * @throws \ErrorException
     *
     * @return void
     */
    private function initHandle($curl)
    {
        // Set callbacks if not already individually set.

        if ($curl->beforeSendCallback === null) {
            $curl->beforeSend($this->beforeSendCallback);
        }

        if ($curl->successCallback === null) {
            $curl->success($this->successCallback);
        }

        if ($curl->errorCallback === null) {
            $curl->error($this->errorCallback);
        }

        if ($curl->completeCallback === null) {
            $curl->complete($this->completeCallback);
        }

        $curl->setRetry($this->retry);
        $curl->setCookies($this->cookies);

        $curlRes = $curl->getCurl();
        if ($curlRes === false) {
            throw new \ErrorException('cURL multi add handle error from curl: curl === false');
        }

        $curlm_error_code = \curl_multi_add_handle($this->multiCurl, $curlRes);
        if ($curlm_error_code !== \CURLM_OK) {
            throw new \ErrorException('cURL multi add handle error: ' . \curl_multi_strerror($curlm_error_code));
        }

        $this->activeCurls[$curl->getId()] = $curl;
        $curl->call($curl->beforeSendCallback);
    }

    /**
     * @param Curl $curl
     *
     * @return void
     */
    private function queueHandle($curl)
    {
        // Use sequential ids to allow for ordered post processing.
        ++$this->nextCurlId;
        $curl->setId($this->nextCurlId);
        $curl->setChildOfMultiCurl(true);
        $this->curls[$this->nextCurlId] = $curl;
    }
}
