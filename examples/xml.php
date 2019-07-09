<?php

declare(strict_types=1);

use Httpful\Mime;

require __DIR__ . '/../vendor/autoload.php';

$uri = 'https://www.w3schools.com/xml/note.xml';

// -------------------------------------------------------

$responseComplex = \Httpful\Client::get_request($uri)
    ->withExpectedType(Mime::PLAIN)
    ->followRedirects(true)
    ->send();

// -------------------------------------------------------

$responseSimple = \Httpful\Client::get($uri);

// -------------------------------------------------------

if ($responseComplex->getRawBody() === $responseSimple->getRawBody()) {
    echo ' - same output - ';
}
