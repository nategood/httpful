<?php

namespace Httpful\Exception;

/**
 * Class ConnectionErrorException
 *
 * @package Httpful\Exception
 */
class ConnectionErrorException extends \Exception
{
  /**
   * @var null|resource
   */
  public $curl_object = null;

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
   * @param string         $message
   * @param int            $code
   * @param \Exception|null $previous
   * @param null           $curl_object
   */
  public function __construct($message, $code = 0, \Exception $previous = null, $curl_object = null)
  {
    $this->curl_object = $curl_object;

    parent::__construct($message, $code, $previous);
  }

  /**
   * @return null|resource
   */
  public function getCurlObject()
  {
    return $this->curl_object;
  }

  /**
   * @return string
   */
  public function getCurlErrorNumber()
  {
    return $this->curlErrorNumber;
  }

  /**
   * @param string $curlErrorNumber
   *
   * @return $this
   */
  public function setCurlErrorNumber($curlErrorNumber)
  {
    $this->curlErrorNumber = $curlErrorNumber;

    return $this;
  }

  /**
   * @return string
   */
  public function getCurlErrorString()
  {
    return $this->curlErrorString;
  }

  /**
   * @param string $curlErrorString
   *
   * @return $this
   */
  public function setCurlErrorString($curlErrorString)
  {
    $this->curlErrorString = $curlErrorString;

    return $this;
  }

  /**
   * @return bool
   */
  public function wasTimeout()
  {
    return $this->code === CURLE_OPERATION_TIMEOUTED;
  }
}
