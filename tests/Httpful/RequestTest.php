<?php

declare(strict_types=1);

namespace Httpful\tests;

use Httpful\Request;
use Httpful\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

/**
 * @internal
 */
final class RequestTest extends TestCase
{
    public function testAddsPortToHeader()
    {
        $r = (new Request('GET'))->withUriFromString('http://foo.com:8124/bar');
        static::assertSame('foo.com:8124', $r->getHeaderLine('host'));
    }

    public function testAddsPortToHeaderAndReplacePreviousPort()
    {
        $r = new Request('GET', 'http://foo.com:8124/bar');
        $r = $r->withUri(new Uri('http://foo.com:8125/bar'));
        static::assertSame('foo.com:8125', $r->getHeaderLine('host'));
    }

    public function testAggregatesHeaders()
    {
        $r = (new Request('GET'))->withHeaders(['ZOO' => 'zoobar', 'zoo' => ['foobar', 'zoobar']]);
        static::assertSame(['zoo' => ['zoobar', 'foobar', 'zoobar']], $r->getHeaders());
        static::assertSame('zoobar, foobar, zoobar', $r->getHeaderLine('zoo'));
    }

    public function testBuildsRequestTarget()
    {
        $r1 = (new Request('GET'))->withUriFromString('http://foo.com/baz?bar=bam');
        static::assertSame('/baz?bar=bam', $r1->getRequestTarget());
    }

    public function testBuildsRequestTargetWithFalseyQuery()
    {
        $r1 = (new Request('GET'))->withUriFromString('http://foo.com/baz?0');
        static::assertSame('/baz?0', $r1->getRequestTarget());
    }

    public function testCanConstructWithBody()
    {
        $r = (new Request('GET'))->withUriFromString('/')->withBodyFromString('baz');
        static::assertInstanceOf(StreamInterface::class, $r->getBody());
        static::assertSame('a:1:{i:0;s:3:"baz";}', (string) $r->getBody());
    }

    public function testCanGetHeaderAsCsv()
    {
        $r = (new Request('GET'))->withUriFromString('http://foo.com/baz?bar=bam')->withHeader('Foo', ['a', 'b', 'c']);
        static::assertSame('a, b, c', $r->getHeaderLine('Foo'));
        static::assertSame('', $r->getHeaderLine('Bar'));
    }

    public function testCanHaveHeaderWithEmptyValue()
    {
        $r = (new Request('GET'))->withUriFromString('https://example.com/');
        $r = $r->withHeader('Foo', '');
        static::assertSame([''], $r->getHeader('Foo'));
    }

