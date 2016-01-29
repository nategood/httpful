<?php

namespace Httpful\Response;

final class Headers implements \ArrayAccess, \Countable {

    private $headers;

    private function __construct($headers)
    {
        $this->headers = $headers;
    }

    public static function fromString($string)
    {
        $headers = preg_split("/(\r|\n)+/", $string, -1, \PREG_SPLIT_NO_EMPTY);
        $parse_headers = array();
        for ($i = 1; $i < count($headers); $i++) {
            list($key, $raw_value) = explode(':', $headers[$i], 2);
            $key = trim($key);
            $value = trim($raw_value);
            if (array_key_exists($key, $parse_headers)) {
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

    public function offsetExists($offset)
    {
        return isset($this->headers[$offset]);
    }

    public function offsetGet($offset)
    {
        if (isset($this->headers[$offset])) {
            return $this->headers[$offset];
        }
    }

    public function offsetSet($offset, $value)
    {
        throw new \Exception("Headers are read-only.");
    }

    public function offsetUnset($offset)
    {
        throw new \Exception("Headers are read-only.");
    }

    public function count()
    {
        return count($this->headers);
    }

    public function toArray()
    {
        return $this->headers;
    }

}
