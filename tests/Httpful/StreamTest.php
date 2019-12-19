<?php

declare(strict_types=1);

namespace Httpful\tests;

use Httpful\Http;
use Httpful\Stream;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class StreamTest extends TestCase
{
    public function testArray()
    {
        $array = ['foo' => 'öäü bar'];

        $stream = Http::stream($array);

        static::assertSame($array, $stream->getContents());
    }

    public function testCanDetachStream()
    {
        $r = \fopen('php://temp', 'w+b');
        $stream = Stream::create($r);
        $stream->write('foo');
        static::assertTrue($stream->isReadable());
        static::assertSame($r, $stream->detach());
        $stream->detach();
        static::assertFalse($stream->isReadable());
        static::assertFalse($stream->isWritable());
        static::assertFalse($stream->isSeekable());
        $throws = static function (callable $fn) use ($stream) {
            try {
                $fn($stream);
                static::fail();
            } catch (\Exception $e) {
                // Suppress the exception
            }
        };
        $throws(
            static function (Stream $stream) {
                $stream->read(10);
            }
        );
        $throws(
            static function (Stream $stream) {
                $stream->write('bar');
            }
        );
        $throws(
            static function (Stream $stream) {
                $stream->seek(10);
            }
        );
        $throws(
            static function (Stream $stream) {
                $stream->tell();
            }
        );
        $throws(
            static function (Stream $stream) {
                $stream->eof();
            }
        );
        $throws(
            static function (Stream $stream) {
                $stream->getSize();
            }
        );
        $throws(
            static function (Stream $stream) {
                $stream->getContents();
            }
        );
        static::assertSame('', (string) $stream);
        $stream->close();
    }

    public function testChecksEof()
    {
        $handle = \fopen('php://temp', 'w+b');
        \fwrite($handle, 'data');
        $stream = Stream::create($handle);
        static::assertFalse($stream->eof());
        $stream->read(4);
        static::assertTrue($stream->eof());
        $stream->close();
    }

    public function testCloseClearProperties()
    {
        $handle = \fopen('php://temp', 'r+b');
        $stream = new Stream($handle);
        $stream->close();
        static::assertFalse($stream->isSeekable());
        static::assertFalse($stream->isReadable());
        static::assertFalse($stream->isWritable());
        static::assertNull($stream->getSize());
        static::assertEmpty($stream->getMetadata());
    }

    public function testConstructorInitializesProperties()
    {
        $handle = \fopen('php://temp', 'r+b');
        \fwrite($handle, 'data');
        $stream = Stream::create($handle);
        static::assertTrue($stream->isReadable());
        static::assertTrue($stream->isWritable());
        static::assertTrue($stream->isSeekable());
        static::assertSame('php://temp', $stream->getMetadata('uri'));
        static::assertInternalType('array', $stream->getMetadata());
        static::assertSame(4, $stream->getSize());
        static::assertFalse($stream->eof());
        $stream->close();
    }

    public function testConvertsToString()
    {
        $handle = \fopen('php://temp', 'w+b');
        \fwrite($handle, 'data');
        $stream = Stream::create($handle);
        static::assertSame('data', (string) $stream);
        static::assertSame('data', (string) $stream);
        $stream->close();
    }

    public function testEnsuresSizeIsConsistent()
    {
        $h = \fopen('php://temp', 'w+b');
        static::assertSame(3, \fwrite($h, 'foo'));
        $stream = Stream::create($h);
        static::assertSame(3, $stream->getSize());
        static::assertSame(4, $stream->write('test'));
        static::assertSame(7, $stream->getSize());
        static::assertSame(7, $stream->getSize());
        $stream->close();
    }

    public function testGetSize()
    {
        $size = \filesize(__FILE__);
        $handle = \fopen(__FILE__, 'rb');
        $stream = Stream::create($handle);
        static::assertSame($size, $stream->getSize());
        // Load from cache
        static::assertSame($size, $stream->getSize());
        $stream->close();
    }

    public function testGetsContents()
    {
        $handle = \fopen('php://temp', 'w+b');
        \fwrite($handle, 'data');
        $stream = Stream::create($handle);
        static::assertSame('', $stream->getContents());
        $stream->seek(0);
        static::assertSame('data', $stream->getContents());
        static::assertSame('', $stream->getContents());
    }

    public function testProvidesStreamPosition()
    {
        $handle = \fopen('php://temp', 'w+b');
        $stream = Stream::create($handle);
        static::assertSame(0, $stream->tell());
        $stream->write('foo');
        static::assertSame(3, $stream->tell());
        $stream->seek(1);
        static::assertSame(1, $stream->tell());
        static::assertSame(\ftell($handle), $stream->tell());
        $stream->close();
    }

    public function testString()
    {
        $string = 'foo öäü bar';

        $stream = Http::stream($string);

        static::assertSame($string, $stream->getContents());
    }
}
