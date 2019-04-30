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
     * @return \voku\helper\HtmlDomParser|null
     */
    public function parse($body)
    {
        $body = $this->stripBom($body);
        if (empty($body)) {
            return null;
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
