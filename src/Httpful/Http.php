<?php

namespace Httpful;

/**
 * @author Nate Good <me@nategood.com>
 */
class Http
{
  const HEAD    = 'HEAD';
  const GET     = 'GET';
  const POST    = 'POST';
  const PUT     = 'PUT';
  const DELETE  = 'DELETE';
  const PATCH   = 'PATCH';
  const OPTIONS = 'OPTIONS';
  const TRACE   = 'TRACE';

  /**
   * @return array of HTTP method strings
   */
  public static function safeMethods()
  {
    return array(self::HEAD, self::GET, self::OPTIONS, self::TRACE);
  }

  /**
   * @param string HTTP method
   *
   * @return bool
   */
  public static function isSafeMethod($method)
  {
    return in_array($method, self::safeMethods(), true);
  }

  /**
   * @param string HTTP method
   *
   * @return bool
   */
  public static function isUnsafeMethod($method)
  {
    return !in_array($method, self::safeMethods(), true);
  }

  /**
   * @return array list of (always) idempotent HTTP methods
   */
  public static function idempotentMethods()
  {
    // Though it is possible to be idempotent, POST
    // is not guarunteed to be, and more often than
    // not, it is not.
    return array(self::HEAD, self::GET, self::PUT, self::DELETE, self::OPTIONS, self::TRACE, self::PATCH);
  }

  /**
   * @param string HTTP method
   *
   * @return bool
   */
  public static function isIdempotent($method)
  {
    return in_array($method, self::idempotentMethods(), true);
  }

  /**
   * @param string HTTP method
   *
   * @return bool
   */
  public static function isNotIdempotent($method)
  {
    return !in_array($method, self::idempotentMethods(), true);
  }

  /**
   * @deprecated Technically anything *can* have a body,
   * they just don't have semantic meaning.  So say's Roy
   * http://tech.groups.yahoo.com/group/rest-discuss/message/9962
   *
   * @return array of HTTP method strings
   */
  public static function canHaveBody()
  {
    return array(self::POST, self::PUT, self::PATCH, self::OPTIONS);
  }

  /**
   * @param $code
   *
   * @return string
   *
   * @throws \Exception
   */
  public static function reason($code)
  {
    $code = (int)$code;
    $codes = self::responseCodes();

    if (!array_key_exists($code, $codes)) {
      throw new \Exception('Unable to parse response code from HTTP response due to malformed response. Code: ' . $code);
    }

    return $codes[$code];
  }

  /**
   * get all response-codes
   *
   * @return array
   */
  protected static function responseCodes()
  {
    return array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        449 => 'Retry With',
        450 => 'Blocked by Windows Parental Controls',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        509 => 'Bandwidth Limit Exceeded',
        510 => 'Not Extended',
    );
  }

}
