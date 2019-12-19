<?php

declare(strict_types=1);

namespace Httpful\Handlers;

use voku\helper\UTF8;

/**
 * Handlers are used to parse and serialize payloads for specific
 * mime types.  You can register a custom handler via the register
 * method.  You can also override a default parser in this way.
 */
abstract class AbstractMimeHandler implements MimeHandlerInterface
{
    /**
     * @param string $body
     *
     * @return string
     */
    protected function stripBom($body): string
    {
        return UTF8::remove_bom($body);
    }
}
