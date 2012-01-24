# Httpful

Httpful is a simple Http Client library for PHP 5.3+.  There is an emphasis of readability without loosing concise syntax.  As such, you will notice that the library lends itself very nicely to "chaining".  

# Use it

Basic example.  Fire off a GET request to FreeBase API to find albums by The Dead Weather.  Notice, we expect the data returned to be JSON and the library parses it nicely.

    
    $uri = "https://www.googleapis.com/freebase/v1/mqlread?query=%7B%22type%22:%22/music/artist%22%2C%22name%22:%22The%20Dead%20Weather%22%2C%22album%22:%5B%5D%7D";
    $response = \Httpful\Request::get($uri)
        ->expectsType(\Httpful\Mime::JSON)
        ->sendIt();
    echo 'The Dead Weather has ' . count($response->result->album) . ' albums.';

*For more details, checkout the examples directory*

# About the Library
You will see several "alias" methods: more readable method definitions that wrap their more concise counterparts.  You will also notice no public constructor.  This two adds to the readability and "chainabilty" of the library.

# More features

## Chainability

The library allows "chaining" to build up your requests.  Need to add on a content-type?  HTTP body?  Custom header?  Just tack it on

## Custom Headers

The library allows for custom headers without sacrificing readability.  Simply chain another method on to your request with the "key" of the header as the method name (in camel case) and the value of the header as that method's argument.  Let's add in an example header X-Example-Header

    $response = Httpful\Request::get($uri)
        ->expectsType(Httpful\Mime::JSON)
        ->xExampleHeader("My Value")            // Add in a custom header X-Example-Header
        ->sendIt();

## Smart Parsing

If you expect (and get) a response in a supported format (JSON, Form Url Encoded, XML Soon), the response will automatically parse that response into a useful response object.  For example, our "Dead Weather" example above was a JSON response, however the library parsed that request and converted into a useful object.

# Testing

Because this is a HTTP Client library, to thoroughly test it, we need an HTTP server.  I included a basic node.js server that takes an HTTP request, serializes it and spits it back out.  See `tests/runTestServer.js` and `tests/httpful.test.php`.