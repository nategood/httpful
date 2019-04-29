<?php

declare(strict_types=1);

use Httpful\Mime;

require __DIR__ . '/../vendor/autoload.php';

$uri = 'https://www.w3schools.com/xml/note.xml';

// -------------------------------------------------------

$responseComplex = \Httpful\Client::get_request($uri)
    ->expectsType(Mime::PLAIN)
    ->send();

// var_dump($responseComplex->getBody());

// -------------------------------------------------------

$responseSimple = \Httpful\Client::get($uri);

// var_dump($responseSimple->getBody());
