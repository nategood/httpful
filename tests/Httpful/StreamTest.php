<?php

declare(strict_types=1);

namespace Httpful\Test;

use Httpful\Http;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class StreamTest extends TestCase
{
    public function testString()
    {
        $string = 'foo öäü bar';

        $stream = Http::stream($string);

        self::assertSame($string, $stream->getContents());
    }

    public function testArray()
    {
        $array = ['foo' => 'öäü bar'];

        $stream = Http::stream($array);

        self::assertSame($array, $stream->getContents());
    }
}
