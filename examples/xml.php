<?php

declare(strict_types=1);

use Httpful\Mime;

require __DIR__ . '/../vendor/autoload.php';

$uri = 'https://www.w3schools.com/xml/note.xml';

// -------------------------------------------------------

$responseComplex = (new \Httpful\Client())
    ->sendRequest(
        (
            new \Httpful\Request(
                \Httpful\Http::GET,
                Mime::PLAIN
            )
        )->followRedirects()
    );

// -------------------------------------------------------

$responseMedium = \Httpful\Client::get_request($uri)
    ->withExpectedType(Mime::PLAIN)
    ->followRedirects()
    ->send();

// -------------------------------------------------------

$responseSimple = \Httpful\Client::get($uri);

// -------------------------------------------------------

if (
    $responseComplex->getRawBody() === $responseSimple->getRawBody()
    &&
    $responseComplex->getRawBody() === $responseMedium->getRawBody()
) {
    echo ' - same output - ';
}
