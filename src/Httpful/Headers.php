<?php

/** @noinspection MagicMethodsValidityInspection */
/** @noinspection PhpMissingParentConstructorInspection */

declare(strict_types=1);

namespace Httpful;

use Httpful\Exception\ResponseHeaderException;

class Headers implements \ArrayAccess, \Countable, \Iterator
{
    /**
     * @var mixed[] data storage with lowercase keys
     *
     * @see offsetSet()
     * @see offsetExists()
     * @see offsetUnset()
     * @see offsetGet()
     * @see count()
     * @see current()
     * @see next()
     * @see key()
     */
    private $data = [];

    /**
     * @var string[] case-sensitive keys
     *
     * @see offsetSet()
     * @see offsetUnset()
     * @see key()
     */
    private $keys = [];

    /**
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
     * @see https://secure.php.net/manual/en/countable.count.php
     *
     * @return int the number of elements stored in the array
     */
    public function count()
    {
        return (int) \count($this->data);
    }

    /**
     * @see https://secure.php.net/manual/en/iterator.current.php
     *
     * @return mixed data at the current position
     */
    public function current()
    {
        return \current($this->data);
    }

    /**
     * @see https://secure.php.net/manual/en/iterator.key.php
     *
     * @return mixed case-sensitive key at current position
     */
    public function key()
    {
        $key = \key($this->data);

        return $this->keys[$key] ?? $key;
    }

    /**
     * @see https://secure.php.net/manual/en/iterator.next.php
     *
     * @return void
     */
    public function next()
    {
        \next($this->data);
    }

    /**
     * @see https://secure.php.net/manual/en/iterator.rewind.php
     *
     * @return void
     */
    public function rewind()
    {
        \reset($this->data);
    }

    /**
     * @see https://secure.php.net/manual/en/iterator.valid.php
     *
     * @return bool if the current position is valid
     */
    public function valid()
    {
        return \key($this->data) !== null;
    }

    /**
     * @param string $offset the offset to store the data at (case-insensitive)
     * @param mixed  $value  the data to store at the specified offset
     *
     * @return void
     */
    public function forceSet($offset, $value)
    {
        $value = $this->_validateAndTrimHeader($offset, $value);

        $this->offsetSetForce($offset, $value);
    }

    /**
     * @param string $offset
     *
     * @return void
     */
    public function forceUnset($offset)
    {
        $this->offsetUnsetForce($offset);
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
     * Checks if the offset exists in data storage. The index is looked up with
     * the lowercase version of the provided offset.
     *
     * @see https://secure.php.net/manual/en/arrayaccess.offsetexists.php
     *
     * @param string $offset Offset to check
     *
     * @return bool if the offset exists
     */
    public function offsetExists($offset)
    {
        return (bool) \array_key_exists(\strtolower($offset), $this->data);
    }

    /**
     * Return the stored data at the provided offset. The offset is converted to
     * lowercase and the lookup is done on the data store directly.
     *
     * @see https://secure.php.net/manual/en/arrayaccess.offsetget.php
     *
     * @param string $offset offset to lookup
     *
     * @return mixed the data stored at the offset
     */
    public function offsetGet($offset)
    {
        $offsetLower = \strtolower($offset);

        return $this->data[$offsetLower] ?? null;
    }

    /**
     * @param string $offset
     * @param string $value
     *
     * @throws ResponseHeaderException
     *
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        throw new ResponseHeaderException('Headers are read-only.');
    }

    /**
     * @param string $offset
     *
     * @throws ResponseHeaderException
     *
     * @return void
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
            throw new \InvalidArgumentException('Header name must be an RFC 7230 compatible string: ' . \print_r($header, true));
        }

        if (!\is_array($values)) {
            // This is simple, just one value.
            if (
                (!\is_numeric($values) && !\is_string($values))
                ||
                \preg_match("@^[ \t\x21-\x7E\x80-\xFF]*$@", (string) $values) !== 1
            ) {
                throw new \InvalidArgumentException('Header values must be RFC 7230 compatible strings: ' . \print_r($header, true));
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
                throw new \InvalidArgumentException('Header values must be RFC 7230 compatible strings: ' . \print_r($v, true));
            }

            $returnValues[] = \trim((string) $v, " \t");
        }

        return $returnValues;
    }

    /**
     * Set data at a specified offset. Converts the offset to lowercase, and
     * stores the case-sensitive offset and the data at the lowercase indexes in
     * $this->keys and @this->data.
     *
     * @see https://secure.php.net/manual/en/arrayaccess.offsetset.php
     *
     * @param string|null $offset the offset to store the data at (case-insensitive)
     * @param mixed       $value  the data to store at the specified offset
     *
     * @return void
     */
    private function offsetSetForce($offset, $value)
    {
        if ($offset === null) {
            $this->data[] = $value;
        } else {
            $offsetlower = \strtolower($offset);
            $this->data[$offsetlower] = $value;
            $this->keys[$offsetlower] = $offset;
        }
    }

    /**
     * Unsets the specified offset. Converts the provided offset to lowercase,
     * and unsets the case-sensitive key, as well as the stored data.
     *
     * @see https://secure.php.net/manual/en/arrayaccess.offsetunset.php
     *
     * @param string $offset the offset to unset
     *
     * @return void
     */
    private function offsetUnsetForce($offset)
    {
        $offsetLower = \strtolower($offset);

        unset($this->data[$offsetLower], $this->keys[$offsetLower]);
    }
}
