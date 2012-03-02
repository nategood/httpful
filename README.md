# Httpful

[Httpful](http://phphttpclient.com) is a simple Http Client library for PHP 5.3+.  There is an emphasis of readability, simplicity, and flexibility – basically provide the features and flexibility to get the job done and make those features really easy to use.

Features

 - Readable HTTP Method Support (GET, PUT, POST, DELETE, HEAD, and OPTIONS)
 - Custom Headers
 - Automatic "Smart" Parsing
 - Automatic Payload Serialization
 - Basic Auth
 - Client Side Certificate Auth
 - Request "Templates"

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

# Show Me More!

You can checkout the [Httpful Landing Page](http://phphttpclient.com) for more info including many examples and  [documentation](http:://phphttpclient.com/docs).