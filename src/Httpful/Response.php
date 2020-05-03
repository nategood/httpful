<?php

declare(strict_types=1);

namespace Httpful;

use Httpful\Exception\ResponseException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use voku\helper\UTF8;

class Response implements ResponseInterface
{
    /**
     * @var StreamInterface
     */
    private $body;

    /**
     * @var mixed|null
     */
    private $raw_body;

    /**
     * @var Headers
     */
    private $headers;

    /**
     * @var mixed|null
     */
    private $raw_headers;

    /**
     * @var RequestInterface|null
     */
    private $request;

    /**
     * @var int
     */
    private $code;

    /**
     * @var string
     */
    private $reason;

    /**
     * @var string
     */
    private $content_type = '';

    /**
     * Parent / Generic type (e.g. xml for application/vnd.github.message+xml)
     *
     * @var string
     */
    private $parent_type = '';

    /**
     * @var string
     */
    private $charset = '';

    /**
     * @var array
     */
    private $meta_data;

    /**
     * @var bool
     */
    private $is_mime_vendor_specific = false;

    /**
     * @var bool
     */
    private $is_mime_personal = false;

    /**
     * @param StreamInterface|string|null $body
     * @param array|string|null           $headers
     * @param RequestInterface|null       $request
     * @param array                       $meta_data
     *                                               <p>e.g. [protocol_version] = '1.1'</p>
     */
    public function __construct(
        $body = null,
        $headers = null,
        RequestInterface $request = null,
        array $meta_data = []
    ) {
        if (!($body instanceof Stream)) {
            $this->raw_body = $body;
            $body = Stream::create($body);
        }

        $this->request = $request;
        $this->raw_headers = $headers;
        $this->meta_data = $meta_data;

        if (!isset($this->meta_data['protocol_version'])) {
            $this->meta_data['protocol_version'] = '1.1';
        }

        if (
            \is_string($headers)
            &&
            $headers !== ''
        ) {
            $this->code = $this->_getResponseCodeFromHeaderString($headers);
            $this->reason = Http::reason($this->code);
            $this->headers = Headers::fromString($headers);
        } elseif (
            \is_array($headers)
            &&
            \count($headers) > 0
        ) {
            $this->code = 200;
            $this->reason = Http::reason($this->code);
            $this->headers = new Headers($headers);
        } else {
            $this->code = 200;
            $this->reason = Http::reason($this->code);
            $this->headers = new Headers();
        }

        $this->_interpretHeaders();

        $bodyParsed = $this->_parse($body);
        $this->body = Stream::createNotNull($bodyParsed);
        $this->raw_body = $bodyParsed;
    }

    /**
     * @return void
     */
    public function __clone()
    {
        $this->headers = clone $this->headers;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        if (
            $this->body->getSize() > 0
            &&
            !(
                $this->raw_body
                &&
                UTF8::is_serialized((string) $this->body)
            )
        ) {
            return (string) $this->body;
        }

        if (\is_string($this->raw_body)) {
            return (string) $this->raw_body;
        }

        return (string) \json_encode($this->raw_body);
    }

    /**
     * @param string $headers
     *
     * @throws ResponseException if we are unable to parse response code from HTTP response
     *
     * @return int
     *
     * @internal
     */
    public function _getResponseCodeFromHeaderString($headers): int
    {
        // If there was a redirect, we will get headers from one then one request,
        // but will are only interested in the last request.
        $headersTmp = \explode("\r\n\r\n", $headers);
        $headersTmpCount = \count($headersTmp);
        if ($headersTmpCount >= 2) {
            $headers = $headersTmp[$headersTmpCount - 2];
        }

        $end = \strpos($headers, "\r\n");
        if ($end === false) {
            $end = \strlen($headers);
        }

        $parts = \explode(' ', \substr($headers, 0, $end));

        if (
            \count($parts) < 2
            ||
            !\is_numeric($parts[1])
        ) {
            throw new ResponseException('Unable to parse response code from HTTP response due to malformed response: "' . \print_r($headers, true) . '"');
        }

        return (int) $parts[1];
    }

