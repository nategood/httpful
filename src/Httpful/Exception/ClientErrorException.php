<?php

declare(strict_types=1);

namespace Httpful\Exception;

class ClientErrorException extends \Exception implements \Psr\Http\Client\ClientExceptionInterface
{
    /**
     * @var \Httpful\Curl\Curl|null
     */
    private $curl_object;

    /**
     * @var int
     */
    private $curlErrorNumber;

    /**
     * @var string
     */
    private $curlErrorString;

    /**
     * ConnectionErrorException constructor.
     *
     * @param string                  $message
     * @param int                     $code
     * @param \Exception|null         $previous
     * @param \Httpful\Curl\Curl|null $curl_object
     */
    public function __construct($message, $code = 0, \Exception $previous = null, $curl_object = null)
    {
        $this->curl_object = $curl_object;

        parent::__construct($message, $code, $previous);
    }

    /**
     * @return int
     */
    public function getCurlErrorNumber(): int
    {
        return $this->curlErrorNumber;
    }

    /**
     * @return string
     */
    public function getCurlErrorString(): string
    {
        return $this->curlErrorString;
    }

    /**
     * @return \Httpful\Curl\Curl|null
     */
    public function getCurlObject()
    {
        return $this->curl_object;
    }

    /**
     * @param int $curlErrorNumber
     *
     * @return static
     */
    public function setCurlErrorNumber($curlErrorNumber)
    {
        $this->curlErrorNumber = $curlErrorNumber;

        return $this;
    }

    /**
     * @param string $curlErrorString
     *
     * @return static
     */
    public function setCurlErrorString($curlErrorString)
    {
        $this->curlErrorString = $curlErrorString;

        return $this;
    }

    /**
     * @return bool
     */
    public function wasTimeout(): bool
    {
        return $this->code === \CURLE_OPERATION_TIMEOUTED;
    }
}
