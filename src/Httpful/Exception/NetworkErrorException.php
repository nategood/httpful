<?php

declare(strict_types=1);

namespace Httpful\Exception;

use Httpful\Request;
use Psr\Http\Message\RequestInterface;

class NetworkErrorException extends \Exception implements \Psr\Http\Client\NetworkExceptionInterface
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
     * @var RequestInterface|null
     */
    private $request;

    /**
     * ConnectionErrorException constructor.
     *
     * @param string                  $message
     * @param int                     $code
     * @param \Exception|null         $previous
     * @param \Httpful\Curl\Curl|null $curl_object
     * @param RequestInterface|null   $request
     */
    public function __construct(
        $message,
        $code = 0,
        \Exception $previous = null,
        \Httpful\Curl\Curl $curl_object = null,
        RequestInterface $request = null
    ) {
        $this->curl_object = $curl_object;
        $this->request = $request;

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
     * Returns the request.
     *
     * The request object MAY be a different object from the one passed to ClientInterface::sendRequest()
     *
     * @return RequestInterface
     */
    public function getRequest(): RequestInterface
    {
        return $this->request ?? new Request();
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
