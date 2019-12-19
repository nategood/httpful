<?php

declare(strict_types=1);

namespace Httpful\Handlers;

use voku\helper\UTF8;

/**
 * Mime Type: application/x-www-urlencoded
 */
class FormMimeHandler implements MimeHandlerInterface
{
    /**
     * @param string $body
     *
     * @return array
     */
    public function parse($body)
    {
        // special: form-data with json response
        if (UTF8::is_json($body)) {
            return \json_decode($body, true);
        }

        // init
        $parsed = [];

        \parse_str($body, $parsed);

        return $parsed;
    }

    /**
     * @param mixed $payload
     *
     * @return string
     */
    public function serialize($payload)
    {
        return \http_build_query($payload, '', '&');
    }
}
