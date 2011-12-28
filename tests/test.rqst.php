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

require('lib/class.rqst.php');

define('TEST_SERVER', '127.0.0.1:8008');
define('TEST_URL', 'http://' . TEST_SERVER);

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
  $r = Rqst::init();
  // Did we get a 'Rqst' object?
  assert('Rqst' === get_class($r));
}

function testMethods() {
  $valid_methods = array('get', 'post', 'delete', 'put', 'options', 'head');
  $url = 'http://example.com/';
  foreach ($valid_methods as $method) {
    $r = call_user_func(array('Rqst', $method), $url);
    assert('Rqst' === get_class($r));
    // var_dump($method);var_dump($r->method);
    assert(strtoupper($method) === $r->method);
  }
}

function testDefaults() {
    // Our current defaults are as follows
    $r = Rqst::init();
    // var_dump($r);
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
    $r = Rqst::init()
         ->withStrictSsl();

    assert(true === $r->strict_ssl);

    $r = Rqst::init()
         ->withoutStrictSsl();

    assert(false === $r->strict_ssl);
}

function testSendsAndExpectsType() {
    $r = Rqst::init()
        ->sendsAndExpectsType(Mime::JSON);
    assert(Mime::JSON === $r->expected_type);
    assert(Mime::JSON === $r->content_type);

    $r = Rqst::init()
        ->sendsAndExpectsType('html');
    assert(Mime::HTML === $r->expected_type);
    assert(Mime::HTML === $r->content_type);

    $r = Rqst::init()
        ->sendsAndExpectsType('form');
    assert(Mime::FORM === $r->expected_type);
    assert(Mime::FORM === $r->content_type);

    $r = Rqst::init()
        ->sendsAndExpectsType('application/x-www-form-urlencoded');
    assert(Mime::FORM === $r->expected_type);
    assert(Mime::FORM === $r->content_type);
}

function testIni() {
    // Test setting defaults/templates

    // Create the template
    $template = Rqst::init()
        ->method(Http::POST)
        ->withStrictSsl()
        ->expectsType(Mime::HTML)
        ->sendsType(Mime::FORM);

    Rqst::ini($template);

    $r = Rqst::init();

    assert(true === $r->strict_ssl);
    assert(Http::POST === $r->method);
    assert(Mime::HTML === $r->expected_type);
    assert(Mime::FORM === $r->content_type);

    // Test the default accessor as well
    assert(true === Rqst::d('strict_ssl'));
    assert(Http::POST === Rqst::d('method'));
    assert(Mime::HTML === Rqst::d('expected_type'));
    assert(Mime::FORM === Rqst::d('content_type'));

    Rqst::resetIni();
}

function testAuthSetup() {
    $username = 'nathan';
    $password = 'openseasame';

    $r = Rqst::get('http://example.com/')
        ->authenticateWith($username, $password);

    assert($username === $r->username);
    assert($password === $r->password);
    assert(true === $r->hasBasicAuth());
}

function testJsonResponseParse() {
    $req = Rqst::init()->sendsAndExpects(Mime::JSON);
    $response = new Response('{"key":"value","object":{"key":"value"},"array":[1,2,3,4]}', array(), $req);

    assert("value" === $response->body->key);
    assert("value" === $response->body->object->key);
    assert(is_array($response->body->array));
    assert(1 === $response->body->array[0]);
}

function testCustomHeader() {
    $value = "custom header value";
    $r = Rqst::init()
        ->withXCustomHeader($value);
    assert($value, $r->headers['X-Custom-Header']);
}

// Tests that require the test server to be running
// The test server basically just echoes what it
// receives in a JSON response in the format of:
// {requestMethod:"", requestHeaders:{}, requestBody: ""}
function testSendGet() {
    $response =
        Rqst::get(TEST_URL)
            ->expects(Mime::JSON)
            ->sendIt();
    assert(empty($response->body->requestBody));
    assert(Http::GET === $response->body->requestMethod);
}

function testSendPost() {
    $response =
      Rqst::post(TEST_URL)
        ->sendsAndExpects(Mime::JSON)
        ->body(array("key" => "value"))
        ->sendIt();

    assert('{"key":"value"}' === $response->body->requestBody);
    assert(Http::POST === $response->body->requestMethod);
}

function testSendPut() {
    $response =
      Rqst::put(TEST_URL)
        ->sendsAndExpects(Mime::JSON)
        ->body(array("key" => "value"))
        ->sendIt();

    assert('{"key":"value"}' === $response->body->requestBody);
    assert(Http::PUT === $response->body->requestMethod);

    $response =
      Rqst::put(TEST_URL)
        ->sendsAndExpects(Mime::JSON)
        ->body('{"key":"value"}')
        ->sendIt();
    assert('{"key":"value"}' === $response->body->requestBody);
}

function testSendDelete() {
    $response =
        Rqst::delete(TEST_URL)
            ->expects(Mime::JSON)
            ->sendIt();

    assert(Http::DELETE === $response->body->requestMethod);
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

checkForTestServer();

testSendGet();
testSendPost();
testSendPut();
testSendDelete();
