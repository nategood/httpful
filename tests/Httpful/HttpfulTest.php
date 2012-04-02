<?php

require('../../downloads/httpful.phar');
Bootstrap::init();

namespace Httpful\Test;

use Httpful\Request;
use Httpful\Mime;

class HttpfulTest extends \PHPUnit_Framework_TestCase 
{
    const TEST_SERVER = '127.0.0.1:8008';
    const TEST_URL = 'http://' . self::TEST_SERVER;
    const TEST_URL_400 = 'http://' . self::TEST_SERVER . '/400';
    
    const SAMPLE_JSON_RESPONSE = '{"key":"value","object":{"key":"value"},"array":[1,2,3,4]}';
    const SAMPLE_JSON_HEADER = "HTTP/1.1 200 OK
Content-Type: application/json
Connection: keep-alive
Transfer-Encoding: chunked";
    const SAMPLE_XML_RESPONSE = '<stdClass><arrayProp><array><k1><myClass><intProp>2</intProp></myClass></k1></array></arrayProp><stringProp>a string</stringProp><boolProp>TRUE</boolProp></stdClass>');
    const SAMPLE_XML_HEADER = "HTTP/1.1 200 OK
Content-Type: application/xml
Connection: keep-alive
Transfer-Encoding: chunked";
    
    function testInit()
    {
      $r = Request::init();
      // Did we get a 'Request' object?
      assert('Httpful\Request' === get_class($r));
    }
    
    function testMethods()
    {
      $valid_methods = array('get', 'post', 'delete', 'put', 'options', 'head');
      $url = 'http://example.com/';
      foreach ($valid_methods as $method) {
        $r = call_user_func(array('Httpful\Request', $method), $url);
        $this->assertEquals('Httpful\Request', get_class($r));
        $this->assertEquals(strtoupper($method), $r->method);
      }
    }

    function testDefaults()
    {
        // Our current defaults are as follows
        $r = Request::init();
        $this->assertEquals(Http::GET, $r->method);
        $this->assertFalse($r->strict_ssl);
    }
    
    function testShortMime()
    {
        // Valid short ones
        $this->assertEquals(Mime::JSON,  Mime::getFullMime('json'));
        $this->assertEquals(Mime::XML,   Mime::getFullMime('xml'));
        $this->assertEquals(Mime::HTML,  Mime::getFullMime('html'));

        // Valid long ones
        $this->assertEquals(Mime::JSON, Mime::getFullMime(Mime::JSON));
        $this->assertEquals(Mime::XML,  Mime::getFullMime(Mime::XML));
        $this->assertEquals(Mime::HTML, Mime::getFullMime(Mime::HTML));

        // No false positives
        $this->assertEquals(Mime::XML,  Mime::getFullMime(Mime::HTML));
        $this->assertEquals(Mime::JSON, Mime::getFullMime(Mime::XML));
        $this->assertEquals(Mime::HTML, Mime::getFullMime(Mime::JSON));
    }

    function testSettingStrictSsl()
    {
        $r = Request::init()
             ->withStrictSsl();

        $this->assertTrue($r->strict_ssl);

        $r = Request::init()
             ->withoutStrictSsl();

        $this->assertFalse($r->strict_ssl);
    }
    
    function testSendsAndExpectsType()
    {
        $r = Request::init()
            ->sendsAndExpectsType(Mime::JSON);
        $this->assertEquals(Mime::JSON, $r->expected_type);
        $this->assertEquals(Mime::JSON, $r->content_type);

        $r = Request::init()
            ->sendsAndExpectsType('html');
        $this->assertEquals(Mime::HTML, $r->expected_type);
        $this->assertEquals(Mime::HTML, $r->content_type);

        $r = Request::init()
            ->sendsAndExpectsType('form');
        $this->assertEquals(Mime::FORM, $r->expected_type);
        $this->assertEquals(Mime::FORM, $r->content_type);

        $r = Request::init()
            ->sendsAndExpectsType('application/x-www-form-urlencoded');
        $this->assertEquals(Mime::FORM, $r->expected_type);
        $this->assertEquals(Mime::FORM, $r->content_type);
    }
    
    function testInit()
    {
        // Test setting defaults/templates

        // Create the template
        $template = Request::init()
            ->method(Http::POST)
            ->withStrictSsl()
            ->expectsType(Mime::HTML)
            ->sendsType(Mime::FORM);

        Request::ini($template);

        $r = Request::init();

        $this->assertTrue($r->strict_ssl);
        $this->assertEquals(Http::POST, $r->method);
        $this->assertEquals(Mime::HTML, $r->expected_type);
        $this->assertEquals(Mime::FORM, $r->content_type);

        // Test the default accessor as well
        $this->assertTrue(Request::d('strict_ssl'));
        $this->assertEquals(Http::POST, Request::d('method'));
        $this->assertEquals(Mime::HTML, Request::d('expected_type'));
        $this->assertEquals(Mime::FORM, Request::d('content_type'));

        Request::resetIni();
    }
    
    function testAuthSetup()
    {
        $username = 'nathan';
        $password = 'opensesame';

        $r = Request::get('http://example.com/')
            ->authenticateWith($username, $password);

        $this->assertEquals($username, $r->username);
        $this->assertEquals($password, $r->password);
        $this->assertTrue($r->hasBasicAuth());
    }

    function testJsonResponseParse()
    {
        $req = Request::init()->sendsAndExpects(Mime::JSON);
        $response = new Response(self::SAMPLE_JSON_RESPONSE, self::SAMPLE_JSON_HEADER, $req);

        $this->assertEquals("value", $response->body->key);
        $this->assertEquals("value", $response->body->object->key);
        $this->assertInternalType('array', $response->body->array);
        $this->assertEquals(1, $response->body->array[0]);
    }
    
    function testXMLResponseParse()
    {
        $req = Request::init()->sendsAndExpects(Mime::XML);
        $response = new Response(self::SAMPLE_XML_RESPONSE, self::SAMPLE_XML_HEADER, $req);
        $sxe = $response->body;
        $this->assertEquals("object", gettype($sxe));
        $this->assertEquals("SimpleXMLElement", get_class($sxe));
        $bools = $sxe->xpath('/stdClass/boolProp');
        list( , $bool ) = each($bools);
        $this->assertEquals("TRUE", (string) $bool);
        $ints = $sxe->xpath('/stdClass/arrayProp/array/k1/myClass/intProp');
        list( , $int ) = each($ints);
        $this->assertEquals("2", (string) $int);
        $strings = $sxe->xpath('/stdClass/stringProp');
        list( , $string ) = each($strings);
        $this->assertEquals("a string", (string) $string);
    }
    
    function testParsingContentTypeCharset()
    {
        $req = Request::init()->sendsAndExpects(Mime::JSON);
        // $response = new Response(SAMPLE_JSON_RESPONSE, "", $req);
        // // Check default content type of iso-8859-1
        $response = new Response(self::SAMPLE_JSON_RESPONSE, "HTTP/1.1 200 OK
Content-Type: text/plain; charset=utf-8", $req);
        $this->assertInternalType('array', $response->headers);
        $this->assertEquals($response->headers['Content-Type'], 'text/plain; charset=utf-8');
        $this->assertEquals($response->content_type, 'text/plain');
        $this->assertEquals($response->charset, 'utf-8');
    }

    function testNoAutoParse()
    {
        $req = Request::init()->sendsAndExpects(Mime::JSON)->withoutAutoParsing();
        $response = new Response(self::SAMPLE_JSON_RESPONSE, self::SAMPLE_JSON_HEADER, $req);
        $this->assertInternalType('string', $response->body);
        $req = Request::init()->sendsAndExpects(Mime::JSON)->withAutoParsing();
        $response = new Response(self::SAMPLE_JSON_RESPONSE, self::SAMPLE_JSON_HEADER, $req);
        $this->assertInternalType('object', $response->body);
    }
}