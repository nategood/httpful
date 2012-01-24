# Httpful

Httpful is a simple Http Client library for PHP 5.3+.  There is an emphasis of readability without losing concise syntax.  As such, you will notice that the library lends itself very nicely to "chaining".  

# Use it

Basic example.  Fire off a GET request to FreeBase API to find albums by The Dead Weather.  Notice, we expect the data returned to be JSON formatted and the library parses it nicely.

    $uri = "https://www.googleapis.com/freebase/v1/mqlread?query=%7B%22type%22:%22/music/artist%22%2C%22name%22:%22The%20Dead%20Weather%22%2C%22album%22:%5B%5D%7D";
    $response = \Httpful\Request::get($uri)
        ->expectsType(\Httpful\Mime::JSON)
        ->sendIt();
    echo 'The Dead Weather has ' . count($response->body->result->album) . ' albums.';

*For more details, checkout the examples directory*

# Features

## Chainability

The library allows "chaining" to build up your requests.  Need to add on a content-type?  HTTP body?  Custom header?  Just tack it on

## Custom Headers

The library allows for custom headers without sacrificing readability.  Simply chain another method on to your request with the "key" of the header as the method name (in camel case) and the value of the header as that method's argument.  Let's add in two custom additional headers, X-Example-Header and X-Another-Header:

    $response = Httpful\Request::get($uri)
        ->expectsType(Httpful\Mime::JSON)
        ->xExampleHeader("My Value")            // Add in a custom header X-Example-Header
        ->withXAnotherHeader("Another Value")   // Sugar: You can also prefix the method with "with"
        ->sendIt();

## Smart Parsing

If you expect (and get) a response in a supported format (JSON, Form Url Encoded, XML Soon), the response will automatically parse that response into a useful response object.  For example, our "Dead Weather" example above was a JSON response, however the library parsed that request and converted into a useful object.  If the text is not supported by the internal parsing, it simply gets returned as a string.

    // JSON
    $response = Httpful\Request::get($uri)
        ->expectsType(Httpful\Mime::JSON)
        ->sendIt();
    
    // If the JSON response is {"scalar":1,"object":{"scalar":2}}
    echo $response->body->scalar;           // prints 1
    echo $response->body->object->scalar;   // prints 5

## Request Templates

Often, if we are working with an API, a lot of the headers we send to that API remain the same (e.g. the expected mime type, authentication headers).  Usually it ends up in writing boiler plate code to get around this.  Httpful solves this problem by letting you create "template" requests.  Subsequent requests will by default use the headers and settings of that template request.

    // Create the template
    $template = Request::init()
        ->method(Http::POST)
        ->withStrictSsl()
        ->expectsType(Mime::HTML)
        ->sendsType(Mime::FORM);
    
    // Set it as a template
    Request::ini($template);
    
    // This new request will have all the settings 
    // of our template by default.  We can override
    // any of these settings.
    $r = Request::init();

# Notes about Source Code
You will see several "alias" methods: more readable method definitions that wrap their more concise counterparts.  You will also notice no public constructor.  This too adds to the readability and "chainabilty" of the library.

# Testing

Because this is a HTTP Client library, to thoroughly test it, we need an HTTP server.  I included a basic node.js server that takes an HTTP request, serializes it and spits it back out.  See `tests/runTestServer.js` and `tests/httpful.test.php`.

# Todo

 - Add XML parsing support out of the box
 - Register a callback to handle custom MIME types
 - Register a callback to handle errors
 - Support SSL Client Side Cert Authentication