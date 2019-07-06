<?php

declare(strict_types=1);

namespace Httpful\tests;

use Httpful\Exception\NetworkErrorException;
use Httpful\Handlers\DefaultMimeHandler;
use Httpful\Handlers\JsonMimeHandler;
use Httpful\Handlers\XmlMimeHandler;
use Httpful\Http;
use Httpful\Mime;
use Httpful\Request;
use Httpful\Response;
use Httpful\Setup;
use PHPUnit\Framework\TestCase;

/** @noinspection PhpMultipleClassesDeclarationsInOneFile */

/**
 * @internal
 */
final class HttpfulTest extends TestCase
{
    const SAMPLE_CSV_HEADER =
        "HTTP/1.1 200 OK
Content-Type: text/csv
Connection: keep-alive
Transfer-Encoding: chunked\r\n";

    const SAMPLE_CSV_RESPONSE =
        'Key1,Key2
Value1,Value2
"40.0","Forty"';

    const SAMPLE_HTML_HEADER =
        "HTTP/1.1 200 OK
Content-Type: test/html
Connection: keep-alive
Transfer-Encoding: chunked\r\n";

    // INFO: Travis-CI can't handle e.g. "10.255.255.1" or "http://www.google.com:81"
    const SAMPLE_HTML_RESPONSE = '<html lang="en"><head><title>foo</title></head><body></body><arrayProp><array><k1><myClass><intProp>2</intProp></myClass></k1></array></arrayProp><stringProp>a string</stringProp><boolProp>TRUE</boolProp></body></html>';

    const SAMPLE_JSON_HEADER =
        "HTTP/1.1 200 OK
Content-Type: application/json
Connection: keep-alive
Transfer-Encoding: chunked\r\n";

    const SAMPLE_JSON_RESPONSE = '{"key":"value","object":{"key":"value"},"array":[1,2,3,4]}';

    const SAMPLE_MULTI_HEADER =
        "HTTP/1.1 200 OK
Content-Type: application/json
Connection: keep-alive
Transfer-Encoding: chunked
X-My-Header:Value1
X-My-Header:Value2\r\n";

    const SAMPLE_VENDOR_HEADER =
        "HTTP/1.1 200 OK
Content-Type: application/vnd.nategood.message+xml
Connection: keep-alive
Transfer-Encoding: chunked\r\n";

    const SAMPLE_VENDOR_TYPE = 'application/vnd.nategood.message+xml';

    const SAMPLE_XML_HEADER =
        "HTTP/1.1 200 OK
Content-Type: application/xml
Connection: keep-alive
Transfer-Encoding: chunked\r\n";

    const SAMPLE_XML_RESPONSE = '<stdClass><arrayProp><array><k1><myClass><intProp>2</intProp></myClass></k1></array></arrayProp><stringProp>a string</stringProp><boolProp>TRUE</boolProp></stdClass>';

    const TEST_SERVER = TEST_SERVER;

    const TEST_URL = 'http://127.0.0.1:8008';

    const TEST_URL_400 = 'http://127.0.0.1:8008/400';

    const TIMEOUT_URI = 'http://suckup.de/timeout.php';

    public function testAccept()
    {
        $r = Request::get('http://example.com/')
            ->expectsType(Mime::JSON);

        static::assertSame(Mime::JSON, $r->getExpectedType());
        $r->_curlPrep();
        static::assertContains('application/json', $r->getRawHeaders());
    }

    public function testAttach()
    {
        $req = new Request();
        $testsPath = \realpath(__DIR__ . \DIRECTORY_SEPARATOR . '..');
        $filename = $testsPath . \DIRECTORY_SEPARATOR . '/static/test_image.jpg';
        $req->attach(['index' => $filename]);
        $payload = $req->getPayload()['index'];

        static::assertInstanceOf(\CURLFile::class, $payload);
        static::assertSame($req->getContentType(), Mime::UPLOAD);
        static::assertSame($req->getSerializePayloadMethod(), Request::SERIALIZE_PAYLOAD_NEVER);
    }

    public function testAuthSetup()
    {
        $username = 'nathan';
        $password = 'opensesame';

        $r = Request::get('http://example.com/')
            ->basicAuth($username, $password);

        static::assertTrue($r->hasBasicAuth());
    }

