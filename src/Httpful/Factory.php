<?php

declare(strict_types=1);

namespace Httpful;

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

/**
 * Psr Factory
 */
class Factory implements RequestFactoryInterface, ServerRequestFactoryInterface, StreamFactoryInterface, ResponseFactoryInterface, UriFactoryInterface, UploadedFileFactoryInterface
{
    /**
     * @param string          $method
     * @param string          $uri
     * @param string|null     $mime
     * @param string|string[] $body
     *
     * @return Request
     */
    public function createRequest(string $method, $uri, string $mime = null, $body = ''): RequestInterface
    {
        $return = (new Request($method, $mime))
            ->withUriFromString($uri);

        if (is_array($body)) {
            $return = $return->withBodyFromArray($body);
        } else {
            $return = $return->withBodyFromString($body);
        }

        return $return;
    }

    /**
     * @param int         $code
     * @param string|null $reasonPhrase
     *
     * @return Response
     */
    public function createResponse(int $code = 200, string $reasonPhrase = null): ResponseInterface
    {
        return (new Response())->withStatus($code, $reasonPhrase);
    }

    /**
     * @param string      $method
     * @param string      $uri
     * @param array       $serverParams
     * @param string|null $mime
     * @param string      $body
     *
     * @return ServerRequest
     */
    public function createServerRequest(string $method, $uri, array $serverParams = [], $mime = null, string $body = ''): ServerRequestInterface
    {
        return (new ServerRequest($method, $mime, null, $serverParams))
            ->withUriFromString($uri)
            ->withBodyFromString($body);
    }

    /**
     * @param string $content
     *
     * @return StreamInterface
     */
    public function createStream(string $content = ''): StreamInterface
    {
        return Stream::createNotNull($content);
    }

    /**
     * @param string $filename
     * @param string $mode
     *
     * @return StreamInterface
     */
    public function createStreamFromFile(string $filename, string $mode = 'rb'): StreamInterface
    {
        /** @noinspection PhpUsageOfSilenceOperatorInspection */
        $resource = @\fopen($filename, $mode);
        if ($resource === false) {
            if ($mode === '' || \in_array($mode[0], ['r', 'w', 'a', 'x', 'c'], true) === false) {
                throw new \InvalidArgumentException('The mode ' . $mode . ' is invalid.');
            }

            throw new \RuntimeException('The file ' . $filename . ' cannot be opened.');
        }

        return Stream::createNotNull($resource);
    }

    /**
     * @param resource|StreamInterface|string $resource
     *
     * @return StreamInterface
     */
    public function createStreamFromResource($resource): StreamInterface
    {
        return Stream::createNotNull($resource);
    }

    /**
     * @param StreamInterface $stream
     * @param int|null        $size
     * @param int             $error
     * @param string|null     $clientFilename
     * @param string|null     $clientMediaType
     *
     * @return UploadedFileInterface
     */
    public function createUploadedFile(
        StreamInterface $stream,
        int $size = null,
        int $error = \UPLOAD_ERR_OK,
        string $clientFilename = null,
        string $clientMediaType = null
    ): UploadedFileInterface {
        if ($size === null) {
            $size = (int) $stream->getSize();
        }

        return new UploadedFile(
            $stream,
            $size,
            $error,
            $clientFilename,
            $clientMediaType
        );
    }

    /**
     * @param string $uri
     *
     * @return UriInterface
     */
    public function createUri(string $uri = ''): UriInterface
    {
        return new Uri($uri);
    }
}
