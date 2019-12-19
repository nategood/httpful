<?php

declare(strict_types=1);

/**
 * Handlers are used to parse and serialize payloads for specific
 * mime types.  You can register a custom handler via the register
 * method.  You can also override a default parser in this way.
 */
namespace Httpful\Handlers;

class DefaultMimeHandler extends AbstractMimeHandler
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
     *
     * @return void
     */
    public function init(array $args)
    {
    }

    /**
     * @param mixed $body
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
        if (
            \is_array($payload)
            ||
            $payload instanceof \Serializable
        ) {
            $payload = \serialize($payload);
        }

        return $payload;
    }
}
