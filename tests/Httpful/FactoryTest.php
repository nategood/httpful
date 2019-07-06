<?php

declare(strict_types=1);

namespace Httpful\tests;

use Httpful\Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * @internal
 */
final class FactoryTest extends TestCase
{
    public function testCreateRequest()
    {
        $factory = new Factory();
        $r = $factory->createRequest('POST', 'https://nyholm.tech');

        static::assertEquals('POST', $r->getMethod());
        static::assertEquals('https://nyholm.tech', $r->getUri()->__toString());

        $headers = $r->getHeaders();
        static::assertCount(1, $headers); // Including HOST
    }

    public function testCreateResponse()
    {
        $factory = new Factory();
        $usual = $factory->createResponse(404);
        static::assertEquals(404, $usual->getStatusCode());
        static::assertEquals('Not Found', $usual->getReasonPhrase());

        $r = $factory->createResponse(217, 'Perfect');

        static::assertEquals(217, $r->getStatusCode());
        static::assertEquals('Perfect', $r->getReasonPhrase());
    }

    public function testCreateStream()
    {
        $factory = new Factory();
        $stream = $factory->createStream('foobar');

        static::assertInstanceOf(StreamInterface::class, $stream);
        static::assertEquals('foobar', $stream->__toString());
    }

    public function testCreateUri()
    {
        $factory = new Factory();
        $uri = $factory->createUri('https://nyholm.tech/foo');

        static::assertInstanceOf(UriInterface::class, $uri);
        static::assertEquals('https://nyholm.tech/foo', $uri->__toString());
    }
}
