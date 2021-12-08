<?php

declare(strict_types=1);

// JSON Example via GitHub-API

require __DIR__ . '/../vendor/autoload.php';

// ------------------- SHORT VERSION

$uri = 'https://postman-echo.com/post';
$result = \Httpful\Client::post_form($uri, ['foo1' => 'PHP']);
echo $result['form']['foo1'] . "\n"; // response from postman

// ------------------- LONG VERSION

$query = \http_build_query(['foo1' => 'PHP']);
$http = new \Httpful\Factory();

$response = (new \Httpful\Client())->sendRequest(
    $http->createRequest(
    \Httpful\Http::POST,
    'https://postman-echo.com/post',
    \Httpful\Mime::FORM,
    $query
)
);
$result = $response->getRawBody();
echo $result['form']['foo1'] . "\n"; // response from postman

// ------------------- LONG VERSION + UPLOAD

$form = ['foo1' => 'PHP'];
$http = new \Httpful\Factory();

$filename = __DIR__ . '/../tests/static/test_image.jpg';

$response = (new \Httpful\Client())->sendRequest(
    $http->createRequest(
        \Httpful\Http::POST,
        'https://postman-echo.com/post',
        \Httpful\Mime::FORM,
        $form
    )->withAttachment(['foo2' => $filename])
);
$result = $response->getRawBody();
echo $result['form']['foo1'] . "\n"; // response from postman
echo $result['files']['test_image.jpg'] . "\n"; // response from postman
