<?php

declare(strict_types=1);

namespace Httpful\Handlers;

use voku\helper\HtmlDomParser;

/**
 * Mime Type: text/html
 */
class HtmlMimeHandler extends DefaultMimeHandler
{
    /**
     * @param string $body
     *
     * @return HtmlDomParser|null
     */
    public function parse($body)
    {
        $body = $this->stripBom($body);
        if (empty($body)) {
            return null;
        }

        if (\voku\helper\UTF8::is_utf8($body) === false) {
            $body = \voku\helper\UTF8::to_utf8($body);
        }

        return HtmlDomParser::str_get_html($body);
    }

    /**
     * @param mixed $payload
     *
     * @return string
     */
    public function serialize($payload)
    {
        return (string) $payload;
    }
}
