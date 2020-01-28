<?php

declare(strict_types=1);

namespace Httpful\Curl;

use Http\Promise\Promise;

/**
 * Promise represents a response that may not be available yet, but will be resolved at some point
 * in future. It acts like a proxy to the actual response.
 */
class MultiCurlPromise implements Promise
{
    /**
     * Requests runner.
     *
     * @var MultiCurl
     */
    private $clientMulti;

    /**
     * Promise state.
     *
     * @var string
     */
    private $state;

    /**
     * Create new promise.
     *
     * @param MultiCurl $clientMulti
     */
    public function __construct(MultiCurl $clientMulti)
    {
        $this->clientMulti = $clientMulti;
        $this->state = Promise::PENDING;
    }

    /**
     * Add behavior for when the promise is resolved or rejected.
     *
     * If you do not care about one of the cases, you can set the corresponding callable to null
     * The callback will be called when the response or exception arrived and never more than once.
     *
     * @param callable $onComplete Called when a response will be available
     * @param callable $onRejected Called when an error happens.
     *
     * You must always return the Response in the interface or throw an Exception
     *
     * @return Promise Always returns a new promise which is resolved with value of the executed
     *                 callback (onFulfilled / onRejected)
     */
    public function then(callable $onComplete = null, callable $onRejected = null)
    {
        if ($onComplete) {
            $this->clientMulti->complete(
                static function (Curl $instance) use ($onComplete) {
                    if ($instance->request instanceof \Httpful\Request) {
                        $response = $instance->request->_buildResponse($instance->rawResponse, $instance);
                    } else {
                        $response = $instance->rawResponse;
                    }

                    $onComplete(
                        $response,
                        $instance->request,
                        $instance
                    );
                }
            );
        }

        if ($onRejected) {
            $this->clientMulti->error(
                static function (Curl $instance) use ($onRejected) {
                    if ($instance->request instanceof \Httpful\Request) {
                        $response = $instance->request->_buildResponse($instance->rawResponse, $instance);
                    } else {
                        $response = $instance->rawResponse;
                    }

                    $onRejected(
                        $response,
                        $instance->request,
                        $instance
                    );
                }
            );
        }

        return new self($this->clientMulti);
    }

    /**
     * Get the state of the promise, one of PENDING, FULFILLED or REJECTED.
     *
     * @return string
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Wait for the promise to be fulfilled or rejected.
     *
     * When this method returns, the request has been resolved and the appropriate callable has terminated.
     *
     * When called with the unwrap option
     *
     * @param bool $unwrap Whether to return resolved value / throw reason or not
     *
     * @return MultiCurl|null Resolved value, null if $unwrap is set to false
     */
    public function wait($unwrap = true)
    {
        if ($unwrap) {
            $this->clientMulti->start();
            $this->state = Promise::FULFILLED;

            return $this->clientMulti;
        }

        try {
            $this->clientMulti->start();
            $this->state = Promise::FULFILLED;
        } catch (\ErrorException $e) {
            $this->_error((string) $e);
        }

        return null;
    }

    /**
     * @param string $error
     *
     * @return void
     */
    private function _error($error)
    {
        $this->state = Promise::REJECTED;

        // global error handling

        $global_error_handler = \Httpful\Setup::getGlobalErrorHandler();
        if ($global_error_handler) {
            if ($global_error_handler instanceof \Psr\Log\LoggerInterface) {
                // PSR-3 https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md
                $global_error_handler->error($error);
            } elseif (\is_callable($global_error_handler)) {
                // error callback
                /** @noinspection VariableFunctionsUsageInspection */
                \call_user_func($global_error_handler, $error);
            }
        }

        // local error handling

        /** @noinspection ForgottenDebugOutputInspection */
        \error_log($error);
    }
}
