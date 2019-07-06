<?php

declare(strict_types=1);

namespace Httpful;

if (!\defined('CURLPROXY_SOCKS4')) {
    \define('CURLPROXY_SOCKS4', 4);
}

class Proxy
{
    const HTTP = \CURLPROXY_HTTP;

    const SOCKS4 = \CURLPROXY_SOCKS4;

    const SOCKS5 = \CURLPROXY_SOCKS5;
}
