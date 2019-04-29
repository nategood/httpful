<?php

declare(strict_types=1);

use Httpful\Mime;

require __DIR__ . '/../vendor/autoload.php';

$uri = 'https://www.w3schools.com/xml/note.xml';

// ---

$responseComplex = \Httpful\Client::getRequest($uri)
    ->expectsType(Mime::PLAIN)
    ->send();

// ---

$responseSimple = \Httpful\Client::get($uri);
