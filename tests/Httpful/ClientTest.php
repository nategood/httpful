<?php

declare(strict_types=1);

namespace Httpful\tests;

use Httpful\Client;
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
        static::assertSame('http://www.google.com/?a=b', $head->getMetaData()['url']);
        /** @noinspection PhpUnitTestsInspection */
        static::assertInternalType('string', (string) $head->getBody());
        static::assertSame('1.1', $head->getProtocolVersion());

        $post = Client::post('http://www.google.com?a=b');
        static::assertSame('http://www.google.com/?a=b', $post->getMetaData()['url']);
        static::assertSame(405, $post->getStatusCode());
    }

    public function testHttpFormClient()
    {
        $get = Client::post_request('http://google.com?a=b', ['a' => ['=', ' ', 2, 'ö']])->contentTypeForm()->_curlPrep();
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
        /** @noinspection PhpUndefinedFieldInspection */
        static::assertSame($expected_params, (array) $result->args);
    }

    public function testSendFormRequest()
    {
        $expected_params = [
            'foo1' => 'bar1',
            'foo2' => 'bar2',
        ];
        $query = \http_build_query($expected_params);
        $http = new Factory();

        $response = (new Client())->sendRequest(
            ($http->createRequest(
                Http::POST,
                "https://postman-echo.com/post?{$query}",
                Mime::FORM
            ))
        );

        static::assertSame('1.1', $response->getProtocolVersion());
        static::assertSame(200, $response->getStatusCode());
        static::assertContains('"content-type":"application/x-www-form-urlencoded"', (string) $response);
    }

    public function testJsonHelper()
    {
        $expected_params = [
            'foo1' => 'bar1',
            'foo2' => 'bar2',
        ];
        $query = \http_build_query($expected_params);

        $response = Client::get_json("https://postman-echo.com/get?{$query}");
        /** @noinspection PhpUndefinedFieldInspection */
        static::assertSame($expected_params, (array) $response->args);
    }

    public function testSelfSignedCertificate()
    {
        $this->expectException(NetworkExceptionInterface::class);
        $this->expectExceptionMessageRegExp('/.*certificat.*/');
        $client = (new Client());
        $request = (new Request('GET'))->setUriFromString('https://self-signed.badssl.com/')->enableStrictSSL();
        /** @noinspection UnusedFunctionResultInspection */
        $client->sendRequest($request);
    }

    public function testIgnoreCertificateErrors()
    {
        $client = (new Client());
        $request = (new Request('GET', Mime::PLAIN))
            ->setUriFromString('https://self-signed.badssl.com/')
            ->disableStrictSSL();
        $response = $client->sendRequest($request);

        static::assertEquals(200, $response->getStatusCode());
        static::assertContains('self-signed.<br>badssl.com', (string) $response);

        // ---

        $client = (new Client());
        $request = (new Request('GET', Mime::HTML))
            ->setUriFromString('https://self-signed.badssl.com/')
            ->disableStrictSSL();
        $response = $client->sendRequest($request);

        static::assertEquals(200, $response->getStatusCode());
        \assert($response instanceof Response);
        static::assertInstanceOf(DomParserInterface::class, $response->getRawBody());
    }

    public function testPageNotFound()
    {
        $client = new Client();
        $request = (new Request('GET'))->setUriFromString('http://www.google.com/DOES/NOT/EXISTS');
        $response = $client->sendRequest($request);
        static::assertEquals(404, $response->getStatusCode());
        static::assertContains('<title>Error 404 (Not Found)', (string) $response->getBody());
    }

    public function testHostNotFound()
    {
        $this->expectException(NetworkExceptionInterface::class);
        $this->expectExceptionMessage('Could not resolve host: www.does.not.exists');
        $client = new Client();
        $request = (new Request('GET'))->setUriFromString('http://www.does.not.exists');
        /** @noinspection UnusedFunctionResultInspection */
        $client->sendRequest($request);
    }

    public function testInvalidMethod()
    {
        $this->expectException(RequestExceptionInterface::class);
        $this->expectExceptionMessage("Unknown HTTP method: 'ASD'");
        $client = new Client();
        $request = (new Request('ASD'))->setUriFromString('http://www.google.it');
        /** @noinspection UnusedFunctionResultInspection */
        $client->sendRequest($request);
    }

    public function testGet()
    {
        $client = new Client();
        $request = (new Request('GET'))->setUriFromString('https://ideato.it/robots.txt');
        $response = $client->sendRequest($request);
        static::assertEquals(200, $response->getStatusCode());
        static::assertStringStartsWith('User-agent:', (string) $response->getBody());
        static::assertContains($response->getProtocolVersion(), ['1.1', '2']);
        static::assertEquals(['text/plain; charset=utf-8'], $response->getHeader('content-type'));
    }

    public function testCookie()
    {
        $client = new Client();
        $request = (new Request('GET'))->setUriFromString('https://httpbin.org/get');
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
        $request = (new Request('GET'))->setUriFromString('https://httpbin.org/get');
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
            ->setUriFromString('https://httpbin.org/put')
            ->withBodyFromArray($dataToSend);
        $response = $client->sendRequest($request);
        static::assertEquals(200, $response->getStatusCode());
        $body = \json_decode((string) $response, true);
        $dataSent = \json_decode($body['data'], true);
        static::assertEquals($dataToSend, $dataSent);
    }

    public function testItFollowsRedirect()
    {
        $client = new Client();
        $request = (new Request('GET'))
            ->setUriFromString('http://httpbin.org/redirect-to?url=http%3A%2F%2Fwww.google.it%2Frobots.txt&status_code=301')
            ->followRedirects();
        $response = $client->sendRequest($request);
        static::assertStringStartsWith('User-agent:', (string) $response->getBody());
        static::assertEquals(200, $response->getStatusCode());
    }

    public function testExpiredTimeout()
    {
        $this->expectException(NetworkExceptionInterface::class);
        $this->expectExceptionMessageRegExp('/Timeout was reached/');
        $client = new Client();
        $request = (new Request())->setUriFromString('http://slowwly.robertomurray.co.uk/delay/10000/url/http://www.example.com')
            ->setConnectionTimeoutInSeconds(0.001);
        /** @noinspection UnusedFunctionResultInspection */
        $client->sendRequest($request);
    }

    public function testNotExpiredTimeout()
    {
        $client = new Client();
        $request = (new Request('GET'))->setUriFromString('https://www.google.com/robots.txt')
            ->setConnectionTimeoutInSeconds(10);
        $response = $client->sendRequest($request);
        static::assertEquals(200, $response->getStatusCode());
    }
}
