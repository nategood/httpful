<?php

declare(strict_types=1);

/**
 * Mime Type: text/html
 * Mime Type: application/html+xml
 */
namespace Httpful\Handlers;

use voku\helper\HtmlDomParser;

/**
 * Class HtmlHandler
 */
class HtmlHandler extends DefaultHandler
{
    /**
     * @param string $body
     *
     * @return mixed
     */
    public function parse($body)
    {
        return HtmlDomParser::str_get_html($body);
    }

    /**
     * @param mixed $payload
     *
     * @return false|string
     */
    public function serialize($payload)
    {
        return (string) HtmlDomParser::str_get_html($payload);
    }
}
