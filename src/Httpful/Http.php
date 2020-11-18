<?php

declare(strict_types=1);

namespace Httpful;

use Httpful\Exception\ResponseException;
use Psr\Http\Message\StreamInterface;

class Http
{
    const DELETE = 'DELETE';

    const GET = 'GET';

    const HEAD = 'HEAD';

    const OPTIONS = 'OPTIONS';

    const PATCH = 'PATCH';

    const POST = 'POST';

    const PUT = 'PUT';

    const TRACE = 'TRACE';

    const HTTP_1_0 = '1.0';

    const HTTP_1_1 = '1.1';

    const HTTP_2_0 = '2';

    /**
     * @return array
     */
    public static function allMethods(): array
    {
        return [
            self::HEAD,
            self::POST,
            self::GET,
            self::PUT,
            self::DELETE,
            self::OPTIONS,
            self::TRACE,
            self::PATCH,
        ];
    }

    /**
     * @return array list of (always) idempotent HTTP methods
     */
    public static function idempotentMethods(): array
    {
        return [
            self::HEAD,
            self::GET,
            self::PUT,
            self::DELETE,
            self::OPTIONS,
            self::TRACE,
            self::PATCH,
        ];
    }

    /**
     * @param string $method HTTP method
     *
     * @return bool
     */
    public static function isIdempotent($method): bool
    {
        return \in_array($method, self::idempotentMethods(), true);
    }

    /**
     * @param string $method HTTP method
     *
     * @return bool
     */
    public static function isNotIdempotent($method): bool
    {
        return !\in_array($method, self::idempotentMethods(), true);
    }

    /**
     * @param string $method HTTP method
     *
     * @return bool
     */
    public static function isSafeMethod($method): bool
    {
        return \in_array($method, self::safeMethods(), true);
    }

    /**
     * @param string $method HTTP method
     *
     * @return bool
     */
    public static function isUnsafeMethod($method): bool
    {
        return !\in_array($method, self::safeMethods(), true);
    }

    /**
     * @param int $code
     *
     * @throws \Exception
     *
     * @return string
     */
    public static function reason(int $code): string
    {
        $codes = self::responseCodes();

        if (!\array_key_exists($code, $codes)) {
            throw new ResponseException('Unable to parse response code from HTTP response due to malformed response. Code: ' . $code);
        }

        return $codes[$code];
    }

    /**
     * @param int $code
     *
     * @return bool
     */
    public static function responseCodeExists(int $code): bool
    {
        return \array_key_exists($code, self::responseCodes());
    }

    /**
     * @return array of HTTP method strings
     */
    public static function safeMethods(): array
    {
        return [
            self::HEAD,
            self::GET,
            self::OPTIONS,
            self::TRACE,
        ];
    }

    /**
     * Create a new stream based on the input type.
     *
     * Options is an associative array that can contain the following keys:
     * - metadata: Array of custom metadata.
     * - size: Size of the stream.
     *
     * @param mixed $resource
     * @param array $options
     *
     * @throws \InvalidArgumentException if the $resource arg is not valid
     *
     * @return StreamInterface
     */
    public static function stream($resource = '', array $options = []): StreamInterface
    {
        // init
        $options['serialized'] = false;

        if (\is_array($resource)) {
            $resource = \serialize($resource);

            $options['serialized'] = true;
        }

        if (\is_scalar($resource)) {
            $stream = \fopen('php://temp', 'r+b');

            if (!\is_resource($stream)) {
                throw new \RuntimeException('fopen must create a resource');
            }

            if ($resource !== '') {
                \fwrite($stream, (string) $resource);
                \fseek($stream, 0);
            }

            return new Stream($stream, $options);
        }

        switch (\gettype($resource)) {
            case 'resource':
                return new Stream($resource, $options);
            case 'object':
                if ($resource instanceof StreamInterface) {
                    return $resource;
                }

                if (\method_exists($resource, '__toString')) {
                    return self::stream((string) $resource, $options);
                }

                break;
            case 'NULL':
                $stream = \fopen('php://temp', 'r+b');

                if (!\is_resource($stream)) {
                    throw new \RuntimeException('fopen must create a resource');
                }

                return new Stream($stream, $options);
        }

        throw new \InvalidArgumentException('Invalid resource type: ' . \gettype($resource));
    }

    /**
     * get all response-codes
     *
     * @return array
     */
    private static function responseCodes(): array
    {
        return [
            100 => 'Continue',
            101 => 'Switching Protocols',
            102 => 'Processing',
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            207 => 'Multi-Status',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            306 => 'Switch Proxy',
            307 => 'Temporary Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            418 => 'I\'m a teapot',
            422 => 'Unprocessable Entity',
            423 => 'Locked',
            424 => 'Failed Dependency',
            425 => 'Unordered Collection',
            426 => 'Upgrade Required',
            429 => 'Too Many Requests',
            449 => 'Retry With',
            450 => 'Blocked by Windows Parental Controls',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
            506 => 'Variant Also Negotiates',
            507 => 'Insufficient Storage',
            509 => 'Bandwidth Limit Exceeded',
            510 => 'Not Extended',
        ];
    }
}
