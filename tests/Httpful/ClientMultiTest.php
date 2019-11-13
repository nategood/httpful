<?php

declare(strict_types=1);

namespace Httpful\tests;

use Httpful\Client;
use Httpful\ClientMulti;
use Httpful\Encoding;
use Httpful\Http;
use Httpful\Mime;
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

    public function testPostAuthJson()
    {
        /** @var Response[] $results */
        $results = [];
        $multi = new ClientMulti(
            static function (Response $response, Request $request) use (&$results) {
                $results[] = $response;
            }
        );

        $request = Client::post_request(
            'https://postman-echo.com/post',
            [
                'foo1' => 'bar1',
                'foo2' => 'bar2',
            ],
            Mime::JSON
        )->withBasicAuth(
            'postman',
            'password'
        )->withContentEncoding(Encoding::GZIP);

        $multi->add_request($request);

        $request = Client::post_request(
            'https://postman-echo.com/post',
            [
                'foo3' => 'bar1',
                'foo4' => 'bar2',
            ],
            Mime::JSON
        )->withBasicAuth(
            'postman',
            'password'
        )->withContentEncoding(Encoding::GZIP);

        $multi->add_request($request);

        $multi->start();

        static::assertCount(2, $results);

        $data = $results[1]->getRawBody();

        static::assertTrue(
            [
                'foo3' => 'bar1',
                'foo4' => 'bar2',
            ] === $data['data']
            ||
            [
                'foo1' => 'bar1',
                'foo2' => 'bar2',
            ] === $data['data']
        );

        $data = $results[0]->getRawBody();

        static::assertTrue(
            [
                'foo3' => 'bar1',
                'foo4' => 'bar2',
            ] === $data['data']
            ||
            [
                'foo1' => 'bar1',
                'foo2' => 'bar2',
            ] === $data['data']
        );

        static::assertContains('https://postman-echo.com/post', $data['url']);

        static::assertSame('https', $data['headers']['x-forwarded-proto']);

        static::assertSame('gzip', $data['headers']['accept-encoding']);

        static::assertContains('Basic ', $data['headers']['authorization']);

        static::assertSame('application/json', $data['headers']['content-type']);

        static::assertContains('Http/PhpClient', $data['headers']['user-agent']);
    }
}
