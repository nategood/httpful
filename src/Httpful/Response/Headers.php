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
		$lines = preg_split("/(\r|\n)+/", $string, -1, PREG_SPLIT_NO_EMPTY);
		array_shift($lines); // HTTP HEADER
		$headers = array();
		foreach ($lines as $line) {
			list($name, $value) = explode(':', $line, 2);
			if (array_key_exists($key, $parse_headers)) {
		                // See HTTP RFC Sec 4.2 Paragraph 5
		                // http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.2
		                // If a header appears more than once, it must also be able to
		                // be represented as a single header with a comma-separated
		                // list of values.  We transform accordingly. 
				$headers[strtolower(trim($key))] .= ', '. trim($value);
		            } else {
		                 $headers[strtolower(trim($name))] = trim($value);
		            }
		}
		return new self($headers);
	}

	public function offsetExists($offset)
	{
		return isset($this->headers[strtolower($offset)]);
	}

	public function offsetGet($offset)
	{
		if (isset($this->headers[$name = strtolower($offset)])) {
			return $this->headers[$name];
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
