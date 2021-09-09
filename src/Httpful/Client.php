<?php

declare(strict_types=1);

namespace Httpful;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Client implements ClientInterface
{
    /**
     * @param string     $uri
     * @param array|null $params
     * @param string     $mime
     *
     * @return Response
     */
    public static function delete(string $uri, array $params = null, string $mime = Mime::JSON): Response
    {
        return self::delete_request($uri, $params, $mime)->send();
    }

    /**
     * @param string     $uri
     * @param array|null $params
     * @param string     $mime
     *
     * @return Request
     */
    public static function delete_request(string $uri, array $params = null, string $mime = Mime::JSON): Request
    {
        return Request::delete($uri, $params, $mime);
    }

    /**
     * @param string    $uri
     * @param string    $file_path
     * @param float|int $timeout
     *
     * @return Response
     */
    public static function download(string $uri, $file_path, $timeout = 0): Response
    {
        $request = Request::download($uri, $file_path);

        if ($timeout > 0) {
            $request->withTimeout($timeout)
                ->withConnectionTimeoutInSeconds($timeout / 10);
        }

        return $request->send();
    }

    /**
     * @param string      $uri
     * @param array|null  $params
     * @param string|null $mime
     *
     * @return Response
     */
    public static function get(string $uri, array $params = null, $mime = Mime::PLAIN): Response
    {
        return self::get_request($uri, $params, $mime)->send();
    }

    /**
     * @param string     $uri
     * @param array|null $param
     *
     * @return \voku\helper\HtmlDomParser|null
     */
    public static function get_dom(string $uri, array $param = null)
    {
        return self::get_request($uri, $param, Mime::HTML)->send()->getRawBody();
    }

    /**
     * @param string     $uri
     * @param array|null $param
     *
     * @return array
     */
    public static function get_form(string $uri, array $param = null): array
    {
        return self::get_request($uri, $param, Mime::FORM)->send()->getRawBody();
    }

    /**
     * @param string     $uri
     * @param array|null $param
     *
     * @return mixed
     */
    public static function get_json(string $uri, array $param = null)
    {
        return self::get_request($uri, $param, Mime::JSON)->send()->getRawBody();
    }

    /**
     * @param string      $uri
     * @param array|null  $param
     * @param string|null $mime
     *
     * @return Request
     */
    public static function get_request(string $uri, array $param = null, $mime = Mime::PLAIN): Request
    {
        return Request::get($uri, $param, $mime)->followRedirects();
    }

    /**
     * @param string     $uri
     * @param array|null $param
     *
     * @return \SimpleXMLElement|null
     */
    public static function get_xml(string $uri, array $param = null)
    {
        return self::get_request($uri, $param, Mime::XML)->send()->getRawBody();
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
     *
     * @return \voku\helper\HtmlDomParser|null
     */
    public static function post_dom(string $uri, $payload = null)
    {
        return self::post_request($uri, $payload, Mime::HTML)->send()->getRawBody();
    }

    /**
     * @param string     $uri
     * @param mixed|null $payload
     *
     * @return array
     */
    public static function post_form(string $uri, $payload = null): array
    {
        return self::post_request($uri, $payload, Mime::FORM)->send()->getRawBody();
    }

    /**
     * @param string     $uri
     * @param mixed|null $payload
     *
     * @return mixed
     */
    public static function post_json(string $uri, $payload = null)
    {
        return self::post_request($uri, $payload, Mime::JSON)->send()->getRawBody();
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
     *
     * @return \SimpleXMLElement|null
     */
    public static function post_xml(string $uri, $payload = null)
    {
        return self::post_request($uri, $payload, Mime::XML)->send()->getRawBody();
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
     * @param Request|RequestInterface $request
     *
     * @return Response|ResponseInterface
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        if (!$request instanceof Request) {
            /** @noinspection PhpSillyAssignmentInspection - helper for PhpStorm */
            /** @var RequestInterface $request */
            $request = $request;

            /** @var Request $requestNew */
            $requestNew = Request::{$request->getMethod()}($request->getUri());
            $requestNew->withHeaders($request->getHeaders());
            $requestNew->withProtocolVersion($request->getProtocolVersion());
            $requestNew->withBody($request->getBody());
            $requestNew->withRequestTarget($request->getRequestTarget());

            $request = $requestNew;
        }

        return $request->send();
    }
}