    /**
     * @return StreamInterface
     */
    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    /**
     * Retrieves a message header value by the given case-insensitive name.
     *
     * This method returns an array of all the header values of the given
     * case-insensitive header name.
     *
     * If the header does not appear in the message, this method MUST return an
     * empty array.
     *
     * @param string $name case-insensitive header field name
     *
     * @return string[] An array of string values as provided for the given
     *                  header. If the header does not appear in the message, this method MUST
     *                  return an empty array.
     */
    public function getHeader($name): array
    {
        if ($this->headers->offsetExists($name)) {
            $value = $this->headers->offsetGet($name);

            if (!\is_array($value)) {
                return [\trim($value, " \t")];
            }

            foreach ($value as $keyInner => $valueInner) {
                $value[$keyInner] = \trim($valueInner, " \t");
            }

            return $value;
        }

        return [];
    }

    /**
     * Retrieves a comma-separated string of the values for a single header.
     *
     * This method returns all of the header values of the given
     * case-insensitive header name as a string concatenated together using
     * a comma.
     *
     * NOTE: Not all header values may be appropriately represented using
     * comma concatenation. For such headers, use getHeader() instead
     * and supply your own delimiter when concatenating.
     *
     * If the header does not appear in the message, this method MUST return
     * an empty string.
     *
     * @param string $name case-insensitive header field name
     *
     * @return string A string of values as provided for the given header
     *                concatenated together using a comma. If the header does not appear in
     *                the message, this method MUST return an empty string.
     */
    public function getHeaderLine($name): string
    {
        return \implode(', ', $this->getHeader($name));
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers->toArray();
    }

    /**
     * Retrieves the HTTP protocol version as a string.
     *
     * The string MUST contain only the HTTP version number (e.g., "1.1", "1.0").
     *
     * @return string HTTP protocol version
     */
    public function getProtocolVersion(): string
    {
        if (isset($this->meta_data['protocol_version'])) {
            return (string) $this->meta_data['protocol_version'];
        }

        return '1.1';
    }

    /**
     * Gets the response reason phrase associated with the status code.
     *
     * Because a reason phrase is not a required element in a response
     * status line, the reason phrase value MAY be null. Implementations MAY
     * choose to return the default RFC 7231 recommended reason phrase (or those
     * listed in the IANA HTTP Status Code Registry) for the response's
     * status code.
     *
     * @see http://tools.ietf.org/html/rfc7231#section-6
     * @see http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
     *
     * @return string reason phrase; must return an empty string if none present
     */
    public function getReasonPhrase(): string
    {
        return $this->reason;
    }

    /**
     * Gets the response status code.
     *
     * The status code is a 3-digit integer result code of the server's attempt
     * to understand and satisfy the request.
     *
     * @return int status code
     */
    public function getStatusCode(): int
    {
        return $this->code;
    }

    /**
     * Checks if a header exists by the given case-insensitive name.
     *
     * @param string $name case-insensitive header field name
     *
     * @return bool Returns true if any header names match the given header
     *              name using a case-insensitive string comparison. Returns false if
     *              no matching header name is found in the message.
     */
    public function hasHeader($name): bool
    {
        return $this->headers->offsetExists($name);
    }

    /**
     * Return an instance with the specified header appended with the given value.
     *
     * Existing values for the specified header will be maintained. The new
     * value(s) will be appended to the existing list. If the header did not
     * exist previously, it will be added.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new header and/or value.
     *
     * @param string          $name  case-insensitive header field name to add
     * @param string|string[] $value header value(s)
     *
     * @throws \InvalidArgumentException for invalid header names or values
     *
     * @return static
     */
    public function withAddedHeader($name, $value)
    {
        $new = clone $this;

        if (!\is_array($value)) {
            $value = [$value];
        }

        if ($new->headers->offsetExists($name)) {
            $new->headers->forceSet($name, \array_merge_recursive($new->headers->offsetGet($name), $value));
        } else {
            $new->headers->forceSet($name, $value);
        }

        return $new;
    }

