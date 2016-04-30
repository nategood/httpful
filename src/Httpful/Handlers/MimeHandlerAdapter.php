<?php

/**
 * Handlers are used to parse and serialize payloads for specific
 * mime types.  You can register a custom handler via the register
 * method.  You can also override a default parser in this way.
 */

namespace Httpful\Handlers;

use voku\helper\UTF8;

/**
 * Class MimeHandlerAdapter
 *
 * @package Httpful\Handlers
 */
class MimeHandlerAdapter
{
  /**
   * MimeHandlerAdapter constructor.
   *
   * @param array $args
   */
  public function __construct(array $args = array())
  {
    $this->init($args);
  }

  /**
   * Initial setup of
   *
   * @param array $args
   */
  public function init(array $args)
  {
  }

  /**
   * @param string $body
   *
   * @return mixed
   */
  public function parse($body)
  {
    return $body;
  }

  /**
   * @param mixed $payload
   *
   * @return string
   */
  public function serialize($payload)
  {
    return (string)$payload;
  }

  /**
   * @param $body
   *
   * @return string
   */
  protected function stripBom($body)
  {
    return UTF8::removeBOM($body);
  }
}
