<?php

declare(strict_types=1);

/**
 * Handlers are used to parse and serialize payloads for specific
 * mime types.  You can register a custom handler via the register
 * method.  You can also override a default parser in this way.
 */
namespace Httpful\Handlers;

use voku\helper\UTF8;

/**
 * Class MimeHandlerAdapter
 */
class MimeHandlerAdapter implements MimeHandlerAdapterInterface
{
    /**
     * MimeHandlerAdapter constructor.
     *
     * @param array $args
     */
    public function __construct(array $args = [])
    {
        $this->init($args);
    }

    /**
     * @param array $args
     */
    public function init(array $args)
    {
    }

    /**
     * @param string $body
     *
     * @return mixed
     */
    public function parse($body)
    {
        return $body;
    }

    /**
     * @param mixed $payload
     *
     * @return mixed
     */
    public function serialize($payload)
    {
        return $payload;
    }

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