    /**
     * Return an instance with the specified message body.
     *
     * The body MUST be a StreamInterface object.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return a new instance that has the
     * new body stream.
     *
     * @param StreamInterface $body body
     *
     * @throws \InvalidArgumentException when the body is not valid
     *
     * @return static
     */
    public function withBody(StreamInterface $body)
    {
        $new = clone $this;

        $new->body = $body;

        return $new;
    }

    /**
     * Return an instance with the provided value replacing the specified header.
     *
     * While header names are case-insensitive, the casing of the header will
     * be preserved by this function, and returned from getHeaders().
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new and/or updated header and value.
     *
     * @param string          $name  case-insensitive header field name
     * @param string|string[] $value header value(s)
     *
     * @throws \InvalidArgumentException for invalid header names or values
     *
     * @return static
     */
    public function withHeader($name, $value)
    {
        $new = clone $this;

        if (!\is_array($value)) {
            $value = [$value];
        }

        $new->headers->forceSet($name, $value);

        return $new;
    }

    /**
     * Return an instance with the specified HTTP protocol version.
     *
     * The version string MUST contain only the HTTP version number (e.g.,
     * "1.1", "1.0").
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new protocol version.
     *
     * @param string $version HTTP protocol version
     *
     * @return static
     */
    public function withProtocolVersion($version)
    {
        $new = clone $this;

        $new->meta_data['protocol_version'] = $version;

        return $new;
    }

    /**
     * Return an instance with the specified status code and, optionally, reason phrase.
     *
     * If no reason phrase is specified, implementations MAY choose to default
     * to the RFC 7231 or IANA recommended reason phrase for the response's
     * status code.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * updated status and reason phrase.
     *
     * @see http://tools.ietf.org/html/rfc7231#section-6
     * @see http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
     *
     * @param int    $code         the 3-digit integer result code to set
     * @param string $reasonPhrase the reason phrase to use with the
     *                             provided status code; if none is provided, implementations MAY
     *                             use the defaults as suggested in the HTTP specification
     *
     * @throws \InvalidArgumentException for invalid status code arguments
     *
     * @return static
     */
    public function withStatus($code, $reasonPhrase = null)
    {
        $new = clone $this;

        $new->code = (int) $code;

        if (Http::responseCodeExists($new->code)) {
            $new->reason = Http::reason($new->code);
        } else {
            $new->reason = '';
        }

        if ($reasonPhrase !== null) {
            $new->reason = $reasonPhrase;
        }

        return $new;
    }

    /**
     * Return an instance without the specified header.
     *
     * Header resolution MUST be done without case-sensitivity.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that removes
     * the named header.
     *
     * @param string $name case-insensitive header field name to remove
     *
     * @return static
     */
    public function withoutHeader($name)
    {
        $new = clone $this;

        $new->headers->forceUnset($name);

        return $new;
    }

    /**
     * @return string
     */
    public function getCharset(): string
    {
        return $this->charset;
    }

    /**
     * @return string
     */
    public function getContentType(): string
    {
        return $this->content_type;
    }

    /**
     * @return Headers
     */
    public function getHeadersObject(): Headers
    {
        return $this->headers;
    }

    /**
     * @return array
     */
    public function getMetaData(): array
    {
        return $this->meta_data;
    }

    /**
     * @return string
     */
    public function getParentType(): string
    {
        return $this->parent_type;
    }

    /**
     * @return mixed
     */
    public function getRawBody()
    {
        return $this->raw_body;
    }

    /**
     * @return string
     */
    public function getRawHeaders(): string
    {
        return $this->raw_headers;
    }

