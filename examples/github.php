<?php

use \Httpful\Request;

// XML Example from GitHub
require(__DIR__ . '/../bootstrap.php');


$uri = 'https://github.com/api/v2/xml/user/show/nategood';
$request = Request::get($uri)->send();

echo "{$request->body->name} joined GitHub on " . date('M jS', strtotime($request->body->{'created-at'})) . "\n";