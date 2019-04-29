<?php

declare(strict_types=1);

use Httpful\Handlers\JsonHandler;
use Httpful\Mime;
use Httpful\Request;
use Httpful\Setup;

require __DIR__ . '/../vendor/autoload.php';

$uri = 'https://www.w3schools.com/xml/note.xml';

// ---

$responseComplex = \Httpful\Client::getRequest($uri)
    ->expectsType(Mime::PLAIN)
    ->send();

// ---

$responseSimple = \Httpful\Client::get($uri);

