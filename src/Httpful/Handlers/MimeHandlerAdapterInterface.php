<?php declare(strict_types=1);

namespace Httpful\Handlers;

/**
 * Class MimeHandlerAdapter
 */
interface MimeHandlerAdapterInterface
{
    /**
     * @param array $args
     */
    public function init(array $args);

    /**
     * @param string $body
     *
     * @return mixed
     */
    public function parse($body);

    /**
     * @param mixed $payload
     *
     * @return mixed
     */
    public function serialize($payload);
}