    public function testCannotHaveHeaderWithEmptyName()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Header name must be an RFC 7230 compatible string');
        $r = (new Request('GET'))->withUriFromString('https://example.com/');
        $r->withHeader('', 'Bar');
    }

    public function testFalseyBody()
    {
        $r = (new Request('GET'))->withUriFromString('/')->withBodyFromString('0');
        static::assertInstanceOf(StreamInterface::class, $r->getBody());
        static::assertSame('a:0:{}', (string) $r->getBody());
    }

    public function testCreateRequest()
    {
        $request = (new \Httpful\Factory())->createRequest(
            \Httpful\Http::POST,
            \sprintf('/api/%d/store/', 3),
            \Httpful\Mime::JSON,
            \json_encode(['foo' => 'bar'])
        );

        static::assertSame(\Httpful\Http::POST, $request->getMethod());
        static::assertSame('a:1:{i:0;s:13:"{"foo":"bar"}";}', (string) $request->getBody());
    }

    public function testGetInvalidURL()
    {
        $this->expectException(\Httpful\Exception\NetworkErrorException::class);
        $this->expectExceptionMessage('Unable to connect');

        // Silence the default logger via whenError override
        Request::get('unavailable.url')->withErrorHandler(
            static function ($error) {
            }
        )->send();
    }

    public function testGetRequestTarget()
    {
        $r = (new Request('GET'))->withUriFromString('https://nyholm.tech');
        static::assertSame('/', $r->getRequestTarget());

        $r = (new Request('GET'))->withUriFromString('https://nyholm.tech/foo?bar=baz');
        static::assertSame('/foo?bar=baz', $r->getRequestTarget());

        $r = (new Request('GET'))->withUriFromString('https://nyholm.tech?bar=baz');
        static::assertSame('/?bar=baz', $r->getRequestTarget());
    }

    public function testHostIsAddedFirst()
    {
        $r = (new Request('GET'))->withUriFromString('http://foo.com/baz?bar=bam')->withHeader('Foo', 'Bar');
        static::assertSame(
            [
                'Host' => ['foo.com'],
                'Foo'  => ['Bar'],
            ],
            $r->getHeaders()
        );
    }

    public function testHostIsNotOverwrittenWhenPreservingHost()
    {
        $r = (new Request('GET'))->withUriFromString('http://foo.com/baz?bar=bam')->withHeader('Host', 'a.com');
        static::assertSame(['Host' => ['a.com']], $r->getHeaders());
        $r2 = $r->withUri(new Uri('http://www.foo.com/bar'), true);
        static::assertSame('a.com', $r2->getHeaderLine('Host'));
    }

    public function testHostIsOverwrittenWhenPreservingHost()
    {
        $r = (new Request('GET'))->withUriFromString('http://foo.com/baz?bar=bam')->withHeader('Host', 'a.com');
        static::assertSame(['Host' => ['a.com']], $r->getHeaders());
        $r2 = $r->withUri(new Uri('http://www.foo.com/bar'), false);
        static::assertSame('www.foo.com', $r2->getHeaderLine('Host'));
    }

    public function testNullBody()
    {
        $r = (new Request('GET'))->withUriFromString('/');
        static::assertInstanceOf(StreamInterface::class, $r->getBody());
        static::assertNotNull($r->getBody());
    }

    public function testOverridesHostWithUri()
    {
        $r = (new Request('GET'))->withUriFromString('http://foo.com/baz?bar=bam');
        static::assertSame(['Host' => ['foo.com']], $r->getHeaders());
        $r2 = $r->withUri(new Uri('http://www.baz.com/bar'));
        static::assertSame('www.baz.com', $r2->getHeaderLine('Host'));
    }

    public function testRequestTargetDefaultsToSlash()
    {
        $r1 = (new Request('GET'))->withUriFromString('');
        static::assertSame('/', $r1->getRequestTarget());

        $r2 = (new Request('GET'))->withUriFromString('*');
        static::assertSame('*', $r2->getRequestTarget());

        $r3 = (new Request('GET'))->withUriFromString('http://foo.com/bar baz/');
        static::assertSame('/bar%20baz/', $r3->getRequestTarget());
    }

    public function testRequestTargetDoesNotAllowSpaces()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid request target provided; cannot contain whitespace');
        $r1 = new Request('GET', '/');
        $r1->withRequestTarget('/foo bar');
    }

    public function testRequestUriMayBeString()
    {
        $r = (new Request('GET'))->withUriFromString('/');
        static::assertSame('/', (string) $r->getUri());
    }

    public function testRequestUriMayBeUri()
    {
        $uri = new Uri('/');
        $r = (new Request('GET'))->withUri($uri);
        static::assertSame($uri, $r->getUri());
    }

    public function testSameInstanceWhenSameUri()
    {
        $r1 = (new Request('GET'))->withUriFromString('http://foo.com');
        $r2 = $r1->withUri($r1->getUri());
        static::assertEquals($r1, $r2);
    }

    public function testSupportNumericHeaders()
    {
        $r = (new Request('GET'))->withHeaders(
            [
                'Content-Length' => 200,
            ]
        );
        static::assertSame(['Content-Length' => ['200']], $r->getHeaders());
        static::assertSame('200', $r->getHeaderLine('Content-Length'));
    }

    public function testUpdateHostFromUri()
    {
        $request = new Request('GET');
        $request = $request->withUri(new Uri('https://nyholm.tech'));
        static::assertSame('nyholm.tech', $request->getHeaderLine('Host'));

        $request = (new Request('GET'))->withUriFromString('https://example.com/');
        static::assertSame('example.com', $request->getHeaderLine('Host'));

        $request = $request->withUri(new Uri('https://nyholm.tech'));
        static::assertSame('nyholm.tech', $request->getHeaderLine('Host'));

        $request = new Request('GET');
        $request = $request->withUri(new Uri('https://nyholm.tech:8080'));
        static::assertSame('nyholm.tech:8080', $request->getHeaderLine('Host'));

        $request = new Request('GET');
        $request = $request->withUri(new Uri('https://nyholm.tech:443'));
        static::assertSame('nyholm.tech', $request->getHeaderLine('Host'));
    }

    public function testValidateRequestUri()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to parse URI: ///');
        (new Request('GET'))->withUriFromString('///');
    }

    public function testWithInvalidRequestTarget()
    {
        $r = new Request('GET', '/');
        $this->expectException(\InvalidArgumentException::class);
        $r->withRequestTarget('foo bar');
    }

    public function testWithRequestTarget()
    {
        $r1 = (new Request('GET'))->withUriFromString('/');
        $r2 = $r1->withRequestTarget('*');
        static::assertSame('*', $r2->getRequestTarget());
        static::assertSame('/', $r1->getRequestTarget());
    }

    public function testWithUri()
    {
        $r1 = new Request('GET', '/');
        $u1 = $r1->getUri();
        $u2 = new Uri('http://www.example.com');
        $r2 = $r1->withUri($u2);
        static::assertNotSame($r1, $r2);
        static::assertSame($u2, $r2->getUri());
        static::assertSame($u1, $r1->getUri());

        $r3 = (new Request('GET'))->withUriFromString('/');
        $u3 = $r3->getUri();
        $r4 = $r3->withUri($u3);
        static::assertEquals($r3, $r4);
        static::assertNotSame($r3, $r4);

        $u4 = new Uri('/');
        $r5 = $r3->withUri($u4);
        static::assertNotSame($r3, $r5);
    }
}
