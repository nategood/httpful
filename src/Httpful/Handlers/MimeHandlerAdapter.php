<?php

/**
 * Handlers are used to parse and serialize payloads for specific 
 * mime types.  You can register a custom handler via the register
 * method.  You can also override a default parser in this way.
 */

namespace Httpful\Handlers;

class MimeHandlerAdapter
{
    /**
     * @param string $body
     * @return mixed
     */
    public function parse($body)
    {
        return $body;
    }

    /**
     * @param mixed $payload
     * @return string
     */
    function serialize($payload)
    {
        return (string) $payload;
    }
}