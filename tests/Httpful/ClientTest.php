<?php

declare(strict_types=1);

namespace Httpful\tests;

use Httpful\Client;
use Httpful\Encoding;
use Httpful\Factory;
use Httpful\Http;
use Httpful\Mime;
use Httpful\Request;
use Httpful\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use voku\helper\DomParserInterface;
use voku\helper\HtmlDomParser;

/**
 * @internal
 */
final class ClientTest extends TestCase
{
    public function testGetDom()
    {
        $dom = Client::get_dom('http://google.com?a=b');
        static::assertInstanceOf(HtmlDomParser::class, $dom);

        $html = $dom->find('html');

        /** @noinspection PhpUnitTestsInspection */
        static::assertTrue(\strpos((string) $html, '<html') !== false);
    }

    public function testHttpClient()
    {
        $get = Client::get_request('http://google.com?a=b')->expectsHtml()->send();
        static::assertSame('http://www.google.com/?a=b', $get->getMetaData()['url']);
        static::assertInstanceOf(HtmlDomParser::class, $get->getRawBody());

        $head = Client::head('http://www.google.com?a=b');

        $expectedForDifferentCurlVersions = [
            'http://www.google.com?a=b',
            'http://www.google.com/?a=b',
        ];
        static::assertContains($head->getMetaData()['url'], $expectedForDifferentCurlVersions);

        static::assertTrue(is_string((string)$head->getBody()));
        static::assertSame('1.1', $head->getProtocolVersion());

        $post = Client::post('http://www.google.com?a=b');

        $expectedForDifferentCurlVersions = [
            'http://www.google.com?a=b',
            'http://www.google.com/?a=b',
        ];
        static::assertContains($head->getMetaData()['url'], $expectedForDifferentCurlVersions);
        static::assertSame(405, $post->getStatusCode());
    }

    public function testHttpFormClient()
    {
        $get = Client::post_request('http://google.com?a=b', ['a' => ['=', ' ', 2, 'ö']])->withContentTypeForm()->_curlPrep();
        static::assertSame('0=%3D&1=+&2=2&3=%C3%B6', $get->getSerializedPayload());
    }

    public function testSendRequest()
    {
        $expected_params = [
            'foo1' => 'bar1',
            'foo2' => 'bar2',
        ];
        $query = \http_build_query($expected_params);
        $http = new Factory();

        $response = (new Client())->sendRequest(
            $http->createRequest(
                Http::GET,
                "https://postman-echo.com/get?{$query}",
                Mime::JSON
            )
        );

        static::assertSame('1.1', $response->getProtocolVersion());
        static::assertSame(200, $response->getStatusCode());
        \assert($response instanceof Response);
        $result = $response->getRawBody();
        static::assertSame($expected_params, $result['args']);
    }

    public function testSendFormRequest()
    {
        $expected_data = [
            'foo1' => 'bar1',
            'foo2' => 'bar2',
        ];

        $response = Client::post_form('https://postman-echo.com/post', $expected_data);

        static::assertSame($expected_data, $response['form'], 'server received x-www-form POST data');
    }

    public function testPostAuthJson()
    {
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
        )->withContentEncoding(Encoding::DEFLATE);

        $response = $request->send();

        $data = $response->getRawBody();

        static::assertSame(
            [
                'foo1' => 'bar1',
                'foo2' => 'bar2',
            ],
            $data['data']
        );

        if (\method_exists(__CLASS__, 'assertStringContainsString')) {
            static::assertStringContainsString('https://postman-echo.com/post', $data['url']);
        } else {
            static::assertContains('https://postman-echo.com/post', $data['url']);
        }

        static::assertSame('https', $data['headers']['x-forwarded-proto']);

        static::assertSame('deflate', $data['headers']['accept-encoding']);

        if (\method_exists(__CLASS__, 'assertStringContainsString')) {
            static::assertStringContainsString('Basic ', $data['headers']['authorization']);
        } else {
            static::assertContains('Basic ', $data['headers']['authorization']);
        }

        static::assertSame('application/json', $data['headers']['content-type']);

