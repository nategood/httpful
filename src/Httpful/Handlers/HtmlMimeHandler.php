<?php

declare(strict_types=1);

namespace Httpful\Handlers;

use voku\helper\HtmlDomParser;

/**
 * Mime Type: text/html
 */
class HtmlMimeHandler implements MimeHandlerInterface
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
