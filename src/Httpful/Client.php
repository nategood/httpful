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
        return self::deleteRequest($uri, $mime)->send();
    }

    /**
     * @param string $uri
     * @param string $mime
     *
     * @return Request
     */
    public static function deleteRequest(string $uri, string $mime = Mime::JSON): Request
    {
        return Request::delete($uri, $mime);
    }

    /**
     * @param string      $uri
     * @param string|null $mime
     *
     * @return Response
     */
    public static function get(string $uri, $mime = Mime::HTML): Response
    {
        return self::getRequest($uri, $mime)->send();
    }

    /**
     * @param string      $uri
     * @param string|null $mime
     *
     * @return Request
     */
    public static function getRequest(string $uri, $mime = Mime::HTML): Request
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
        return self::headRequest($uri)->send();
    }

    /**
     * @param string $uri
     *
     * @return Request
     */
    public static function headRequest(string $uri): Request
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
    public static function patch(string $uri, $payload = null, string $mime = Mime::FORM): Response
    {
        return self::patchRequest($uri, $payload, $mime)->send();
    }

    /**
     * @param string     $uri
     * @param mixed|null $payload
     * @param string     $mime
     *
     * @return Request
     */
    public static function patchRequest(string $uri, $payload = null, string $mime = Mime::FORM): Request
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
    public static function post(string $uri, $payload = null, string $mime = Mime::FORM): Response
    {
        return self::postRequest($uri, $payload, $mime)->send();
    }

    /**
     * @param string     $uri
     * @param mixed|null $payload
     * @param string     $mime
     *
     * @return Request
     */
    public static function postRequest(string $uri, $payload = null, string $mime = Mime::FORM): Request
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
    public static function put(string $uri, $payload = null, string $mime = Mime::JSON): Response
    {
        return self::putRequest($uri, $payload, $mime)->send();
    }

    /**
     * @param string     $uri
     * @param mixed|null $payload
     * @param string     $mime
     *
     * @return Request
     */
    public static function putRequest(string $uri, $payload = null, string $mime = Mime::JSON): Request
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
        return self::optionsRequest($uri)->send();
    }

    /**
     * @param string $uri
     *
     * @return Request
     */
    public static function optionsRequest(string $uri): Request
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
