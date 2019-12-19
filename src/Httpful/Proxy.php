<?php

declare(strict_types=1);

namespace Httpful;

if (!\defined('CURLPROXY_HTTP')) {
    \define('CURLPROXY_HTTP', 0);
}

if (!\defined('CURLPROXY_SOCKS4')) {
    \define('CURLPROXY_SOCKS4', 4);
}

if (!\defined('CURLPROXY_SOCKS5')) {
    \define('CURLPROXY_SOCKS5', 5);
}

class Proxy
{
    const HTTP = \CURLPROXY_HTTP;

    const SOCKS4 = \CURLPROXY_SOCKS4;

    const SOCKS5 = \CURLPROXY_SOCKS5;
}