    public function testBeforeSend()
    {
        $invoked = false;
        $changed = false;
        $self = $this;

        try {
            Request::get('malformed://url')
                ->beforeSend(
                    static function ($request) use (&$invoked, $self) {

                           /* @var Request $request */

                        $self::assertSame('malformed://url', $request->getUriString());
                        $request->setUriFromString('malformed2://url');
                        $invoked = true;
                    }
                   )
                ->setErrorHandler(
                    static function ($error) { /* Be silent */
                    }
                   )
                ->send();
        } catch (NetworkErrorException $e) {
            static::assertNotSame(\strpos($e->getMessage(), 'malformed2'), false, \print_r($e->getMessage(), true));
            $changed = true;
        }

        static::assertTrue($invoked);
        static::assertTrue($changed);
    }

    public function testCsvResponseParse()
    {
        $req = new Request(Http::GET, Mime::CSV);
        $response = new Response(self::SAMPLE_CSV_RESPONSE, self::SAMPLE_CSV_HEADER, $req);

        static::assertSame('Key1', $response->getRawBody()[0][0]);
        static::assertSame('Value1', $response->getRawBody()[1][0]);
        static::assertInternalType('string', $response->getRawBody()[2][0]);
        static::assertSame('40.0', $response->getRawBody()[2][0]);
    }

    public function testCustomAccept()
    {
        $accept = 'application/api-1.0+json';
        $r = Request::get('http://example.com/')
            ->addHeader('Accept', $accept);

        $r->_curlPrep();
        static::assertContains($accept, $r->getRawHeaders());
        static::assertSame($accept, $r->getHeaders()['Accept']);
    }

    public function testCustomHeaders()
    {
        $accept = 'application/api-1.0+json';
        $r = Request::get('http://example.com/')
            ->addHeaders(
                [
                    'Accept' => $accept,
                    'Foo'    => 'Bar',
                ]
            );

        $r->_curlPrep();
        static::assertContains($accept, $r->getRawHeaders());
        static::assertSame($accept, $r->getHeaders()['Accept'][0]);
        static::assertSame('Bar', $r->getHeaders()['Foo'][0]);
    }

    public function testCustomHeader()
    {
        $r = Request::get('http://example.com/')
            ->addHeader('XTrivial', 'FooBar');

        $r->_curlPrep();
        static::assertContains('', $r->getRawHeaders());
        static::assertSame('FooBar', $r->getHeaders()['XTrivial']);
    }

    public function testCustomMimeRegistering()
    {
        // Register new mime type handler for "application/vnd.nategood.message+xml"
        Setup::registerMimeHandler(self::SAMPLE_VENDOR_TYPE, new DemoDefaultMimeHandler());

        static::assertTrue(Setup::hasParserRegistered(self::SAMPLE_VENDOR_TYPE));

        $request = new Request(Http::GET, self::SAMPLE_VENDOR_TYPE);
        $response = new Response('<xml><name>Nathan</name></xml>', self::SAMPLE_VENDOR_HEADER, $request);

        static::assertSame(self::SAMPLE_VENDOR_TYPE, $response->getContentType());
        static::assertSame('custom parse', $response->getRawBody());
    }

    public function testDefaults()
    {
        // Our current defaults are as follows
        $r = new Request();
        static::assertSame(Http::GET, $r->getHttpMethod());
        static::assertFalse($r->isStrictSSL());
    }

    public function testDetectContentType()
    {
        $req = new Request();
        $response = new Response(self::SAMPLE_JSON_RESPONSE, self::SAMPLE_JSON_HEADER, $req);
        static::assertSame('application/json', $response->getHeaders()['Content-Type'][0]);
    }

    public function testDetermineLength()
    {
        $r = new Request();
        static::assertSame(1, $r->_determineLength('A'));
        static::assertSame(2, $r->_determineLength('À'));
        static::assertSame(2, $r->_determineLength('Ab'));
        static::assertSame(3, $r->_determineLength('Àb'));
        static::assertSame(6, $r->_determineLength('世界'));
    }

    public function testDigestAuthSetup()
    {
        $username = 'nathan';
        $password = 'opensesame';

        $r = Request::get('http://example.com/')
            ->digestAuth($username, $password);

        static::assertTrue($r->hasDigestAuth());
    }

