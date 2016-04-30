<?php

use Httpful\Handlers\MimeHandlerAdapter;
use Httpful\Handlers\XmlHandler;
use Httpful\Httpful;
use Httpful\Mime;

require __DIR__ . '/../bootstrap.php';

// We can override the default parser configuration options be registering
// a parser with different configuration options for a particular mime type

// Example setting a namespace for the XMLHandler parser
$conf = array('namespace' => 'http://example.com');
Httpful::register(Mime::XML, new XmlHandler($conf));

// We can also add the parsers with our own...
/**
 * Class SimpleCsvHandler
 */
class SimpleCsvHandler extends MimeHandlerAdapter
{
  /**
   * Takes a response body, and turns it into
   * a two dimensional array.
   *
   * @param string $body
   *
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
   *
   * @return string
   */
  public function serialize($payload)
  {
    $serialized = '';
    foreach ($payload as $line) {
      $serialized .= '"' . implode('","', $line) . '"' . "\n";
    }

    return $serialized;
  }
}

Httpful::register('text/csv', new SimpleCsvHandler());
