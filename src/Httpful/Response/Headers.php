<?php

namespace Httpful\Response;

final class Headers implements \ArrayAccess, \Countable {

    private $headers;

    /**
     * @param array $headers
     */
    private function __construct($headers)
    {
        $this->headers = $headers;
    }

    /**
     * @param string $string
     * @return Headers
     */
    public static function fromString($string)
    {
        $lines = preg_split("/(\r|\n)+/", $string, -1, PREG_SPLIT_NO_EMPTY);
        array_shift($lines); // HTTP HEADER
        $headers = array();
        foreach ($lines as $line) {
            list($name, $value) = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }
        return new self($headers);
    }

    /**
     * @param string $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->headers[strtolower($offset)]);
    }

    /**
     * @param string $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        if (isset($this->headers[$name = strtolower($offset)])) {
            return $this->headers[$name];
        }
    }

    /**
     * @param string $offset
     * @param string $value
     * @throws \Exception
     */
    public function offsetSet($offset, $value)
    {
        throw new \Exception("Headers are read-only.");
    }

    /**
     * @param string $offset
     * @throws \Exception
     */
    public function offsetUnset($offset)
    {
        throw new \Exception("Headers are read-only.");
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->headers);
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->headers;
    }

}