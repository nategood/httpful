<?php

namespace Httpful;

use Httpful\Handlers\MimeHandlerAdapter;

/**
 * Class Httpful
 *
 * @package Httpful
 */
class Httpful
{
  const VERSION = '0.2.27';

  /**
   * @var array
   */
  private static $mimeRegistrar = array();

  /**
   * @var mixed
   */
  private static $default = null;

  /**
   * @param string                               $mimeType
   * @param \Httpful\Handlers\MimeHandlerAdapter $handler
   */
  public static function register($mimeType, MimeHandlerAdapter $handler)
  {
    self::$mimeRegistrar[$mimeType] = $handler;
  }

  /**
   * @param string $mimeType defaults to MimeHandlerAdapter
   *
   * @return MimeHandlerAdapter
   */
  public static function get($mimeType = null)
  {
    if (isset(self::$mimeRegistrar[$mimeType])) {
      return self::$mimeRegistrar[$mimeType];
    }

    if (empty(self::$default)) {
      self::$default = new MimeHandlerAdapter();
    }

    return self::$default;
  }

  /**
   * Does this particular Mime Type have a parser registered
   * for it?
   *
   * @param string $mimeType
   *
   * @return bool
   */
  public static function hasParserRegistered($mimeType)
  {
    return isset(self::$mimeRegistrar[$mimeType]);
  }
}
