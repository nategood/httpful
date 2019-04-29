[![Build Status](https://travis-ci.org/voku/httpful.svg?branch=master)](https://travis-ci.org/voku/httpful)
[![Coverage Status](https://coveralls.io/repos/github/voku/httpful/badge.svg?branch=master)](https://coveralls.io/github/voku/httpful?branch=master)
[![Codacy Badge](https://api.codacy.com/project/badge/Grade/5882e37a6cd24f6c9d1cf70a08064146)](https://www.codacy.com/app/voku/httpful)
[![Latest Stable Version](https://poser.pugx.org/voku/httpful/v/stable)](https://packagist.org/packages/voku/httpful) 
[![Total Downloads](https://poser.pugx.org/voku/httpful/downloads)](https://packagist.org/packages/voku/httpful) 
[![License](https://poser.pugx.org/voku/arrayy/license)](https://packagist.org/packages/voku/arrayy)
[![Donate to this project using Paypal](https://img.shields.io/badge/paypal-donate-yellow.svg)](https://www.paypal.me/moelleken)
[![Donate to this project using Patreon](https://img.shields.io/badge/patreon-donate-yellow.svg)](https://www.patreon.com/voku)

# 📯 Httpful

Features

 - Readable HTTP Method Support (GET, PUT, POST, DELETE, HEAD, PATCH and OPTIONS)
 - Custom Headers
 - Automatic "Smart" Parsing
 - Automatic Payload Serialization
 - Basic Auth
 - Client Side Certificate Auth
 - Request "Templates"
 - PSR-3: Logger Interface
 - PSR-18: HTTP Client

# Example

```php
<?php

// Make a request to the GitHub API with a custom
// header of "X-Trvial-Header: Just as a demo".
$uri = 'https://api.github.com/users/voku';
$response = \Httpful\Client::getRequest($uri)->addHeader('X-Trvial-Header', 'Just as a demo')
                                             ->expectsJson()
                                             ->send();

echo $response->getBody()->name . ' joined GitHub on ' . \date('M jS Y', \strtotime($response->getBody()->created_at)) . "\n";
```

# Installation

```shell
composer require voku/httpful
```

## Handlers

Handlers are simple classes that are used to parse response bodies and serialize request payloads.  All Handlers must extend the `MimeHandlerAdapter` class and implement two methods: `serialize($payload)` and `parse($response)`.  Let's build a very basic Handler to register for the `text/csv` mime type.

```php
<?php

class SimpleCsvHandler extends \Httpful\Handlers\MimeHandlerAdapter
{
    /**
     * Takes a response body, and turns it into 
     * a two dimensional array.
     *
     * @param string $body
     * @return mixed
     */
    public function parse($body)
    {
        return str_getcsv($body);
    }

    /**
     * Takes a two dimensional array and turns it
     * into a serialized string to include as the 
     * body of a request
     *
     * @param mixed $payload
     * @return string
     */
    public function serialize($payload)
    {
        // init
        $serialized = '';
        
        foreach ($payload as $line) {
            $serialized .= '"' . implode('","', $line) . '"' . "\n";
        }
        
        return $serialized;
    }
}
```

Finally, you must register this handler for a particular mime type.

```
HttpSetup::register(Mime::CSV, new SimpleCsvHandler());
```

After this registering the handler in your source code, by default, any responses with a mime type of text/csv should be parsed by this handler.

