<?php

/** @noinspection MagicMethodsValidityInspection */
/** @noinspection PhpMissingParentConstructorInspection */

declare(strict_types=1);

namespace Httpful;

use Curl\CaseInsensitiveArray;
use Httpful\Exception\ResponseHeaderException;

final class Headers extends CaseInsensitiveArray
{
    /**
     * Construct
     *
     * Allow creating either an empty Array, or convert an existing Array to a
     * Case-Insensitive Array.  (Caution: Data may be lost when converting Case-
     * Sensitive Arrays to Case-Insensitive Arrays)
     *
     * @param mixed[] $initial (optional) Existing Array to convert
     */
    public function __construct(array $initial = null)
    {
        if ($initial !== null) {
            foreach ($initial as $key => $value) {
                if (!\is_array($value)) {
                    $value = [$value];
                }

                $this->forceSet($key, $value);
            }
        }
    }

    /**
     * @param string $offset the offset to store the data at (case-insensitive)
     * @param mixed  $value  the data to store at the specified offset
     */
    public function forceSet($offset, $value)
    {
        $value = $this->_validateAndTrimHeader($offset, $value);

        parent::offsetSet($offset, $value);
    }

    /**
     * @param string $offset
     */
    public function forceUnset($offset)
    {
        parent::offsetUnset($offset);
    }

    /**
     * @param string $string
     *
     * @return Headers
     */
    public static function fromString($string): self
    {
        // init
        $parsed_headers = [];

        $headers = \preg_split("/[\r\n]+/", $string, -1, \PREG_SPLIT_NO_EMPTY);
        if ($headers === false) {
            return new self($parsed_headers);
        }

        $headersCount = \count($headers);
        for ($i = 1; $i < $headersCount; ++$i) {
            $header = $headers[$i];

            if (\strpos($header, ':') === false) {
                continue;
            }

            list($key, $raw_value) = \explode(':', $header, 2);
            $key = \trim($key);
            $value = \trim($raw_value);

            if (\array_key_exists($key, $parsed_headers)) {
                $parsed_headers[$key][] = $value;
            } else {
                $parsed_headers[$key][] = $value;
            }
        }

        return new self($parsed_headers);
    }

    /**
     * @param string $offset
     * @param string $value
     *
     * @throws ResponseHeaderException
     */
    public function offsetSet($offset, $value)
    {
        throw new ResponseHeaderException('Headers are read-only.');
    }

    /**
     * @param string $offset
     *
     * @throws ResponseHeaderException
     */
    public function offsetUnset($offset)
    {
        throw new ResponseHeaderException('Headers are read-only.');
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        // init
        $return = [];

        $that = clone $this;

        foreach ($that as $key => $value) {
            if (\is_array($value)) {
                foreach ($value as $keyInner => $valueInner) {
                    $value[$keyInner] = \trim($valueInner, " \t");
                }
            }

            $return[$key] = $value;
        }

        return $return;
    }

    /**
     * Make sure the header complies with RFC 7230.
     *
     * Header names must be a non-empty string consisting of token characters.
     *
     * Header values must be strings consisting of visible characters with all optional
     * leading and trailing whitespace stripped. This method will always strip such
     * optional whitespace. Note that the method does not allow folding whitespace within
     * the values as this was deprecated for almost all instances by the RFC.
     *
     * header-field = field-name ":" OWS field-value OWS
     * field-name   = 1*( "!" / "#" / "$" / "%" / "&" / "'" / "*" / "+" / "-" / "." / "^"
     *              / "_" / "`" / "|" / "~" / %x30-39 / ( %x41-5A / %x61-7A ) )
     * OWS          = *( SP / HTAB )
     * field-value  = *( ( %x21-7E / %x80-FF ) [ 1*( SP / HTAB ) ( %x21-7E / %x80-FF ) ] )
     *
     * @see https://tools.ietf.org/html/rfc7230#section-3.2.4
     *
     * @param mixed $header
     * @param mixed $values
     *
     * @return string[]
     */
    private function _validateAndTrimHeader($header, $values): array
    {
        if (
            !\is_string($header)
            ||
            \preg_match("@^[!#$%&'*+.^_`|~0-9A-Za-z-]+$@", $header) !== 1
        ) {
            throw new \InvalidArgumentException('Header name must be an RFC 7230 compatible string.');
        }

        if (!\is_array($values)) {
            // This is simple, just one value.
            if (
                (!\is_numeric($values) && !\is_string($values))
                ||
                \preg_match("@^[ \t\x21-\x7E\x80-\xFF]*$@", (string) $values) !== 1
            ) {
                throw new \InvalidArgumentException('Header values must be RFC 7230 compatible strings.');
            }

            return [\trim((string) $values, " \t")];
        }

        if (empty($values)) {
            throw new \InvalidArgumentException('Header values must be a string or an array of strings, empty array given.');
        }

        // Assert Non empty array
        $returnValues = [];
        foreach ($values as $v) {
            if (
                (!\is_numeric($v) && !\is_string($v))
                ||
                \preg_match("@^[ \t\x21-\x7E\x80-\xFF]*$@", (string) $v) !== 1
            ) {
                throw new \InvalidArgumentException('Header values must be RFC 7230 compatible strings.');
            }

            $returnValues[] = \trim((string) $v, " \t");
        }

        return $returnValues;
    }
}
