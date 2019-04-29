<?php

declare(strict_types=1);

namespace Httpful;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class Client implements ClientInterface
{
    /**
     * @param string $uri
     * @param string $mime
     *
     * @return Response
     */
    public static function delete(string $uri, string $mime = Mime::JSON): Response
    {
        return self::delete_request($uri, $mime)->send();
    }

    /**
     * @param string $uri
     * @param string $mime
     *
     * @return Request
     */
    public static function delete_request(string $uri, string $mime = Mime::JSON): Request
    {
        return Request::delete($uri, $mime);
    }

    /**
     * @param string      $uri
     * @param string|null $mime
     *
     * @return Response
     */
    public static function get(string $uri, $mime = Mime::PLAIN): Response
    {
        return self::get_request($uri, $mime)->send();
    }

    /**
     * @param string      $uri
     * @param string|null $mime
     *
     * @return Request
     */
    public static function get_request(string $uri, $mime = Mime::PLAIN): Request
    {
        return Request::get($uri, $mime)->followRedirects();
    }

    /**
     * @param string $uri
     *
     * @return Response
     */
    public static function head(string $uri): Response
    {
        return self::head_request($uri)->send();
    }

    /**
     * @param string $uri
     *
     * @return Request
     */
    public static function head_request(string $uri): Request
    {
        return Request::head($uri)->followRedirects();
    }

    /**
     * @param string     $uri
     * @param mixed|null $payload
     * @param string     $mime
     *
     * @return Response
     */
    public static function patch(string $uri, $payload = null, string $mime = Mime::PLAIN): Response
    {
        return self::patch_request($uri, $payload, $mime)->send();
    }

    /**
     * @param string     $uri
     * @param mixed|null $payload
     * @param string     $mime
     *
     * @return Request
     */
    public static function patch_request(string $uri, $payload = null, string $mime = Mime::PLAIN): Request
    {
        return Request::patch($uri, $payload, $mime);
    }

    /**
     * @param string     $uri
     * @param mixed|null $payload
     * @param string     $mime
     *
     * @return Response
     */
    public static function post(string $uri, $payload = null, string $mime = Mime::PLAIN): Response
    {
        return self::post_request($uri, $payload, $mime)->send();
    }

    /**
     * @param string     $uri
     * @param mixed|null $payload
     * @param string     $mime
     *
     * @return Request
     */
    public static function post_request(string $uri, $payload = null, string $mime = Mime::PLAIN): Request
    {
        return Request::post($uri, $payload, $mime)->followRedirects();
    }

    /**
     * @param string     $uri
     * @param mixed|null $payload
     * @param string     $mime
     *
     * @return Response
     */
    public static function put(string $uri, $payload = null, string $mime = Mime::PLAIN): Response
    {
        return self::put_request($uri, $payload, $mime)->send();
    }

    /**
     * @param string     $uri
     * @param mixed|null $payload
     * @param string     $mime
     *
     * @return Request
     */
    public static function put_request(string $uri, $payload = null, string $mime = Mime::JSON): Request
    {
        return Request::put($uri, $payload, $mime);
    }

    /**
     * @param string $uri
     *
     * @return Response
     */
    public static function options(string $uri): Response
    {
        return self::options_request($uri)->send();
    }

    /**
     * @param string $uri
     *
     * @return Request
     */
    public static function options_request(string $uri): Request
    {
        return Request::options($uri);
    }

    /**
     * @param RequestInterface $request
     *
     * @return ResponseInterface
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return Request::{$request->getMethod()}($request->getUri())->send();
    }
}
