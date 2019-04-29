<?php declare(strict_types=1);

namespace Httpful\Handlers;

interface MimeHandlerInterface
{
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
