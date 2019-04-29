<?php

declare(strict_types=1);

use Httpful\Client;

// JSON Example via GitHub-API

require __DIR__ . '/../vendor/autoload.php';

$uri = 'https://api.github.com/users/voku';
$response = Client::getRequest($uri)->expectsJson()->send();

echo $response->getBody()->name . ' joined GitHub on ' . \date('M jS Y', \strtotime($response->getBody()->created_at)) . "\n";
