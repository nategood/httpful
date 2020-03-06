<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

/**
 * @param string[] $urls
 *
 * @return array
 */
function scraping_multi(array $urls): array
{
    $client = new \Httpful\ClientPromise();

    foreach ($urls as $url) {
        $client->add_html($url);
    }

    $promise = $client->getPromise();

    $return = [];
    $promise->then(static function (Httpful\Response $response, Httpful\Request $request) use (&$return) {
        /** @var \voku\helper\HtmlDomParser $dom */
        $dom = $response->getRawBody();

        // get title
        $return[] = $dom->find('title', 0)->innertext;
    });

    $promise->wait();

    return $return;
}

// -----------------------------------------------------------------------------

$data = scraping_multi(
    [
        'https://moelleken.org',
        'https://google.com',
    ]
);

foreach ($data as $title) {
    echo '<strong>' . $title . ' </strong><br>' . "\n";
}