    public function testEmptyResponseParse()
    {
        $req = (new Request())->mime(Mime::JSON);
        $response = new Response('', self::SAMPLE_JSON_HEADER, $req);
        static::assertNull($response->getRawBody());

        $reqXml = (new Request())->mime(Mime::XML);
        $responseXml = new Response('', self::SAMPLE_XML_HEADER, $reqXml);
        static::assertNull($responseXml->getRawBody());
    }

    public function testHTMLResponseParse()
    {
        $req = (new Request())->mime(Mime::HTML);
        $response = new Response(self::SAMPLE_HTML_RESPONSE, self::SAMPLE_HTML_HEADER, $req);
        /** @var \voku\helper\HtmlDomParser $dom */
        $dom = $response->getRawBody();
        static::assertSame('object', \gettype($dom));
        static::assertSame(\voku\helper\HtmlDomParser::class, \get_class($dom));
        $bools = $dom->find('boolProp');
        foreach ($bools as $bool) {
            static::assertSame('TRUE', $bool->innerhtml);
        }
        $ints = $dom->find('intProp');
        foreach ($ints as $int) {
            static::assertSame('2', $int->innerhtml);
        }
        $strings = $dom->find('stringProp');
        foreach ($strings as $string) {
            static::assertSame('<stringprop>a string</stringprop>', (string) $string);
        }
    }

    public function testHasErrors()
    {
        $req = new Request(Http::GET, Mime::JSON);
        $response = new Response('', "HTTP/1.1 100 Continue\r\n", $req);
        static::assertFalse($response->hasErrors());
        $response = new Response('', "HTTP/1.1 200 OK\r\n", $req);
        static::assertFalse($response->hasErrors());
        $response = new Response('', "HTTP/1.1 300 Multiple Choices\r\n", $req);
        static::assertFalse($response->hasErrors());
        $response = new Response('', "HTTP/1.1 400 Bad Request\r\n", $req);
        static::assertTrue($response->hasErrors());
        $response = new Response('', "HTTP/1.1 500 Internal Server Error\r\n", $req);
        static::assertTrue($response->hasErrors());
    }

    public function testHasProxyWithEnvironmentProxy()
    {
        \putenv('http_proxy=http://127.0.0.1:300/');
        $r = Request::get('some_other_url');
        static::assertTrue($r->hasProxy());

        // reset
        \putenv('http_proxy=');
    }

    public function testHasProxyWithProxy()
    {
        $r = Request::get('some_other_url');
        $r->useProxy('proxy.com');
        static::assertTrue($r->hasProxy());
    }

    public function testHasProxyWithoutProxy()
    {
        $r = Request::get('someUrl');
        static::assertFalse($r->hasProxy());
    }

    public function testHtmlSerializing()
    {
        $body = self::SAMPLE_HTML_RESPONSE;
        $request = Request::post(self::TEST_URL, $body)->mime(Mime::HTML)->_curlPrep();
        static::assertSame($body, $request->getSerializedPayload());
    }

    public function testUseTemplate()
    {
        // Test setting defaults/templates

        // Create the template
        $template = (new Request())
            ->withMethod(Http::GET)
            ->enableStrictSSL()
            ->expectsType(Mime::PLAIN)
            ->contentType(Mime::PLAIN);

        $r = new Request(null, null, $template);

        static::assertTrue($r->isStrictSSL());
        static::assertSame(Http::GET, $r->getHttpMethod());
        static::assertSame(Mime::PLAIN, $r->getExpectedType());
        static::assertSame(Mime::PLAIN, $r->getContentType());
    }

    /**
     * init
     */
    public function testInit()
    {
        $r = new Request();
        // Did we get a 'Request' object?
        static::assertSame(Request::class, \get_class($r));
    }

    public function testIsUpload()
    {
        $req = new Request();

        $req->contentType(Mime::UPLOAD);

        static::assertTrue($req->isUpload());
    }

    public function testJsonResponseParse()
    {
        $req = (new Request())->mime(Mime::JSON);
        $response = new Response(self::SAMPLE_JSON_RESPONSE, self::SAMPLE_JSON_HEADER, $req);

        static::assertSame('value', $response->getRawBody()->key);
        static::assertSame('value', $response->getRawBody()->object->key);
        static::assertInternalType('array', $response->getRawBody()->array);
        static::assertSame(1, $response->getRawBody()->array[0]);
    }

