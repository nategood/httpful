<?php

declare(strict_types=1);

// JSON Example via GitHub-API

require __DIR__ . '/../vendor/autoload.php';

$uri = 'https://api.github.com/users/voku';
$response = \Httpful\Client::get_request($uri)->addHeader('X-Foo-Header', 'Just as a demo')
    ->expectsJson()
    ->send();

echo $response->getBody()->name . ' joined GitHub on ' . \date('M jS Y', \strtotime($response->getBody()->created_at)) . "\n";
