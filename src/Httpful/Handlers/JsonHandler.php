<?php
/**
 * Mime Type: application/json
 *
 * @author Nathan Good <me@nategood.com>
 */

namespace Httpful\Handlers;

/**
 * Class JsonHandler
 *
 * @package Httpful\Handlers
 */
class JsonHandler extends MimeHandlerAdapter
{
  private $decode_as_array = false;

  /**
   * @param array $args
   */
  public function init(array $args)
  {
    $this->decode_as_array = !!(array_key_exists('decode_as_array', $args) ? $args['decode_as_array'] : false);
  }

  /**
   * @param string $body
   *
   * @return mixed
   * @throws \Exception
   */
  public function parse($body)
  {
    $body = $this->stripBom($body);
    if (empty($body)) {
      return null;
    }

    $parsed = json_decode($body, $this->decode_as_array);
    if (null === $parsed && 'null' !== strtolower($body)) {
      throw new \Exception('Unable to parse response as JSON');
    }

    return $parsed;
  }

  /**
   * @param mixed $payload
   *
   * @return string
   */
  public function serialize($payload)
  {
    return json_encode($payload);
  }
}
