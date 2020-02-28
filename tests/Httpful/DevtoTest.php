<?php

declare(strict_types=1);

namespace Httpful\tests;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class DevtoTest extends TestCase
{
    public function testSimpleCall()
    {
        // init
        $user = 'suckup_de';
        $ARTICLES_ENDPOINT = 'https://dev.to/api/articles';

        // Prepare client-side promise handling.
        $client = new \Httpful\ClientPromise();

        // Send a simple client-side request. (non async)
        $articles = ((\Httpful\Request::get($ARTICLES_ENDPOINT . '?username=' . $user)->withExpectedType(\Httpful\Mime::JSON))->send())->getRawBody();
        foreach ($articles as $article) {
            // Representation of an outgoing, client-side request.
            $request = \Httpful\Request::get($ARTICLES_ENDPOINT . '/' . $article['id'])->withExpectedType(\Httpful\Mime::JSON);

            // Sends a PSR-7 request in an asynchronous way.
            $client->sendAsyncRequest($request);
        }

        $promise = $client->getPromise();

        // Add behavior for when the promise is resolved or rejected.
        /** @var \Httpful\Response[] $results */
        $results = [];
        $promise->then(static function (\Httpful\Response $response, \Httpful\Request $request) use (&$results) {
            $results[] = $response;
        });

        // Wait for the promise to be fulfilled or rejected.
        $promise->wait();

        static::assertTrue(\count($results) > 1);
    }
}
