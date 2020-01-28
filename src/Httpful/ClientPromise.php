<?php

declare(strict_types=1);

namespace Httpful;

use Httpful\Curl\MultiCurlPromise;
use Psr\Http\Message\RequestInterface;

class ClientPromise extends ClientMulti implements \Http\Client\HttpAsyncClient
{
    /**
     * @noinspection MagicMethodsValidityInspection
     * @noinspection PhpMissingParentConstructorInspection
     */
    public function __construct()
    {
        $this->curlMulti = (new Request())->initMulti();
    }

    /**
     * @return MultiCurlPromise
     */
    public function getPromise(): MultiCurlPromise
    {
        return new MultiCurlPromise($this->curlMulti);
    }

    /**
     * Sends a PSR-7 request in an asynchronous way.
     *
     * Exceptions related to processing the request are available from the returned Promise.
     *
     * @param RequestInterface $request
     *
     * @return \Http\Promise\Promise resolves a PSR-7 Response or fails with an Http\Client\Exception
     */
    public function sendAsyncRequest(RequestInterface $request)
    {
        $this->add_request($request);

        return $this->getPromise();
    }
}
