<?php

declare(strict_types=1);

/**
 * Mime Type: application/x-www-urlencoded
 */
namespace Httpful\Handlers;

/**
 * Class FormHandler
 */
class FormHandler extends DefaultHandler
{
    /**
     * @param string $body
     *
     * @return array
     */
    public function parse($body)
    {
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
