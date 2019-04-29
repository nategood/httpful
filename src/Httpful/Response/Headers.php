<?php

/** @noinspection MagicMethodsValidityInspection */
/** @noinspection PhpMissingParentConstructorInspection */

declare(strict_types=1);

namespace Httpful\Response;

use Curl\CaseInsensitiveArray;

/**
 * Class Headers
 */
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
                parent::offsetSet($key, $value);
            }
        }
    }

    /**
     * @param string $string
     *
     * @return Headers
     */
    public static function fromString($string): self
    {
        // init
        $parse_headers = [];

        $headers = \preg_split("/[\r\n]+/", $string, -1, \PREG_SPLIT_NO_EMPTY);

        if ($headers === false) {
            return new self($parse_headers);
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
            if (\array_key_exists($key, $parse_headers)) {
                // See HTTP RFC Sec 4.2 Paragraph 5
                // http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.2
                // If a header appears more than once, it must also be able to
                // be represented as a single header with a comma-separated
                // list of values.  We transform accordingly.
                $parse_headers[$key] .= ',' . $value;
            } else {
                $parse_headers[$key] = $value;
            }
        }

        return new self($parse_headers);
    }

    /**
     * @param string $offset
     * @param string $value
     *
     * @throws \Exception
     */
    public function offsetSet($offset, $value)
    {
        throw new \Exception('Headers are read-only.');
    }

    /**
     * @param string $offset
     *
     * @throws \Exception
     */
    public function offsetUnset($offset)
    {
        throw new \Exception('Headers are read-only.');
    }

    /**
     * @param string $offset
     */
    public function forceUnset($offset)
    {
        parent::offsetUnset($offset);
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        // init
        $return = [];

        foreach ($this as $key => $value) {
            $return[$key] = $value;
        }

        return $return;
    }
}
