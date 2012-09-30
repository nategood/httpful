# Httpful

[![Build Status](https://secure.travis-ci.org/nategood/httpful.png?branch=master)](http://travis-ci.org/nategood/httpful)

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

Here's something to whet your appetite.  Search the twitter API for tweets containing "#PHP".  Include a trivial header for the heck of it.  Notice that the library automatically interprets the response as JSON (can override this if desired) and parses it as an array of objects.

    $url = "http://search.twitter.com/search.json?q=" . urlencode('#PHP');
    $response = Request::get($url)
        ->withXTrivialHeader('Just as a demo')
        ->send();

    foreach ($response->body->results as $tweet) {
        echo "@{$tweet->from_user} tweets \"{$tweet->text}\"\n";
    }

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

# Contributing

Httpful highly encourages sending in pull requests.  When submitting a pull request please:

 - All pull requests should target the `dev` branch (not `master`)
 - Make sure your code follows the [coding conventions](http://pear.php.net/manual/en/standards.php)
 - Please use soft tabs (four spaces) instead of hard tabs
 - Make sure you add appropriate test coverage for your changes
 - Run all unit tests in the test directory via `phpunit ./tests`
 - Include commenting where appropriate and add a descriptive pull request message

# Changelog

## 0.1.6

 - Ability to set the number of max redirects via overloading `followRedirects(int max_redirects)`
 - Standards Compliant fix to `Accepts` header
 - Bug fix for bootstrap process when installed via Composer

## 0.1.5

 - Use `DIRECTORY_SEPARATOR` constant [PR #33](https://github.com/nategood/httpful/pull/32)
 - [PR #35](https://github.com/nategood/httpful/pull/35)
 - Added the raw_headers property reference to response.
 - Compose request header and added raw_header to Request object.
 - Fixed response has errors and added more comments for clarity.
 - Fixed header parsing to allow the minimum (status line only) and also cater for the actual CRLF ended headers as per RFC2616.
 - Added the perfect test Accept: header for all Acceptable scenarios see  @b78e9e82cd9614fbe137c01bde9439c4e16ca323 for details.
 - Added default User-Agent header
  - `User-Agent: Httpful/0.1.5` + curl version + server software + PHP version
 - To bypass this "default" operation simply add a User-Agent to the request headers even a blank User-Agent is sufficient and more than simple enough to produce me thinks.
 - Completed test units for additions.
 - Added phpunit coverage reporting and helped phpunit auto locate the tests a bit easier.

## 0.1.4

 - Add support for CSV Handling [PR #32](https://github.com/nategood/httpful/pull/32)

## 0.1.3

 - Handle empty responses in JsonParser and XmlParser

## 0.1.2

 - Added support for setting XMLHandler configuration options
 - Added examples for overriding XmlHandler and registering a custom parser
 - Removed the httpful.php download (deprecated in favor of httpful.phar)

## 0.1.1

 - Bug fix serialization default case and phpunit tests

## 0.1.0

 - Added Support for Registering Mime Handlers
  - Created AbstractMimeHandler type that all Mime Handlers must extend
  - Pulled out the parsing/serializing logic from the Request/Response classes into their own MimeHandler classes
  - Added ability to register new mime handlers for mime types