        if (\method_exists(__CLASS__, 'assertStringContainsString')) {
            static::assertStringContainsString('Http/PhpClient', $data['headers']['user-agent']);
        } else {
            static::assertContains('Http/PhpClient', $data['headers']['user-agent']);
        }
    }

    public function testBasicAuthRequest()
    {
        $response = (new Client())->sendRequest(
            (new Request(Http::GET))
                ->withUriFromString('https://postman-echo.com/basic-auth')
                ->withBasicAuth('postman', 'password')
        );

        static::assertSame('{"authenticated":true}', str_replace(["\n", ' '], '', (string) $response));
    }

    public function testDigestAuthRequest()
    {
        $response = (new Client())->sendRequest(
            (new Request(Http::GET))
                ->withUriFromString('https://postman-echo.com/digest-auth')
                ->withDigestAuth('postman', 'password')
        );

        static::assertSame('{"authenticated":true}', str_replace(["\n", ' '], '', (string) $response));
    }

    public function testSendJsonRequest()
    {
        $expected_data = [
            'foo1' => 123,
            'foo2' => 456,
        ];

        $http = new Factory();

        $body = $http->createStream(
            \json_encode($expected_data)
        );

        $response = (new Client())->sendRequest(
            $http->createRequest(
                Http::POST,
                'https://postman-echo.com/post',
                Mime::JSON
            )->withBody($body)
        );

        static::assertSame('1.1', $response->getProtocolVersion());
        static::assertSame(200, $response->getStatusCode());

        if (\method_exists(__CLASS__, 'assertStringContainsString')) {
            static::assertStringContainsString('"content-type":"application\/json"', (string) $response);
        } else {
            static::assertContains('"content-type":"application\/json"', (string) $response);
        }
    }

    public function testPutCall()
    {
        $response = Client::put('https://postman-echo.com/put', 'lall');

        if (\method_exists(__CLASS__, 'assertStringContainsString')) {
            static::assertStringContainsString('"data":"lall"', str_replace(["\n", ' '], '', (string) $response));
        } else {
            static::assertContains('"data":"lall"', str_replace(["\n", ' '], '', (string) $response));
        }
    }

    public function testPatchCall()
    {
        $response = Client::patch('https://postman-echo.com/patch', 'lall');

        if (\method_exists(__CLASS__, 'assertStringContainsString')) {
            static::assertStringContainsString('"data":"lall"', str_replace(["\n", ' '], '', (string) $response));
        } else {
            static::assertContains('"data":"lall"', str_replace(["\n", ' '], '', (string) $response));
        }
    }

    public function testJsonHelper()
    {
        $expected_params = [
            'foo1' => 'b%20a%20r%201',
            'foo2' => 'b a r 2',
        ];

        $response = Client::get_json('https://postman-echo.com/get', $expected_params);
        static::assertSame($expected_params, $response['args']);

        $response = Client::get_json('https://postman-echo.com/get?', $expected_params);
        static::assertSame($expected_params, $response['args']);
    }

    public function testDownloadSimple()
    {
        $testFileUrl = 'http://thetofu.com/webtest/webmachine/test100k/test100.log';
        $tmpFile = \tempnam('/tmp', 'FOO');
        $expectedFileContent = \file_get_contents($testFileUrl);

        $response = Client::download($testFileUrl, $tmpFile, 5);

        static::assertTrue(\count($response->getHeaders()) > 0);
        static::assertSame($expectedFileContent, $response->getRawBody());
        static::assertSame($expectedFileContent, \file_get_contents($tmpFile));
    }

    public function testReceiveHeader()
    {
        $http = new Factory();

        $response = (new Client())->sendRequest(
            $http->createRequest(
                Http::GET,
                'https://postman-echo.com/headers',
                Mime::JSON
            )->withHeader('X-Hello', 'Hello World')
        );

        static::assertSame('1.1', $response->getProtocolVersion());
        static::assertSame(200, $response->getStatusCode());

        static::assertSame(
            'application/json; charset=utf-8',
            $response->getHeaderLine('Content-Type'),
            'Response model was populated with headers'
        );

        static::assertSame(
            'Hello World',
            \json_decode((string) $response, true)['headers']['x-hello'],
            'server received custom header'
        );
    }

    public function testReceiveHeaders()
    {
        $http = new Factory();

        $response = (new Client())->sendRequest(
            $http->createRequest(
                Http::GET,
                'https://postman-echo.com/response-headers?x-hello[]=one&x-hello[]=two',
                Mime::JSON
            )
        );

        static::assertSame('1.1', $response->getProtocolVersion());
        static::assertSame(200, $response->getStatusCode());

        static::assertSame(
            'application/json; charset=utf-8',
            $response->getHeaderLine('Content-Type'),
            'Response model was populated with headers'
        );

        static::assertSame(
            ['one', 'two'],
            $response->getHeader('X-Hello'),
            'Can parse multi-line header'
        );
    }

    public function testHttp2()
    {
        \curl_version()['features'];

        if (\PHP_VERSION_ID >= 70300 && \PHP_VERSION_ID < 70304) {
            static::markTestSkipped('PHP 7.3.0 to 7.3.3 don\'t support HTTP/2 PUSH');
        }

        /** @noinspection SuspiciousBinaryOperationInspection */
        if (!\defined('CURLMOPT_PUSHFUNCTION') || ($v = \curl_version())['version_number'] < 0x073d00 || !(\CURL_VERSION_HTTP2 & $v['features'])) {
            static::markTestSkipped('curl <7.61 is used or it is not compiled with support for HTTP/2 PUSH');
        }

        $http = new Factory();

        $response = (new Client())->sendRequest(
            $http->createRequest(
                Http::GET,
                'https://http2.akamai.com/demo/tile-0.png'
            )->withProtocolVersion(Http::HTTP_2_0)
        );

        static::assertSame('2', $response->getProtocolVersion());
        static::assertSame(200, $response->getStatusCode());

        static::assertSame(
            'image/png',
            $response->getHeaderLine('Content-Type')
        );
    }

    public function testSelfSignedCertificate()
    {
        $this->expectException(NetworkExceptionInterface::class);
        if (\method_exists(__CLASS__, 'expectExceptionMessageRegExp')) {
            $this->expectExceptionMessageRegExp('/.*certificat.*/');
        } else {
            $this->expectExceptionMessageMatches('/.*certificat.*/');
        }

        $client = new Client();
        $request = (new Request('GET'))->withUriFromString('https://self-signed.badssl.com/')->enableStrictSSL();
        /** @noinspection UnusedFunctionResultInspection */
        $client->sendRequest($request);
    }

    public function testIgnoreCertificateErrors()
    {
        $client = new Client();
        $request = (new Request('GET', Mime::PLAIN))
            ->withUriFromString('https://self-signed.badssl.com/')
            ->disableStrictSSL();
        $response = $client->sendRequest($request);

        static::assertEquals(200, $response->getStatusCode());
        if (\method_exists(__CLASS__, 'assertStringContainsString')) {
            static::assertStringContainsString('self-signed.<br>badssl.com', (string) $response);
        } else {
            static::assertContains('self-signed.<br>badssl.com', (string) $response);
        }

        // ---

        $client = new Client();
        $request = (new Request('GET', Mime::HTML))
            ->withUriFromString('https://self-signed.badssl.com/')
            ->disableStrictSSL();
        $response = $client->sendRequest($request);

        static::assertEquals(200, $response->getStatusCode());
        \assert($response instanceof Response);
        static::assertInstanceOf(DomParserInterface::class, $response->getRawBody());
    }

    public function testPageNotFound()
    {
        $client = new Client();
        $request = (new Request('GET'))->withUriFromString('http://www.google.com/DOES/NOT/EXISTS');
        $response = $client->sendRequest($request);
        static::assertEquals(404, $response->getStatusCode());
        if (\method_exists(__CLASS__, 'assertStringContainsString')) {
            static::assertStringContainsString('<title>Error 404 (Not Found)', (string) $response->getBody());
        } else {
            static::assertContains('<title>Error 404 (Not Found)', (string) $response->getBody());
        }
    }

    public function testHostNotFound()
    {
        $this->expectException(NetworkExceptionInterface::class);
        $this->expectExceptionMessage('Could not resolve host: www.does.not.exists');
        $client = new Client();
        $request = (new Request('GET'))->withUriFromString('http://www.does.not.exists');
        /** @noinspection UnusedFunctionResultInspection */
        $client->sendRequest($request);
    }

    public function testInvalidMethod()
    {
        $this->expectException(RequestExceptionInterface::class);
        $this->expectExceptionMessage("Unknown HTTP method: 'ASD'");
        $client = new Client();
        $request = (new Request('ASD'))->withUriFromString('http://www.google.it');
        /** @noinspection UnusedFunctionResultInspection */
        $client->sendRequest($request);
    }

    public function testGet()
    {
        $client = new Client();
        $request = (new Request('GET'))
            ->disableStrictSSL()
            ->withUriFromString('https://moelleken.org/');
        $response = $client->sendRequest($request);
        static::assertEquals(200, $response->getStatusCode());

        if (\method_exists(__CLASS__, 'assertStringContainsString')) {
            static::assertStringContainsString('Lars Moelleken', (string) $response->getBody());
        } else {
            static::assertContains('Lars Moelleken', (string) $response->getBody());
        }
        static::assertContains($response->getProtocolVersion(), ['1.1', '2']);

        static::assertEquals(['text/html; charset=utf-8'], $response->getHeader('content-type'));
    }

    public function testCookie()
    {
        $client = new Client();
        $request = (new Request('GET'))->withUriFromString('https://httpbin.org/get');
        $request = $request->withAddedCookie('name', 'value');
        $response = $client->sendRequest($request);
        static::assertEquals(200, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $cookieSent = $body['headers']['Cookie'];
        static::assertEquals('name=value', $cookieSent);
    }

    public function testMultipleCookies()
    {
        $client = new Client();
        $request = (new Request('GET'))->withUriFromString('https://httpbin.org/get');
        $request = $request->withAddedCookie('name', 'value');
        $request = $request->withAddedCookie('foo', 'bar');
        $response = $client->sendRequest($request);
        static::assertEquals(200, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $cookieSent = $body['headers']['Cookie'];
        static::assertEquals('name=value,foo=bar', $cookieSent);
    }

    public function testPutSendData()
    {
        $client = new Client();
        $dataToSend = ['abc' => 'def'];
        $request = (new Request('PUT', Mime::JSON))
            ->withUriFromString('https://httpbin.org/put')
            ->withBodyFromArray($dataToSend)
            ->withTimeout(60);
        $response = $client->sendRequest($request);
        static::assertEquals(200, $response->getStatusCode());
        $body = \json_decode((string) $response, true);
        $dataSent = \json_decode($body['data'], true);
        static::assertEquals($dataToSend, $dataSent);
    }

    public function testFollowsRedirect()
    {
        $client = new Client();
        $request = (new Request('GET'))
            ->withUriFromString('http://google.de')
            ->followRedirects();
        $response = $client->sendRequest($request);
        static::assertEquals(200, $response->getStatusCode());
    }

    public function testNotFollowsRedirect()
    {
        $client = new Client();
        $request = (new Request('GET'))
            ->withUriFromString('http://google.de')
            ->doNotFollowRedirects();
        $response = $client->sendRequest($request);
        static::assertEquals(301, $response->getStatusCode());
    }

    public function testExpiredTimeout()
    {
        $this->expectException(NetworkExceptionInterface::class);
        if (\method_exists(__CLASS__, 'expectExceptionMessageRegExp')) {
            $this->expectExceptionMessageRegExp('/Timeout was reached/');
        } else {
            $this->expectExceptionMessageMatches('/Timeout was reached/');
        }
        $client = new Client();
        $request = (new Request())->withUriFromString('http://slowwly.robertomurray.co.uk/delay/10000/url/http://www.example.com')
            ->withConnectionTimeoutInSeconds(0.001);
        /** @noinspection UnusedFunctionResultInspection */
        $client->sendRequest($request);
    }

    public function testNotExpiredTimeout()
    {
        $client = new Client();
        $request = (new Request('GET'))->withUriFromString('https://www.google.com/robots.txt')
            ->withConnectionTimeoutInSeconds(10);
        $response = $client->sendRequest($request);
        static::assertEquals(200, $response->getStatusCode());
    }
}
