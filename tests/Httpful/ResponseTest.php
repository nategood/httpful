<?php

declare(strict_types=1);

namespace Httpful\tests;

use Httpful\Factory;
use Httpful\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

/**
 * @internal
 */
final class ResponseTest extends TestCase
{
    public function testDefaultConstructor()
    {
        $r = new Response();
        static::assertSame(200, $r->getStatusCode());
        static::assertSame('1.1', $r->getProtocolVersion());
        static::assertSame('OK', $r->getReasonPhrase());
        static::assertSame([], $r->getHeaders());
        static::assertInstanceOf(StreamInterface::class, $r->getBody());
        static::assertSame('', (string) $r->getBody());
    }

    public function testCanConstructWithStatusCode()
    {
        $r = (new Response())->withStatus(404);
        static::assertSame(404, $r->getStatusCode());
        static::assertSame('Not Found', $r->getReasonPhrase());
    }

    public function testCanConstructWithUndefinedStatusCode()
    {
        $r = (new Response())->withStatus(999);
        static::assertSame(999, $r->getStatusCode());
        static::assertSame('', $r->getReasonPhrase());
    }

    public function testConstructorDoesNotReadStreamBody()
    {
        $body = $this->getMockBuilder(StreamInterface::class)->getMock();
        $body->expects(static::never())
            ->method('__toString');

        $r = (new Response())->withBody($body);
        static::assertSame($body, $r->getBody());
    }

    public function testStatusCanBeNumericString()
    {
        $r = (new Response())->withStatus(404);
        $r2 = $r->withStatus('201');
        static::assertSame(404, $r->getStatusCode());
        static::assertSame('Not Found', $r->getReasonPhrase());
        static::assertSame(201, $r2->getStatusCode());
        static::assertSame('Created', $r2->getReasonPhrase());
    }

    public function testCanConstructWithHeaders()
    {
        $r = (new Response())->withHeaders(['Foo' => 'Bar']);
        static::assertSame(['Foo' => ['Bar']], $r->getHeaders());
        static::assertSame('Bar', $r->getHeaderLine('Foo'));
        static::assertSame(['Bar'], $r->getHeader('Foo'));
    }

    public function testCanConstructWithHeadersAsArray()
    {
        $r = new Response('', ['Foo' => ['baz', 'bar']]);
        static::assertSame(['Foo' => ['baz', 'bar']], $r->getHeaders());
        static::assertSame('baz, bar', $r->getHeaderLine('Foo'));
        static::assertSame(['baz', 'bar'], $r->getHeader('Foo'));
    }

    public function testCanConstructWithBody()
    {
        $r = new Response('baz');
        static::assertInstanceOf(StreamInterface::class, $r->getBody());
        static::assertSame('baz', (string) $r->getBody());
    }

    public function testNullBody()
    {
        $r = new Response(null);
        static::assertInstanceOf(StreamInterface::class, $r->getBody());
        static::assertSame('', (string) $r->getBody());
    }

    public function testFalseyBody()
    {
        $r = new Response('0');
        static::assertInstanceOf(StreamInterface::class, $r->getBody());
        static::assertSame('0', (string) $r->getBody());
    }

    public function testCanConstructWithReason()
    {
        $r = (new Response())->withStatus(200, 'bar');
        static::assertSame('bar', $r->getReasonPhrase());

        $r = (new Response())->withStatus(200, '0');
        static::assertSame('0', $r->getReasonPhrase(), 'Falsey reason works');
    }

    public function testCanConstructWithProtocolVersion()
    {
        $r = (new Response())->withProtocolVersion('1000');
        static::assertSame('1000', $r->getProtocolVersion());
    }

    public function testWithStatusCodeAndNoReason()
    {
        $r = (new Response())->withStatus(201);
        static::assertSame(201, $r->getStatusCode());
        static::assertSame('Created', $r->getReasonPhrase());
    }

    public function testWithStatusCodeAndReason()
    {
        $r = (new Response())->withStatus(201, 'Foo');
        static::assertSame(201, $r->getStatusCode());
        static::assertSame('Foo', $r->getReasonPhrase());

        $r = (new Response())->withStatus(201, '0');
        static::assertSame(201, $r->getStatusCode());
        static::assertSame('0', $r->getReasonPhrase(), 'Falsey reason works');
    }

    public function testWithProtocolVersion()
    {
        $r = (new Response())->withProtocolVersion('1000');
        static::assertSame('1000', $r->getProtocolVersion());
    }

    public function testSameInstanceWhenSameProtocol()
    {
        $r = new Response();
        static::assertEquals($r, $r->withProtocolVersion('1.1'));
    }

    public function testWithBody()
    {
        $b = (new Factory())->createStream('0');
        $r = (new Response())->withBody($b);
        static::assertInstanceOf(StreamInterface::class, $r->getBody());
        static::assertSame('0', (string) $r->getBody());
    }

