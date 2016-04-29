<?php

use Httpful\Handlers\JsonHandler;
use Httpful\Httpful;
use Httpful\Mime;
use \Httpful\Request;

require(__DIR__ . '/../bootstrap.php');

//
// Get event details for a public event
//
$uri = "http://api.showclix.com/Event/8175";
$response = Request::get($uri)
                   ->expectsType('json')
                   ->send();

//
// Print out the event details
//
echo "The event {$response->body->event} will take place on {$response->body->event_start}\n";

//
// Example overriding the default JSON handler with one that encodes the response as an array
//
Httpful::register(Mime::JSON, new JsonHandler(array('decode_as_array' => true)));

$response = Request::get($uri)
                   ->expectsType('json')
                   ->send();

// Print out the event details
echo "The event {$response->body['event']} will take place on {$response->body['event_start']}\n";
