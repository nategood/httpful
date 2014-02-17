<?php

namespace Httpful\Exception;

class ConnectionErrorException extends \Exception
{
    public $curl_object = null;
    public function __construct($message, $code = 0, Exception $previous = null, $curl_object = null) {
        $this->curl_object = $curl_object;
        parent::__construct($message, $code, $previous);
    }

    public function getCurlObject() {
        return $this->curl_object;
    }

    public function wasTimeout() {
        return $this->code == CURLE_OPERATION_TIMEOUTED;
    }
}
