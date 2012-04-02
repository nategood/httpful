# Httpful

[Httpful](http://phphttpclient.com) is a simple Http Client library for PHP 5.3+.  There is an emphasis of readability, simplicity, and flexibility – basically provide the features and flexibility to get the job done and make those features really easy to use.

Features

 - Readable HTTP Method Support (GET, PUT, POST, DELETE, HEAD, PATCH and OPTIONS)
 - Custom Headers
 - Automatic "Smart" Parsing
 - Automatic Payload Serialization
 - Basic Auth
 - Client Side Certificate Auth
 - Request "Templates"

# Sneak Peak

Here's something to whet your appetite.  Fire off a GET request to FreeBase API to find albums by The Dead Weather.  Notice, we expect the data returned to be JSON formatted and the library parses it nicely into a native PHP object.

    $uri = "https://www.googleapis.com/freebase/v1/mqlread?query=%7B%22type%22:%22/music/artist%22%2C%22name%22:%22The%20Dead%20Weather%22%2C%22album%22:%5B%5D%7D";
    
    $response = Request::get($uri)->expectsJson()->sendIt();
    echo 'The Dead Weather has ' . count($response->body->result->album) . ' albums.';

# Installation

## Phar

A [PHP Archive](http://php.net/manual/en/book.phar.php) (or .phar) file is available for [downloading](https://github.com/downloads/nategood/httpful/httpful.phar).  Simply [download](https://github.com/downloads/nategood/httpful/httpful.phar) the .phar, drop it into your project, and include it like you would any other php file.  _This method is ideal smaller projects, one off scripts, and quick API hacking_.

    <?php
    include('httpful.phar');
    $r = \Httpful\Request::get($uri)->sendIt();
    ...
    
## Composer

Httpful is PSR-0 compliant and can be installed using [composer](http://getcomposer.org/).  Simply add `nategood/httpful` to your composer.json file.  _Composer is the sane alternative to PEAR.  It is excellent for managing dependancies in larger projects_.

    {
        "require": {
            "nategood/httpful": "*"
        }
    }

## Install from Source

Because Httpful is PSR-0 compliant, you can also just clone the Httpful repository and use a PSR-0 compatible autoloader to load the library, like [Symfony's](http://symfony.com/doc/current/components/class_loader.html). Alternatively you can use the PSR-0 compliant autoloader included with the Httpful (simply `require("bootstrap.php")`).

# Show Me More!

You can checkout the [Httpful Landing Page](http://phphttpclient.com) for more info including many examples and  [documentation](http:://phphttpclient.com/docs).