<?php

/**
 * At the time of writing these tests
 * I was on a machine without phpunit
 * and without an internet connection.
 * As a result, I went the old fashion
 * way and just relied on using native
 * php assert functions.  Does the trick
 * and removes a dependency from the project.
 *
 * Because this is a Http library, to test the
 * heavy lifting stuff, I actually needed a
 * HTTP server.  I choose to write one using
 * Node.js because it makes it brain dead easy
 * to do. If you have node installed, run the
 * node server by calling `node tests/runTestServer.js`
 * If the server is not running, all of the non-server
 * -dependent tests will run and the test suite
 * will just short circuit before running the HTTP
 * testing methods.
 */

namespace Httpful;

require(__DIR__ . '/../lib/httpful.php');

define('TEST_SERVER', '127.0.0.1:8008');
define('TEST_URL', 'http://' . TEST_SERVER);
define('TEST_URL_400', 'http://' . TEST_SERVER . '/400');
define('SAMPLE_JSON_RESPONSE', '{"key":"value","object":{"key":"value"},"array":[1,2,3,4]}');
define('SAMPLE_HEADER', "HTTP/1.1 200 OK
Content-Type: application/json
Connection: keep-alive
Transfer-Encoding: chunked");

// Helpers

function checkForTestServer() {
    $fp = @fsockopen(TEST_SERVER);

    if ($fp === false) {
        echo "
===
Unable to connect to test server at " . TEST_SERVER . ".
Can't run the rest of the test suite.
The local test server requires node.js and can be run via `node tests/runTestServer.js`
===
";
        exit(1); // return false;// throw new Exception("Unable to connect to test server at " . TEST_SERVER);
    }

    fclose($fp);
    return true;
}

// Tests

function testInit() {
  $r = Request::init();
  // Did we get a 'Request' object?
  assert('Httpful\Request' === get_class($r));
}

function testMethods() {
  $valid_methods = array('get', 'post', 'delete', 'put', 'options', 'head');
  $url = 'http://example.com/';
  foreach ($valid_methods as $method) {
    $r = call_user_func(array('Httpful\Request', $method), $url);
    assert('Httpful\Request' === get_class($r));
    assert(strtoupper($method) === $r->method);
  }
}

function testDefaults() {
    // Our current defaults are as follows
    $r = Request::init();
    assert(Http::GET === $r->method);
    assert(Mime::JSON === $r->expected_type);
    assert(Mime::JSON === $r->content_type);
    assert(false === $r->strict_ssl);
}

function testShortMime() {
    // Valid short ones
    assert(Mime::JSON === Mime::getFullMime('json'));
    assert(Mime::XML === Mime::getFullMime('xml'));
    assert(Mime::HTML === Mime::getFullMime('html'));

    // Valid long ones
    assert(Mime::JSON === Mime::getFullMime(Mime::JSON));
    assert(Mime::XML === Mime::getFullMime(Mime::XML));
    assert(Mime::HTML === Mime::getFullMime(Mime::HTML));

    // No false positives
    assert(Mime::XML !== Mime::getFullMime(Mime::HTML));
    assert(Mime::JSON !== Mime::getFullMime(Mime::XML));
    assert(Mime::HTML !== Mime::getFullMime(Mime::JSON));
}

function testSettingStrictSsl() {
    $r = Request::init()
         ->withStrictSsl();

    assert(true === $r->strict_ssl);

    $r = Request::init()
         ->withoutStrictSsl();

    assert(false === $r->strict_ssl);
}

function testSendsAndExpectsType() {
    $r = Request::init()
        ->sendsAndExpectsType(Mime::JSON);
    assert(Mime::JSON === $r->expected_type);
    assert(Mime::JSON === $r->content_type);

    $r = Request::init()
        ->sendsAndExpectsType('html');
    assert(Mime::HTML === $r->expected_type);
    assert(Mime::HTML === $r->content_type);

    $r = Request::init()
        ->sendsAndExpectsType('form');
    assert(Mime::FORM === $r->expected_type);
    assert(Mime::FORM === $r->content_type);

    $r = Request::init()
        ->sendsAndExpectsType('application/x-www-form-urlencoded');
    assert(Mime::FORM === $r->expected_type);
    assert(Mime::FORM === $r->content_type);
}

function testIni() {
    // Test setting defaults/templates

    // Create the template
    $template = Request::init()
        ->method(Http::POST)
        ->withStrictSsl()
        ->expectsType(Mime::HTML)
        ->sendsType(Mime::FORM);

    Request::ini($template);

    $r = Request::init();

    assert(true === $r->strict_ssl);
    assert(Http::POST === $r->method);
    assert(Mime::HTML === $r->expected_type);
    assert(Mime::FORM === $r->content_type);

    // Test the default accessor as well
    assert(true === Request::d('strict_ssl'));
    assert(Http::POST === Request::d('method'));
    assert(Mime::HTML === Request::d('expected_type'));
    assert(Mime::FORM === Request::d('content_type'));

    Request::resetIni();
}

function testAuthSetup() {
    $username = 'nathan';
    $password = 'opensesame';

    $r = Request::get('http://example.com/')
        ->authenticateWith($username, $password);

    assert($username === $r->username);
    assert($password === $r->password);
    assert(true === $r->hasBasicAuth());
}

function testJsonResponseParse() {
    $req = Request::init()->sendsAndExpects(Mime::JSON);
    $response = new Response(SAMPLE_JSON_RESPONSE, SAMPLE_HEADER, $req);

    assert("value" === $response->body->key);
    assert("value" === $response->body->object->key);
    assert(is_array($response->body->array));
    assert(1 === $response->body->array[0]);
}

function testSerializePayloadOptions() {
    $req = Request::get(TEST_URL)
        ->sendsJson()
        ->body("Nathan")
        ->alwaysSerializePayload();

    $res = $req->send();
    
    // Rare, but perfect example of why you might want 
    // to use alwaysSerializePayload
    
    assert(is_string($req->serialized_payload));
    assert('"Nathan"' === $req->serialized_payload);
    
    $req = Request::get(TEST_URL)
        ->sendsJson()
        ->body("Nathan")
        ->neverSerializePayload();
    
    $res = $req->send();
    assert(is_string($req->serialized_payload));
    assert('Nathan' === $req->serialized_payload);
    
}

function testCustomPayloadSerializer($mime_type) {
    $req = Request::get(TEST_URL)
        ->sendsJson()
        ->body("Nathan")
        ->alwaysSerializePayload()
        ->registerPayloadSerializer($mime_type, function($payload) {
            // Screw it.  Our trivial serializer just
            // always returns the word regardless of 
            // the $payload
            return 'Apples';
        });

    $res = $req->send();
    assert('Apples' === $req->serialized_payload);
}

function testParsingContentTypeCharset() {
    $req = Request::init()->sendsAndExpects(Mime::JSON);
    // $response = new Response(SAMPLE_JSON_RESPONSE, "", $req);
    // // Check default content type of iso-8859-1
    $response = new Response(SAMPLE_JSON_RESPONSE, "HTTP/1.1 200 OK
Content-Type: text/plain; charset=utf-8", $req);
    assert(is_array($response->headers));
    assert($response->headers['Content-Type'] === 'text/plain; charset=utf-8');
    assert($response->content_type === 'text/plain');
    assert($response->charset === 'utf-8');
}

function testNoAutoParse() {
    $req = Request::init()->sendsAndExpects(Mime::JSON)->withoutAutoParsing();
    $response = new Response(SAMPLE_JSON_RESPONSE, SAMPLE_HEADER, $req);
    assert(is_string($response->body));
    $req = Request::init()->sendsAndExpects(Mime::JSON)->withAutoParsing();
    $response = new Response(SAMPLE_JSON_RESPONSE, SAMPLE_HEADER, $req);
    assert(is_object($response->body));
}

function testSendsSugar() {
    $req = Request::init()->sendsJson();
    $req2 = Request::init()->sends(Mime::JSON);
    assert($req2->content_type === $req->content_type);
    assert($req->content_type === 'application/json');
}

function testExpectsSugar() {
    $req = Request::init()->expectsJson();
    $req2 = Request::init()->expects(Mime::JSON);
    assert($req2->expected_type === $req->expected_type);
    assert($req->expected_type === 'application/json');
}

function testCustomParse() {
    $f = function($body) {
        return $body . $body;
    };

    $req = Request::init()->parseWith($f);
    $raw_body = 'my response text';
    $response = new Response($raw_body, SAMPLE_HEADER, $req);

    assert($raw_body . $raw_body === $response->body);
}

function testCustomHeader() {
    $value = "custom header value";
    $r = Request::init()
        ->withXCustomHeader($value);
    assert($value, $r->headers['X-Custom-Header']);
}

// Tests that require the test server to be running
// The test server basically just echoes what it
// receives in a JSON response in the format of:
// {requestMethod:"", requestHeaders:{}, requestBody: ""}
function testSendGet() {
    $response =
        Request::get(TEST_URL)
            ->expects(Mime::JSON)
            ->sendIt();
    assert(empty($response->body->requestBody));
    assert(Http::GET === $response->body->requestMethod);
}

function testSendPost() {
    $response =
      Request::post(TEST_URL)
        ->sendsAndExpects(Mime::JSON)
        ->body(array("key" => "value"))
        ->sendIt();

    assert('{"key":"value"}' === $response->body->requestBody);
    assert(Http::POST === $response->body->requestMethod);
}

function testSendPut() {
    $response =
      Request::put(TEST_URL)
        ->sendsAndExpects(Mime::JSON)
        ->body(array("key" => "value"))
        ->sendIt();

    assert('{"key":"value"}' === $response->body->requestBody);
    assert(Http::PUT === $response->body->requestMethod);

    $response =
      Request::put(TEST_URL)
        ->sendsAndExpects(Mime::JSON)
        ->body('{"key":"value"}')
        ->sendIt();
    assert('{"key":"value"}' === $response->body->requestBody);
}

function testSendDelete() {
    $response =
        Request::delete(TEST_URL)
            ->expects(Mime::JSON)
            ->sendIt();

    assert(Http::DELETE === $response->body->requestMethod);
}

function testParsingResponseHeaders() {
    $response =
        Request::get(TEST_URL)
            ->expects(Mime::JSON)
            ->sendIt();
    
    assert(is_array($response->headers));
    assert($response->headers['Content-Type'] === 'application/json');
}

function testAddOnCurlOption() {
    // Let's use the NOBODY curl opt
    // to override our post.  This should
    // result in getting no response from the
    // server.
    $req = Request::post(TEST_URL)
        ->body('HELLO')
        ->expects(Mime::HTML)
        ->addOnCurlOption(CURLOPT_NOBODY, true);
    $response = $req->sendIt();
    $bodyWithHeadOverride = $response->raw_body;

    assert(empty($bodyWithHeadOverride));

    // Let's remove it and make sure we
    // get our body back
    $req = Request::post(TEST_URL)
        ->body('HELLO')
        ->expects(Mime::HTML);
    $response = $req->sendIt();
    $bodyWithoutOverride = $response->raw_body;

    assert($bodyWithHeadOverride !== $bodyWithoutOverride);
    assert('HELLO' === json_decode($bodyWithoutOverride)->requestBody);
}

function test200StatusCode() {
    $res = Request::get(TEST_URL)
        ->sendsJson()
        ->sendIt();
    assert(200 === $res->code);
}

function test400StatusCode() {
    $res = Request::get(TEST_URL_400)
        ->sendsJson()
        ->sendIt();
    assert(400 === $res->code);
}

function testStatusCodeParse() {
    $req = Request::get(TEST_URL);
    
    $res = new Response(SAMPLE_JSON_RESPONSE, SAMPLE_HEADER, $req);
    assert(200 === $res->code);
    
    $four_oh_four_headers = "HTTP/1.1 400 OK
Content-Type: application/json";

    $res = new Response(SAMPLE_JSON_RESPONSE, $four_oh_four_headers, $req);
    assert(400 === $res->code);
    
    // Let's make sure we catch malformed HTTP responses
    try {
        $bad_headers = "Wait, this ain't a stinking HTTP response!";
        $res = new Response(SAMPLE_JSON_RESPONSE, $bad_headers, $req);
    } catch(\Exception $e) {
        $yep_caught_it = true;
    }
    assert(true === $yep_caught_it);
}


testInit();
testMethods();
testDefaults();
testShortMime();
testSettingStrictSsl();
testSendsAndExpectsType();
testIni();
testAuthSetup();
testJsonResponseParse();
testCustomParse();
testSendsSugar();
testExpectsSugar();
testNoAutoParse();
testParsingContentTypeCharset();
testStatusCodeParse();

checkForTestServer();

testSendGet();
testSendPost();
testSendPut();
testSendDelete();
testParsingResponseHeaders();
testAddOnCurlOption();
test200StatusCode();
// test400StatusCode();
testSerializePayloadOptions();
testCustomPayloadSerializer('application/json');
testCustomPayloadSerializer('json');
testCustomPayloadSerializer('*');