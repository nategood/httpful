<?php

declare(strict_types=1);

namespace Httpful;

use Httpful\Response\Headers;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

final class Response implements ResponseInterface
{
    /**
     * @var mixed
     */
    private $body;

    /**
     * @var string
     */
    private $raw_body;

    /**
     * @var Headers
     */
    private $headers;

    /**
     * @var string
     */
    private $raw_headers;

    /**
     * @var Request
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
    private $meta_data = [];

    /**
     * @var bool
     */
    private $is_mime_vendor_specific = false;

    /**
     * @var bool
     */
    private $is_mime_personal = false;

    /**
     * @param string  $body
     * @param string  $headers
     * @param Request $request
     * @param array   $meta_data
     */
    public function __construct(
        string $body,
        string $headers,
        Request $request,
        array $meta_data = []
    ) {
        $this->request = $request;
        $this->raw_headers = $headers;
        $this->raw_body = $body;
        $this->meta_data = $meta_data;

        $this->code = $this->_parseCode($headers);
        $this->reason = Http::reason((int) $this->code);
        $this->headers = Response\Headers::fromString($headers);

        $this->_interpretHeaders();

        $this->body = $this->_parse($body);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->raw_body;
    }

    /**
     * Parse the response into a clean data structure
     * (most often an associative array) based on the expected
     * Mime type.
     *
     * @param string $body Http response body
     *
     * @return mixed the response parse accordingly
     */
    public function _parse($body)
    {
        // If the user decided to forgo the automatic smart parsing, short circuit.
        if (!$this->request->isAutoParse()) {
            return $body;
        }

        // If provided, use custom parsing callback.
        if ($this->request->hasParseCallback()) {
            return \call_user_func($this->request->getParseCallback(), $body);
        }

        // Decide how to parse the body of the response in the following order:
        //
        //  1. If provided, use the mime type specifically set as part of the `Request`
        //  2. If a MimeHandler is registered for the content type, use it
        //  3. If provided, use the "parent type" of the mime type from the response
        //  4. Default to the content-type provided in the response
        $parse_with = $this->request->getExpectedType();
        if (empty($parse_with)) {
            if (Setup::hasParserRegistered($this->content_type)) {
                $parse_with = $this->content_type;
            } else {
                $parse_with = $this->parent_type;
            }
        }

        return Setup::setupGlobalMimeType($parse_with)->parse($body);
    }

    /**
     * @param string $headers
     *
     * @throws \Exception
     *
     * @return int
     */
    public function _parseCode($headers): int
    {
        $end = \strpos($headers, "\r\n");
        if ($end === false) {
            $end = \strlen($headers);
        }

        $parts = \explode(' ', \substr($headers, 0, $end));

        if (
            !\is_numeric($parts[1])
            ||
            \count($parts) < 2
        ) {
            throw new \Exception('Unable to parse response code from HTTP response due to malformed response');
        }

        return (int) $parts[1];
    }

    /**
     * Parse text headers from response into array of key value pairs.
     *
     * @param string $headers
     *
     * @return string[]
     */
    public function _parseHeaders($headers): array
    {
        return Headers::fromString($headers)->toArray();
    }

    /**
     * @return mixed
     */
    public function getBody()
    {
        return $this->body;
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
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers->toArray();
    }

    /**
     * @return Headers
     */
    public function getHeadersObject(): Headers
    {
        return $this->headers;
    }

    /**
     * @return string
     */
    public function getParentType(): string
    {
        return $this->parent_type;
    }

    /**
     * @return string
     */
    public function getRawHeaders(): string
    {
        return $this->raw_headers;
    }

    /**
     * @return array
     */
    public function getMetaData(): array
    {
        return $this->meta_data;
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
     * Retrieves the HTTP protocol version as a string.
     *
     * The string MUST contain only the HTTP version number (e.g., "1.1", "1.0").
     *
     * @return string HTTP protocol version
     */
    public function getProtocolVersion()
    {
        return $this->meta_data['protocol_version'];
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
        $return = clone $this;

        $this->meta_data['protocol_version'] = $version;

        return $return;
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
    public function hasHeader($name)
    {
        return (bool) $this->raw_headers;
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
    public function getHeader($name)
    {
        $headers = $this->headers->toArray();

        if (isset($headers[$name])) {
            if (!\is_array($headers[$name])) {
                return [$headers[$name]];
            }

            return $headers[$name];
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
    public function getHeaderLine($name)
    {
        return $this->headers[$name];
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
        $return = clone $this;

        $return->headers[$name] = $value;

        return $return;
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
        $return = clone $this;

        if (isset($return->headers[$name])) {
            $return->headers[$name] .= $value;
        } else {
            $return->headers[$name] = $value;
        }

        return $return;
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
        $return = clone $this;

        $return->headers->forceUnset($name);

        return $return;
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
        $return = clone $this;

        $return->body = $body;

        return $return;
    }

    /**
     * Gets the response status code.
     *
     * The status code is a 3-digit integer result code of the server's attempt
     * to understand and satisfy the request.
     *
     * @return int status code
     */
    public function getStatusCode()
    {
        return $this->code;
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
    public function withStatus($code, $reasonPhrase = '')
    {
        $return = clone $this;

        $return->code = $code;
        $return->reason = $reasonPhrase;

        return $return;
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
    public function getReasonPhrase()
    {
        return $this->reason;
    }

    /**
     * After we've parse the headers, let's clean things
     * up a bit and treat some headers specially
     */
    private function _interpretHeaders()
    {
        // Parse the Content-Type and charset
        $content_type = $this->headers['Content-Type'] ?? '';
        $content_type = \explode(';', $content_type);

        $this->content_type = $content_type[0];
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
}