    public function testMethods()
    {
        $valid_methods = ['get', 'post', 'delete', 'put', 'options', 'head'];
        $url = 'http://example.com/';
        foreach ($valid_methods as $method) {
            $r = \call_user_func([Request::class, $method], $url);
            static::assertSame(Request::class, \get_class($r));
            static::assertSame(\strtoupper($method), $r->getHttpMethod());
        }
    }

    public function testMissingBodyContentType()
    {
        $body = 'A string';
        $request = Request::post(self::TEST_URL, $body)->_curlPrep();
        static::assertSame($body, $request->getSerializedPayload());
    }

    public function testMissingContentType()
    {
        // Parent type
        $request = (new Request())->mime(Mime::XML);
        $response = new Response(
            '<xml><name>Nathan</name></xml>',
            "HTTP/1.1 200 OK
Connection: keep-alive
Transfer-Encoding: chunked\r\n",
            $request
        );

        static::assertSame('', $response->getContentType());
    }

    public function testNoAutoParse()
    {
        $req = (new Request())->mime(Mime::JSON)->disableAutoParsing();
        $response = new Response(self::SAMPLE_JSON_RESPONSE, self::SAMPLE_JSON_HEADER, $req);
        static::assertInternalType('string', (string) $response->getBody());
        $req = (new Request())->mime(Mime::JSON)->enableAutoParsing();
        $response = new Response(self::SAMPLE_JSON_RESPONSE, self::SAMPLE_JSON_HEADER, $req);
        static::assertInternalType('object', $response->getRawBody());
    }

    public function testOverrideXmlHandler()
    {
        // Lazy test...
        $prev = Setup::setupGlobalMimeType(Mime::XML);
        static::assertInstanceOf(DefaultMimeHandler::class, $prev);
        $conf = ['namespace' => 'http://example.com'];
        Setup::registerMimeHandler(Mime::XML, new XmlMimeHandler($conf));
        $new = Setup::setupGlobalMimeType(Mime::XML);
        static::assertNotSame($prev, $new);
        Setup::reset();
    }

    public function testParams()
    {
        $r = Request::get('http://google.com');
        $r->_curlPrep();
        $r->_uriPrep();
        static::assertSame('http://google.com', $r->getUriString());

        $r = Request::get('http://google.com?q=query');
        $r->_curlPrep();
        $r->_uriPrep();
        static::assertSame('http://google.com?q=query', $r->getUriString());

        $r = Request::get('http://google.com');
        $r->param('a', 'b');
        $r->_curlPrep();
        $r->_uriPrep();
        static::assertSame('http://google.com?a=b', $r->getUriString());

        $r = Request::get('http://google.com');
        $r->_curlPrep();
        $r->_uriPrep();
        static::assertSame('http://google.com', $r->getUriString());

        $r = Request::get('http://google.com?a=b');
        $r->param('c', 'd');
        $r->_curlPrep();
        $r->_uriPrep();
        static::assertSame('http://google.com?a=b&c=d', $r->getUriString());

        $r = Request::get('http://google.com?a=b');
        $r->param('', 'e');
        $r->_curlPrep();
        $r->_uriPrep();
        static::assertSame('http://google.com?a=b', $r->getUriString());

        $r = Request::get('http://google.com?a=b');
        $r->param('e', '');
        $r->_curlPrep();
        $r->_uriPrep();
        static::assertSame('http://google.com?a=b', $r->getUriString());
    }

    public function testParentType()
    {
        // Parent type
        $request = (new Request())->mime(Mime::XML);
        $response = new Response('<xml><name>Nathan</name></xml>', self::SAMPLE_VENDOR_HEADER, $request);

        static::assertSame('application/xml', $response->getParentType());
        static::assertSame(self::SAMPLE_VENDOR_TYPE, $response->getContentType());
        static::assertTrue($response->isMimeVendorSpecific());

        // Make sure we still parsed as if it were plain old XML
        static::assertSame('Nathan', (string) $response->getRawBody()->name);
    }

    public function testParseCode()
    {
        $req = (new Request())->mime(Mime::JSON);
        $response = new Response(self::SAMPLE_JSON_RESPONSE, self::SAMPLE_JSON_HEADER, $req);
        $code = $response->_getResponseCodeFromHeaderString("HTTP/1.1 406 Not Acceptable\r\n");
        static::assertSame(406, $code);
    }

