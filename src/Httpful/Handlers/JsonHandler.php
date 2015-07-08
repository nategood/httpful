<?php
/**
 * Mime Type: application/json
 * @author Nathan Good <me@nategood.com>
 */

namespace Httpful\Handlers;

class JsonHandler extends MimeHandlerAdapter
{
    private $decode_as_array = false;
    private $depth = 512;
    private $parse_options = 0;

    public function init(array $args)
    {
        $this->decode_as_array = !!(array_key_exists('decode_as_array', $args) ? $args['decode_as_array'] : false);
        $this->depth = (array_key_exists('depth', $args) ? $args['depth'] : 512);
        $this->parse_options = (array_key_exists('parse_options', $args) ? $args['parse_options'] : 0);
    }

    /**
     * @param string $body
     * @return mixed
     */
    public function parse($body)
    {
        $body = $this->stripBom($body);
        if (empty($body))
            return null;
        $parsed = json_decode($body, $this->decode_as_array, $this->depth, $this->parse_options);
        if (is_null($parsed) && 'null' !== strtolower($body))
            throw new \Exception("Unable to parse response as JSON");
        return $parsed;
    }

    /**
     * @param mixed $payload
     * @return string
     */
    public function serialize($payload)
    {
        return json_encode($payload);
    }
}
