<?php

require(__DIR__ . '/../bootstrap.php');

// Get event details for a public event
$uri = "http://api.showclix.com/Event/8175";
$response = \Httpful\Request::get($uri)
    ->expectsType(\Httpful\Mime::JSON)
    ->sendIt();

// Print out the event details
echo "The event {$response->body->event} will take place on {$response->body->event_start}\n";