    public function testParseHeaders()
    {
        $req = (new Request())->mime(Mime::JSON);
        $response = new Response(self::SAMPLE_JSON_RESPONSE, self::SAMPLE_JSON_HEADER, $req);
        static::assertSame('application/json', $response->getHeaders()['Content-Type'][0]);
    }

    public function testParseHeaders2()
    {
        $parse_headers = Response\Headers::fromString(self::SAMPLE_JSON_HEADER);
        static::assertCount(3, $parse_headers);
        static::assertSame('application/json', $parse_headers['Content-Type'][0]);
        static::assertTrue(isset($parse_headers['Connection']));
    }

    public function testParseJSON()
    {
        $handler = new JsonMimeHandler();

        $bodies = [
            'foo',
            [],
            ['foo', 'bar'],
            null,
        ];
        foreach ($bodies as $body) {
            static::assertSame($body, $handler->parse((string) \json_encode($body)));
        }

        try {
            /** @noinspection OnlyWritesOnParameterInspection */
            /** @noinspection PhpUnusedLocalVariableInspection */
            $result = $handler->parse('invalid{json');
        } catch (\Httpful\Exception\JsonParseException $e) {
            static::assertSame('Unable to parse response as JSON: ' . \json_last_error_msg() . ' | "invalid{json"', $e->getMessage());

            return;
        }

        static::fail('Expected an exception to be thrown due to invalid json');
    }

    public function testParsingContentTypeCharset()
    {
        $req = (new Request())->mime(Mime::JSON);
        $response = new Response(
            self::SAMPLE_JSON_RESPONSE,
            "HTTP/1.1 200 OK
Content-Type: text/plain; charset=utf-8\r\n",
            $req
        );
        static::assertSame($response->getHeaders()['Content-Type'][0], 'text/plain; charset=utf-8');
        static::assertSame($response->getContentType(), 'text/plain');
        static::assertSame($response->getCharset(), 'utf-8');
    }

    public function testParsingContentTypeUpload()
    {
        $req = new Request();

        $req->contentType(Mime::UPLOAD);
        static::assertSame($req->getContentType(), 'multipart/form-data');
    }

    public function testRawHeaders()
    {
        $req = (new Request())->mime(Mime::JSON);
        $response = new Response(self::SAMPLE_JSON_RESPONSE, self::SAMPLE_JSON_HEADER, $req);
        static::assertContains('Content-Type: application/json', $response->getRawHeaders());
    }

    public function testmimeType()
    {
        $r = (new Request())
            ->mimeType(Mime::JSON);
        static::assertSame(Mime::JSON, $r->getExpectedType());
        static::assertSame(Mime::JSON, $r->getContentType());

        $r = (new Request())
            ->mimeType('html');
        static::assertSame(Mime::HTML, $r->getExpectedType());
        static::assertSame(Mime::HTML, $r->getContentType());

        $r = (new Request())
            ->mimeType('form');
        static::assertSame(Mime::FORM, $r->getExpectedType());
        static::assertSame(Mime::FORM, $r->getContentType());

        $r = (new Request())
            ->mimeType('application/x-www-form-urlencoded');
        static::assertSame(Mime::FORM, $r->getExpectedType());
        static::assertSame(Mime::FORM, $r->getContentType());

        $r = (new Request())
            ->mimeType(Mime::CSV);
        static::assertSame(Mime::CSV, $r->getExpectedType());
        static::assertSame(Mime::CSV, $r->getContentType());
    }

    public function testSettingStrictSsl()
    {
        $r = (new Request())
            ->enableStrictSSL();

        static::assertTrue($r->isStrictSSL());

        $r = (new Request())
            ->disableStrictSSL();

        static::assertFalse($r->isStrictSSL());
    }

