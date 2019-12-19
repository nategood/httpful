<?php

declare(strict_types=1);

use Httpful\Handlers\DefaultMimeHandler;
use Httpful\Handlers\XmlMimeHandler;
use Httpful\Mime;
use Httpful\Setup;

require __DIR__ . '/../vendor/autoload.php';

// We can override the default parser configuration options be registering
// a parser with different configuration options for a particular mime type

// Example setting a namespace for the XMLHandler parser
$conf = ['namespace' => 'http://example.com'];
Setup::registerMimeHandler(Mime::XML, new XmlMimeHandler($conf));

// We can also add the parsers with our own ...

class SimpleCsvMimeHandler extends DefaultMimeHandler
{
    /**
     * Takes a response body, and turns it into
     * a two dimensional array.
     *
     * @param string $body
     *
     * @return array
     */
    public function parse($body)
    {
        return \str_getcsv($body);
    }

    /**
     * Takes a two dimensional array and turns it
     * into a serialized string to include as the
     * body of a request
     *
     * @param mixed $payload
     *
     * @return string
     */
    public function serialize($payload)
    {
        // init
        $serialized = '';

        foreach ($payload as $line) {
            $serialized .= '"' . \implode('","', $line) . '"' . "\n";
        }

        return $serialized;
    }
}

Setup::registerMimeHandler(Mime::CSV, new SimpleCsvMimeHandler());
