<?php

declare(strict_types=1);

namespace Httpful\tests;

use Httpful\ClientMulti;
use Httpful\Http;
use Httpful\Request;
use Httpful\Response;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class ClientMultiTest extends TestCase
{
    public function testGet()
    {
        /** @var Response[] $results */
        $results = [];
        $multi = new ClientMulti(
            static function (Response $response, Request $request) use (&$results) {
                $results[] = $response;
            }
        );

        $multi->add_get('http://google.com?a=b');
        $multi->add_get('http://moelleken.org');

        $multi->start();

        static::assertCount(2, $results);
        static::assertContains('<!doctype html>', (string) $results[0]);
        static::assertContains('Lars Moelleken', (string) $results[1]);
    }

    public function testBasicAuthRequest()
    {
        /** @var Response[] $results */
        $results = [];
        $multi = new ClientMulti(
            static function (Response $response, Request $request) use (&$results) {
                $results[] = $response;
            }
        );

        $request = (new Request(Http::GET))
            ->withUriFromString('https://postman-echo.com/basic-auth')
            ->withBasicAuth('postman', 'password');

        $multi->add_request($request);

        $multi->start();

        static::assertSame('{"authenticated":true}', (string) $results[0]);
    }
}
