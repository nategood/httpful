<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

function scraping_imdb(string $url): array
{
    // init
    $return = [];

    // create HTML DOM
    $response = \Httpful\Client::get_request($url)
        ->expectsHtml()
        ->disableStrictSSL()
        ->send();

    /** @var \voku\helper\HtmlDomParser $dom */
    $dom = $response->getRawBody();

    // get title
    $return['Title'] = $dom->find('title', 0)->innertext;

    // get rating
    $return['Rating'] = $dom->find('.ratingValue strong', 0)->getAttribute('title');

    return $return;
}

// -----------------------------------------------------------------------------

$data = scraping_imdb('http://imdb.com/title/tt0335266/');

foreach ($data as $k => $v) {
    echo '<strong>' . $k . ' </strong>' . $v . '<br>';
}