    public function testSameInstanceWhenSameBody()
    {
        $r = new Response();
        $b = $r->getBody();
        static::assertEquals($r, $r->withBody($b));
    }

    public function testWithHeader()
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        $r2 = $r->withHeader('baZ', 'Bam');
        static::assertSame(['Foo' => ['Bar']], $r->getHeaders());
        static::assertSame(['Foo' => ['Bar'], 'baZ' => ['Bam']], $r2->getHeaders());
        static::assertSame('Bam', $r2->getHeaderLine('baz'));
        static::assertSame(['Bam'], $r2->getHeader('baz'));
    }

    public function testWithHeaderAsArray()
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        $r2 = $r->withHeader('baZ', ['Bam', 'Bar']);
        static::assertSame(['Foo' => ['Bar']], $r->getHeaders());
        static::assertSame(['Foo' => ['Bar'], 'baZ' => ['Bam', 'Bar']], $r2->getHeaders());
        static::assertSame('Bam, Bar', $r2->getHeaderLine('baz'));
        static::assertSame(['Bam', 'Bar'], $r2->getHeader('baz'));
    }

    public function testWithHeaderReplacesDifferentCase()
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        $r2 = $r->withHeader('foO', 'Bam');
        static::assertSame(['Foo' => ['Bar']], $r->getHeaders());
        static::assertSame(['foO' => ['Bam']], $r2->getHeaders());
        static::assertSame('Bam', $r2->getHeaderLine('foo'));
        static::assertSame(['Bam'], $r2->getHeader('foo'));
    }

    public function testWithAddedHeader()
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        $r2 = $r->withAddedHeader('foO', 'Baz');
        static::assertSame(['Foo' => ['Bar']], $r->getHeaders());
        static::assertSame(['foO' => ['Bar', 'Baz']], $r2->getHeaders());
        static::assertSame('Bar, Baz', $r2->getHeaderLine('foo'));
        static::assertSame(['Bar', 'Baz'], $r2->getHeader('foo'));
    }

    public function testWithAddedHeaderAsArray()
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        $r2 = $r->withAddedHeader('foO', ['Baz', 'Bam']);
        static::assertSame(['Foo' => ['Bar']], $r->getHeaders());
        static::assertSame(['foO' => ['Bar', 'Baz', 'Bam']], $r2->getHeaders());
        static::assertSame('Bar, Baz, Bam', $r2->getHeaderLine('foo'));
        static::assertSame(['Bar', 'Baz', 'Bam'], $r2->getHeader('foo'));
    }

    public function testWithAddedHeaderThatDoesNotExist()
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        $r2 = $r->withAddedHeader('nEw', 'Baz');
        static::assertSame(['Foo' => ['Bar']], $r->getHeaders());
        static::assertSame(['Foo' => ['Bar'], 'nEw' => ['Baz']], $r2->getHeaders());
        static::assertSame('Baz', $r2->getHeaderLine('new'));
        static::assertSame(['Baz'], $r2->getHeader('new'));
    }

    public function testWithoutHeaderThatExists()
    {
        $r = new Response(200, ['Foo' => 'Bar', 'Baz' => 'Bam']);
        $r2 = $r->withoutHeader('foO');
        static::assertTrue($r->hasHeader('foo'));
        static::assertSame(['Foo' => ['Bar'], 'Baz' => ['Bam']], $r->getHeaders());
        static::assertFalse($r2->hasHeader('foo'));
        static::assertSame(['Baz' => ['Bam']], $r2->getHeaders());
    }

    public function testWithoutHeaderThatDoesNotExist()
    {
        $r = new Response(200, ['Baz' => 'Bam']);
        $r2 = $r->withoutHeader('foO');
        static::assertEquals($r, $r2);
        static::assertFalse($r2->hasHeader('foo'));
        static::assertSame(['Baz' => ['Bam']], $r2->getHeaders());
    }

    public function testSameInstanceWhenRemovingMissingHeader()
    {
        $r = new Response();
        static::assertEquals($r, $r->withoutHeader('foo'));
    }

    public function trimmedHeaderValues()
    {
        return [
            [new Response(200, ['OWS' => " \t \tFoo\t \t "])],
            [(new Response())->withHeader('OWS', " \t \tFoo\t \t ")],
            [(new Response())->withAddedHeader('OWS', " \t \tFoo\t \t ")],
        ];
    }

    /**
     * @dataProvider trimmedHeaderValues
     *
     * @param mixed $r
     */
    public function testHeaderValuesAreTrimmed($r)
    {
        static::assertSame(['OWS' => ['Foo']], $r->getHeaders());
        static::assertSame('Foo', $r->getHeaderLine('OWS'));
        static::assertSame(['Foo'], $r->getHeader('OWS'));
    }
}
