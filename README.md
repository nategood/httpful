# Httpful

Httpful is a simple Http Client library for PHP 5.3+.  There is an emphasis of readability, simplicity, and flexibility – basically provide the features and flexibility to get the job done and make those features really easy to use.

# Sneak Peak

Here's something to whet your appetite.  Fire off a GET request to FreeBase API to find albums by The Dead Weather.  Notice, we expect the data returned to be JSON formatted and the library parses it nicely into a native PHP object.

    use Httpful\Request;
    $uri = "https://www.googleapis.com/freebase/v1/mqlread?query=%7B%22type%22:%22/music/artist%22%2C%22name%22:%22The%20Dead%20Weather%22%2C%22album%22:%5B%5D%7D";
    
    $response = Request::get($uri)->expectsJson()->sendIt();
    echo 'The Dead Weather has ' . count($response->body->result->album) . ' albums.';

# Install

There are two options to get up and running.  The first is the usual `git clone` + PSR-0 route and the second is a single file download route perfect for quick hacking.

## Quick Install

For these quick one off scripts, the easiest way to get started is to simply include the library as a single [file available in GitHub downloads](https://github.com/downloads/nategood/httpful/httpful.php). 

    <?php
    include('./httpful.php');
    $r = \Httpful\Request::get($uri)->sendIt();
    ...
    
## Usual Install

The library also provides a more traditional PSR-0 compliant option.  `git clone` the repo into your vendors directory.  If your project isn't already using a compatible autoloader, the library includes a very basic autoloader that you can use by just including the `bootstrap.php` file.

*For more examples, checkout the examples directory*

# Features

## Chaining

Anyone that has used jQuery can attest to the awesomeness and conciseness of "chaining".  Httpful allows similar chaining to neatly build up your requests.  Need to specify a content-type?  Tack on an HTTP body?  Custom header?  Just keep tacking them on to your initial request object.

## Make the Important Stuff Easy

Choosing HTTP methods beyond GET and POST, quickly setting `Content-Type`, setting request payloads, authenticating... these are the things we should be able to do quickly.  Httpful library makes those things easy using the aforementioned chaining.  Let's show a quick example doing a few of our favorite things.

    $response = \Httpful\Request::put($uri)         // Build a PUT request...
        ->sendsJson()                               // tell it we're sending (Content-Type) JSON...
        ->authenticateWith('username', 'password')  // authenticate with basic auth...
        ->body('{"json":"is awesome"}')             // attach a body/payload...
        ->sendIt();                                 // and finally, fire that thing off!

## Custom Headers

The library allows for custom headers without sacrificing readability.  Simply chain another method on to your request with the "key" of the header as the method name (in camel case) and the value of the header as that method's argument.  Let's add in two custom additional headers, X-Example-Header and X-Another-Header:

    $response = \Httpful\Request::get($uri)
        ->expectsJson()
        ->xExampleHeader("My Value")                // Add in a custom header X-Example-Header
        ->withXAnotherHeader("Another Value")       // Sugar: You can also prefix the method with "with"
        ->sendIt();

## Smart Parsing

If you expect (and get) a response in a supported format (JSON, Form Url Encoded, XML and YAML Soon), the Httpful will automatically parse that body of the response into a useful response object.  For instance, our "Dead Weather" example above was a JSON response, however the library parsed that request and converted into a useful object.  If the text is not supported by the internal parsing, it simply gets returned as a string.

    // JSON
    $response = \Httpful\Request::get($uri)
        ->expectsJson()
        ->sendIt();
    
    // If the JSON response is {"scalar":1,"object":{"scalar":2}}
    echo $response->body->scalar;           // prints 1
    echo $response->body->object->scalar;   // prints 5

## Custom Parsing

Best of all, if the library doesn't automatically parse your mime type, or if you aren't happy with how the library parses it, you can add in a custom response parser with the `parseWith` method.  Here's a trivial example:

    // Attach our own handler that could naively parse comma 
    // separated values into an array
    $response = \Httpful\Request::get($uri)
        ->parseWith(function($body) {
            return explode(",", $body);
        })
        ->sendIt();
    
    echo "This response had " . count($response) . " values separated via commas";

## Request Templates

Often, if we are working with an API, a lot of the headers we send to that API remain the same (e.g. the expected mime type, authentication headers).  Usually it ends up in writing boiler plate code to get around this.  Httpful solves this problem by letting you create "template" requests.  Subsequent requests will by default use the headers and settings of that template request.

    // Create the template
    $template = \Httpful\Request::init()
        ->method(\Httpful\Http::POST)     // Alternative to Request::post
        ->withoutStrictSsl()              // Ease up on some of the SSL checks
        ->expectsHtml()                   // Expect HTML responses
        ->sendsType(\Httpful\Mime::FORM); // Send application/x-www-form-urlencoded
    
    // Set it as a template
    \Httpful\Request::ini($template);
    
    // This new request will have all the settings 
    // of our template by default.  We can override
    // any of these settings by settings them on this 
    // new instance as we've done with expected type.
    $r = \Httpful\Request::get($uri)->expectsJson();

## Client Side Cert Authentication

In addition to Basic Auth support, Httpful supports [client side cert auth support](http://blog.nategood.com/client-side-certificate-authentication-in-ngi) which is an authentication excellent option for APIs that may have more stringent authentication demands.

    use \Httpful\Request;
    
    $key        = '/path/to/client/key.pem';
    $crt        = '/path/to/client/crt.pem';
    $passphrase = 'for-your-key-if-needed';
    
    $request = Request::get($uri)
        ->authenticateWithCert($cert, $key, $passphrase)
        ->sendIt();

# Notes about Source Code
You will see several "alias" methods: more readable method definitions that wrap their more concise counterparts.  You will also notice no public constructor.  This too adds to the readability and "chainabilty" of the library.

# Testing

Because this is a HTTP Client library, to thoroughly test it, we need an HTTP server.  I included a basic node.js server that takes an HTTP request, serializes it and spits it back out.  See `tests/runTestServer.js` and `tests/httpful.test.php`.

# Todo

See [Issues](https://github.com/nategood/httpful/issues?state=open) for details about the project roadmap and outstanding TODO items