    public function testShortMime()
    {
        // Valid short ones
        static::assertSame(Mime::JSON, Mime::getFullMime('json'));
        static::assertSame(Mime::XML, Mime::getFullMime('xml'));
        static::assertSame(Mime::HTML, Mime::getFullMime('html'));
        static::assertSame(Mime::CSV, Mime::getFullMime('csv'));
        static::assertSame(Mime::UPLOAD, Mime::getFullMime('upload'));

        // Valid long ones
        static::assertSame(Mime::JSON, Mime::getFullMime(Mime::JSON));
        static::assertSame(Mime::XML, Mime::getFullMime(Mime::XML));
        static::assertSame(Mime::HTML, Mime::getFullMime(Mime::HTML));
        static::assertSame(Mime::CSV, Mime::getFullMime(Mime::CSV));
        static::assertSame(Mime::UPLOAD, Mime::getFullMime(Mime::UPLOAD));

        // No false positives
        static::assertNotSame(Mime::XML, Mime::getFullMime(Mime::HTML));
        static::assertNotSame(Mime::JSON, Mime::getFullMime(Mime::XML));
        static::assertNotSame(Mime::HTML, Mime::getFullMime(Mime::JSON));
        static::assertNotSame(Mime::XML, Mime::getFullMime(Mime::CSV));
    }

    public function testShorthandMimeDefinition()
    {
        $r = (new Request())->expectsType('json');
        static::assertSame(Mime::JSON, $r->getExpectedType());

        $r = (new Request())->expectsJson();
        static::assertSame(Mime::JSON, $r->getExpectedType());
    }

    public function testTimeout()
    {
        try {
            (new Request())
                ->setUriFromString(self::TIMEOUT_URI)
                ->timeout(0.1)
                ->send();
        } catch (NetworkErrorException $e) {
            static::assertInternalType('resource', $e->getCurlObject()->curl);
            static::assertTrue($e->wasTimeout());

            return;
        }

        static::assertFalse(true);
    }

    public function testToString()
    {
        $req = (new Request())->mime(Mime::JSON);
        $response = new Response(self::SAMPLE_JSON_RESPONSE, self::SAMPLE_JSON_HEADER, $req);
        static::assertSame(self::SAMPLE_JSON_RESPONSE, (string) $response);
    }

    public function testUserAgentGet()
    {
        $r = Request::get('http://example.com/')
            ->withUserAgent('ACME/1.2.3');

        static::assertArrayHasKey('User-Agent', $r->getHeaders());
        $r->_curlPrep();
        static::assertContains('User-Agent: ACME/1.2.3', $r->getRawHeaders());
        static::assertNotContains('User-Agent: HttpFul/1.0', $r->getRawHeaders());

        $r = Request::get('http://example.com/')
            ->withUserAgent('');

        static::assertArrayHasKey('User-Agent', $r->getHeaders());
        $r->_curlPrep();
        static::assertContains('User-Agent:', $r->getRawHeaders());
        static::assertNotContains('User-Agent: HttpFul/1.0', $r->getRawHeaders());
    }

    public function testWhenError()
    {
        $caught = false;

        try {
            /** @noinspection PhpUnusedParameterInspection */
            Request::get('malformed:url')
                ->setErrorHandler(
                    static function ($error) use (&$caught) {
                        $caught = true;
                    }
                )
                ->timeout(0.1)
                ->send();
        } catch (NetworkErrorException $e) {
        }

        static::assertTrue($caught);
    }

    public function testXMLResponseParse()
    {
        $req = (new Request())->mime(Mime::XML);
        $response = new Response(self::SAMPLE_XML_RESPONSE, self::SAMPLE_XML_HEADER, $req);
        $sxe = $response->getRawBody();
        static::assertSame('object', \gettype($sxe));
        static::assertSame(\SimpleXMLElement::class, \get_class($sxe));
        $bools = $sxe->xpath('/stdClass/boolProp');
        foreach ($bools as $bool) {
            static::assertSame('TRUE', (string) $bool);
        }
        $ints = $sxe->xpath('/stdClass/arrayProp/array/k1/myClass/intProp');
        foreach ($ints as $int) {
            static::assertSame('2', (string) $int);
        }
        $strings = $sxe->xpath('/stdClass/stringProp');
        foreach ($strings as $string) {
            static::assertSame('a string', (string) $string);
        }
    }
}

/** @noinspection PhpMultipleClassesDeclarationsInOneFile */

/**
 * Class DemoMimeHandler
 */
class DemoDefaultMimeHandler extends DefaultMimeHandler
{
    /** @noinspection PhpMissingParentCallCommonInspection */

    /**
     * @param string $body
     *
     * @return string
     */
    public function parse($body)
    {
        return 'custom parse';
    }
}
