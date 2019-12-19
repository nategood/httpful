<?php

declare(strict_types=1);

namespace Httpful\Exception;

use Psr\Http\Message\RequestInterface;
use Throwable;

class RequestException extends \Exception implements \Psr\Http\Client\RequestExceptionInterface
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * RequestException constructor.
     *
     * @param string           $message
     * @param int              $code
     * @param Throwable|null   $previous
     * @param RequestInterface $request
     */
    public function __construct(RequestInterface $request, $message = '', $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->request = $request;
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
        return $this->request;
    }
}
