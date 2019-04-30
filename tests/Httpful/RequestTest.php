<?php

declare(strict_types=1);

namespace Httpful\Test;

use Httpful\Request;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class RequestTest extends TestCase
{
    public function testGetInvalidURL()
    {
        $this->expectException(\Httpful\Exception\ConnectionErrorException::class);
        $this->expectExceptionMessage('Unable to connect');

        // Silence the default logger via whenError override
        Request::get('unavailable.url')->setErrorHandler(
        static function ($error) {
        }
    )->send();
    }
}
