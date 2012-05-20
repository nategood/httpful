<?php
/**
 * Mime Type: application/json
 * @author Nathan Good <me@nategood.com>
 */

namespace Httpful\Handlers;

class JsonHandler extends MimeHandlerAdapter
{
    /**
     * @param string $body
     * @return mixed
     */
    public function parse($body)
    {
        if (empty($body))
            return null;
        $parsed = json_decode($body, false);
        if (is_null($parsed))
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