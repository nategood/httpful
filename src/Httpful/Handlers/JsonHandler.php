<?php

declare(strict_types=1);

/**
 * Mime Type: application/json
 */
namespace Httpful\Handlers;

use Httpful\Exception\JsonParseException;

/**
 * Class JsonHandler
 */
class JsonHandler extends DefaultHandler
{
    /**
     * @var bool
     */
    private $decode_as_array = false;

    /**
     * @param array $args
     */
    public function init(array $args)
    {
        if (\array_key_exists('decode_as_array', $args)) {
            $this->decode_as_array = (bool) ($args['decode_as_array']);
        } else {
            $this->decode_as_array = false;
        }
    }

    /**
     * @param string $body
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public function parse($body)
    {
        $body = $this->stripBom($body);
        if (empty($body)) {
            return null;
        }

        $parsed = \json_decode($body, $this->decode_as_array);
        if ($parsed === null && \strtolower($body) !== 'null') {
            throw new JsonParseException('Unable to parse response as JSON: ' . \json_last_error_msg() . ' | "' . \print_r($body, true) . '"');
        }

        return $parsed;
    }

    /**
     * @param mixed $payload
     *
     * @return false|string
     */
    public function serialize($payload)
    {
        return \json_encode($payload);
    }
}
