<?php

namespace Httpful;

/**
 * Models an HTTP response
 *
 * @author Nate Good <me@nategood.com>
 */
class Response
{

    public $body,
           $raw_body,
           $headers,
           $request,
           $code = 0,
           $content_type,
           $charset;
    /**
     * @param string $body
     * @param string $headers
     * @param Request $request
     */
    public function __construct($body, $headers, Request $request)
    {
        $this->request      = $request;
        $this->raw_headers  = $headers;
        $this->raw_body     = $body;

        $this->code         = $this->_parseCode($headers);
        $this->headers      = $this->_parseHeaders($headers);

        $this->_interpretHeaders();

        $this->body         = $this->_parse($body);
    }
    
    /**
     * @return bool Did we receive a 400 or 500?
     */
    public function hasErrors() {
        return $this->code < 100 || $this->code >= 400;
    }
    
    /**
     * @return return bool
     */
    public function hasBody() {
        return !empty($this->body);
    }

    /**
     * Parse the response into a clean data structure
     * (most often an associative array) based on the expected
     * Mime type.
     * @return array|string|object the response parse accordingly
     * @param string Http response body
     */
    public function _parse($body)
    {
        // If the user decided to forgo the automatic
        // smart parsing, short circuit.
        if (!$this->request->auto_parse) {
            return $body;
        }

        // If provided, use custom parsing callback
        if (isset($this->request->parse_callback)) {
            return call_user_func($this->request->parse_callback, $body);
        }

        // Use the Content-Type from the response if we didn't explicitly 
        // specify one as part of our `Request`
        $parse_with = (empty($this->request->expected_type) && isset($this->content_type)) ?
            $this->content_type :
            $this->request->expected_type;

        switch ($parse_with) {
            case Mime::JSON:
                $parsed = json_decode($body, false);
                if (!$parsed) throw new \Exception("Unable to parse response as JSON");
                break;
            case Mime::XML:
                $parsed = simplexml_load_string($body);
                if (!$parsed) throw new \Exception("Unable to parse response as XML");
                break;
            case Mime::FORM:
                $parsed = array();
                parse_str($body, $parsed);
                break;
            default:
                $parsed = $body;
        }
        return $parsed;
    }

    /**
     * Parse text headers from response into
     * array of key value pairs
     * @return array parse headers
     * @param string $headers raw headers
     */
    public function _parseHeaders($headers)
    {
        $headers = preg_split("/(\r|\n)+/", $headers);
        for ($i = 1; $i < count($headers); $i++) {
            list($key, $raw_value) = explode(':', $headers[$i], 2);
            $parse_headers[trim($key)] = trim($raw_value);
        }
        return $parse_headers;
    }

    public function _parseCode($headers)
    {
        $parts = explode(' ', substr($headers, 0, strpos($headers, "\n")));
        if (count($parts) < 2 || !is_numeric($parts[1])) {
            throw new \Exception("Unable to parse response code from HTTP response due to malformed response");
        }
        return intval($parts[1]);
    }

    /**
     * After we've parse the headers, let's clean things
     * up a bit and treat some headers specially
     */
    public function _interpretHeaders()
    {
        // Parse the Content-Type and charset
        $content_type = isset($this->headers['Content-Type']) ? $this->headers['Content-Type'] : '';
        $content_type = explode(';', $content_type);

        $this->content_type = $content_type[0];
        if (count($content_type) == 2 && strpos($content_type[1], '=') !== false) {
            list($nill, $this->charset) = explode('=', $content_type[1]);
        }
        // RFC 2616 states "text/*" Content-Types should have a default
        // charset of ISO-8859-1. "application/*" and other Content-Types
        // are assumed to have UTF-8 unless otherwise specified.
        // http://www.w3.org/Protocols/rfc2616/rfc2616-sec3.html#sec3.7.1
        // http://www.w3.org/International/O-HTTP-charset.en.php
        if (!isset($this->charset)) {
            $this->charset = substr($this->content_type, 5) === 'text/' ? 'iso-8859-1' : 'utf-8';
        }
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->raw_body;
    }
}