    /**
     * @return bool
     */
    public function hasBody(): bool
    {
        return !empty($this->body);
    }

    /**
     * Status Code Definitions.
     *
     * Informational 1xx
     * Successful    2xx
     * Redirection   3xx
     * Client Error  4xx
     * Server Error  5xx
     *
     * http://pretty-rfc.herokuapp.com/RFC2616#status.codes
     *
     * @return bool Did we receive a 4xx or 5xx?
     */
    public function hasErrors(): bool
    {
        return $this->code >= 400;
    }

    /**
     * @return bool
     */
    public function isMimePersonal(): bool
    {
        return $this->is_mime_personal;
    }

    /**
     * @return bool
     */
    public function isMimeVendorSpecific(): bool
    {
        return $this->is_mime_vendor_specific;
    }

    /**
     * @param string[] $header
     *
     * @return static
     */
    public function withHeaders(array $header)
    {
        $new = clone $this;

        foreach ($header as $name => $value) {
            $new = $new->withHeader($name, $value);
        }

        return $new;
    }

    /**
     * After we've parse the headers, let's clean things
     * up a bit and treat some headers specially
     *
     * @return void
     */
    private function _interpretHeaders()
    {
        // Parse the Content-Type and charset
        $content_type = $this->headers['Content-Type'] ?? [];
        foreach ($content_type as $content_type_inner) {
            $content_type = \array_merge(\explode(';', $content_type_inner));
        }

        $this->content_type = $content_type[0] ?? '';
        if (
            \count($content_type) === 2
            &&
            \strpos($content_type[1], '=') !== false
        ) {
            /** @noinspection PhpUnusedLocalVariableInspection */
            list($nill, $this->charset) = \explode('=', $content_type[1]);
        }

        // fallback
        if (!$this->charset) {
            $this->charset = 'utf-8';
        }

        // check for vendor & personal type
        if (\strpos($this->content_type, '/') !== false) {
            /** @noinspection PhpUnusedLocalVariableInspection */
            list($type, $sub_type) = \explode('/', $this->content_type);
            $this->is_mime_vendor_specific = \strpos($sub_type, 'vnd.') === 0;
            $this->is_mime_personal = \strpos($sub_type, 'prs.') === 0;
        }

        $this->parent_type = $this->content_type;
        if (\strpos($this->content_type, '+') !== false) {
            /** @noinspection PhpUnusedLocalVariableInspection */
            list($vendor, $this->parent_type) = \explode('+', $this->content_type, 2);
            $this->parent_type = Mime::getFullMime($this->parent_type);
        }
    }

    /**
     * Parse the response into a clean data structure
     * (most often an associative array) based on the expected
     * Mime type.
     *
     * @param StreamInterface|null $body Http response body
     *
     * @return mixed the response parse accordingly
     */
    private function _parse($body)
    {
        // If the user decided to forgo the automatic smart parsing, short circuit.
        if (
            $this->request instanceof Request
            &&
            !$this->request->isAutoParse()
        ) {
            return $body;
        }

        // If provided, use custom parsing callback.
        if (
            $this->request instanceof Request
            &&
            $this->request->hasParseCallback()
        ) {
            return \call_user_func($this->request->getParseCallback(), $body);
        }

        // Decide how to parse the body of the response in the following order:
        //
        //  1. If provided, use the mime type specifically set as part of the `Request`
        //  2. If a MimeHandler is registered for the content type, use it
        //  3. If provided, use the "parent type" of the mime type from the response
        //  4. Default to the content-type provided in the response
        if ($this->request instanceof Request) {
            $parse_with = $this->request->getExpectedType();
        }

        if (empty($parse_with)) {
            if (Setup::hasParserRegistered($this->content_type)) {
                $parse_with = $this->content_type;
            } else {
                $parse_with = $this->parent_type;
            }
        }

        return Setup::setupGlobalMimeType($parse_with)->parse((string) $body);
    }
}
