<?php

declare(strict_types=1);

namespace Httpful\Test;

use Httpful\Client;
use PHPUnit\Framework\TestCase;
use voku\helper\HtmlDomParser;

/**
 * @internal
 */
final class ClientTest extends TestCase
{
    public function testGetDom()
    {
        $dom = Client::get_dom('http://google.com?a=b');
        self::assertInstanceOf(HtmlDomParser::class, $dom);

        $html = $dom->find('html');

        /** @noinspection PhpUnitTestsInspection */
        self::assertTrue(strpos((string)$html, '<html') !== false);
    }

    public function testHttpClient()
    {
        $get = Client::get_request('http://google.com?a=b')->expectsHtml()->send();
        static::assertSame('http://www.google.com/?a=b', $get->getMetaData()['url']);
        static::assertInstanceOf(\voku\helper\HtmlDomParser::class, $get->getBody());

        $head = Client::head('http://www.google.com?a=b');
        static::assertSame('http://www.google.com/?a=b', $head->getMetaData()['url']);
        /** @noinspection PhpUnitTestsInspection */
        static::assertInternalType('string', $head->getBody());
        static::assertSame('1.1', $head->getProtocolVersion());

        $post = Client::post('http://www.google.com?a=b');
        static::assertSame('http://www.google.com/?a=b', $post->getMetaData()['url']);
        static::assertSame(405, $post->getStatusCode());
    }
}
