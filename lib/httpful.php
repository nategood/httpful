<?php

namespace Httpful;

/**
 * Class to organize the Mime stuff a bit more 
 * @author Nate Good <me@nategood.com>
 */
class Mime {
	const JSON	= 'application/json';
	const XML	= 'application/xml';
	const FORM	= 'application/x-www-form-urlencoded';
	const PLAIN = 'text/plain';
	const JS	= 'text/javascript';
	const HTML  = 'text/html';

	/** 
	 * Map short name for a mime type 
	 * to a full proper mime type
	 */
	public static $mimes = array(
		'json' 	=> self::JSON,
		'xml'	=> self::XML,
		'form'	=> self::FORM,
		'plain' => self::PLAIN,
		'html'	=> self::HTML,
	);

	/** 
	 * Get the full Mime Type name from a "short name". 
	 * Returns the short if no mapping was found.
	 * @return string full mime type (e.g. application/json)
	 * @param string common name for mime type (e.g. json)
	 */
	public static function getFullMime($short_name) {
		return array_key_exists($short_name, self::$mimes) ? self::$mimes[$short_name] : $short_name;
	}
}

class Http {
	const HEAD		= 'HEAD';
	const GET		= 'GET';
	const POST		= 'POST';
	const PUT		= 'PUT';
	const DELETE	= 'DELETE';
	const OPTIONS	= 'OPTIONS';
}

class Response {
	// TODO magic method getters for headers?

	public $body, $raw_body, $headers, $request;

	/**
	 * @param string $body
	 * @param string $headers
	 * @param Request $request
	 */
	public function __construct($body, $headers, Request $request) {
		$this->request	= $request;
		$this->raw_body = $body;
		$this->body 	= $this->_parse($body);
		$this->headers 	= $headers; // todo $this->_parseHeaders
	}

	/**
	 * Parse the response into a clean data structure
	 * (most often an associative array) based on the expected 
	 * Mime type.
	 * @return array|string|object the response parse accordingly
	 * @param string Http response body
	 */
	private function _parse($body) {
	    // If provided, use custom parsing callback
        if (isset($this->request->parseCallback)) {
            // echo call_user_func($this->request->parseCallback, $body);exit;
            return call_user_func($this->request->parseCallback, $body);
        }
	    
	    // Fallback to sensible parsing defaults
		switch ($this->request->expected_type) {
			case Mime::JSON: 
				$parsed = json_decode($body, false);
				if (!$parsed) throw new \Exception("Unable to parse response as JSON");
				break;
            case Mime::XML:
                // XML Parsing not yet supported :-(
                throw new \Exception('XML not yet supported');
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
	 * @return string
	 */
	public function __toString() {
		return $this->raw_body;
	}
}

/**
 * Clean, simple class for sending HTTP requests
 * in PHP. 
 * 
 * There is an emphasis of readability without loosing concise
 * syntax.  As such, you will notice that the library lends
 * itself very nicely to "chaining".  You will see several "alias" 
 * methods: more readable method definitions that wrap
 * their more concise counterparts.  You will also notice
 * no public constructor.  This two adds to the readability 
 * and "chainabilty" of the library.
 * 
 * @author Nate Good <me@nategood.com>
 */
class Request {
	public $uri, $method = Http::GET, $headers = array(), $strict_ssl = false, $content_type = Mime::JSON, $expected_type = Mime::JSON, 
	    $additional_curl_opts = array(),
		$username, $password,
		$parseCallback, $errorCallback;

	// Curl Handle
	public $_ch,
		$_debug;

	// Template Request object
	private static $_template;

	/**
	 * We made the constructor private to force the factory style.  This was 
	 * done to keep the syntax cleaner and better the support the idea of 
	 * "default templates".  Very basic and flexible as it is only intended 
	 * for internal use.
	 * @param array $attrs hash of initial attribute values
	 */
	private function __construct($attrs = null) {
		if (!is_array($attrs)) return;
		foreach ($attrs as $attr => $value) {
			$this->$attr = $value;
		}
	}

	// Defaults Management
	
	/** 
	 * Let's you configure default settings for this
	 * class from a template Request object.  Simply construct a 
	 * Request object as much as you want to and then pass it to
	 * this method.  It will then lock in those settings from 
	 * that template object.
	 * The most common of which may be default mime
	 * settings or strict ssl settings.
	 * Again some slight memory overhead incurred here but in the grand 
	 * scheme of things as it typically only occurs once
	 * @param Request $template
	 */
	public static function ini(Request $template) {
		self::$_template = clone $template;
	}
	
	/**
	 * Reset the default template back to the 
	 * library defaults.
	 */
	public static function resetIni() {
		self::_initializeDefaults();
	}
	
	/**
	 * Get default for a value based on the template object
	 * @return mixed default value
	 * @param string|null $attr Name of attribute (e.g. mime, headers)
	 *    if null just return the whole template object;
	 */
	public function d($attr) {
		return isset($attr) ? self::$_template->$attr : self::$_template;
	}

	// Accessors

	/**
	 * @return bool has the internal curl request been initialized?
	*/
	public function hasBeenInitialized() {
		return isset($this->_ch);
	}

	/**
	 * @return bool Is this request setup for basic auth?
	*/
	public function hasBasicAuth() {
		return isset($this->password) && isset($this->username);
	}

	/**
	 * Actually send off the request, and parse the response
	 * @return string|associative array of parsed results
 	 * @throws \Exception when unable to parse or communicate w server
     */
	public function send() {
		if (!$this->hasBeenInitialized())
			$this->_curlPrep();

		$result = curl_exec($this->_ch);

		if ($result === false) {
            // return new Response(400);
            $this->_error(curl_error($this->_ch));
            throw new \Exception('Unable to connect.');
		}
		
		list($header, $body) = explode("\r\n\r\n", $result, 2);

		return new Response($body, $header, $this);
	}
	public function sendIt() {return $this->send();}

	// Setters
	
	/** 
	 * @return Request this
	 * @param string $uri
	 */
	public function uri($uri) {
		$this->uri = $uri;
		return $this;
	}
	
	/** 
	 * User Basic Auth. WARNING Only use when over SSL/TSL.
	 * @return Request this
	 * @param string $username
	 * @param string $password
	 */
	public function basicAuth($username, $password) {
		$this->username = $username;
		$this->password = $password;
		return $this;
	}
	// @alias of basicAuth
	public function authenticateWith($username, $password) {return $this->basicAuth($username, $password);}
	
	/**
	 * Set the body of the request
	 * @return Request this
	 * @param mixed $payload
	 * @param string $mimeType
	 */
	public function body($payload, $mimeType = null) {
		$this->mime($mimeType);
		$this->payload = $payload; 
		// Intentially don't call _detectPayload yet.  Wait until
		// we actually send off the request to convert payload to string
		return $this;
	}

	/**
	 * Helper function to set the Content type and Expected as same in 
	 * one swoop
	 * @return Request this
	 * @param string $mime mime type to use for content type and expected return type
	 */
	public function mime($mime) {
		if (empty($mime)) return $this;
		$this->content_type = $this->expected_type = Mime::getFullMime($mime);
		return $this;
	}
	// @alias of mime
	public function sendsAndExpectsType($mime) { return $this->mime($mime); }
	
	/**
	 * Set the method.  Shouldn't be called often as the preferred syntax
	 * for instantiation is the method specific factory methods.
	 * @return Request this
	 * @param string $method
	 */
	public function method($method) {
		if (empty($method)) return $this;
		$this->method = $method;
		return $this;
	}
	
	/**
	 * @return Request this
	 * @param string $mime
	 */
	public function expects($mime) {
		if (empty($mime)) return $this;
		$this->expected_type 	= Mime::getFullMime($mime);
		return $this;
	}
	// @alias of expects
	public function expectsType($mime) { return $this->expects($mime); }
	
	/**
	 * @return Request this
	 * @param string $mime
	 */
	public function contentType($mime) {
		if (empty($mime)) return $this;
		$this->content_type 	= Mime::getFullMime($mime);
		return $this;
	}
	// @alias of contentType
	public function sends($mime) { return $this->contentType($mime); }
	// @alias of contentType
	public function sendsType($mime) { return $this->contentType($mime); }
	
	/**
	 * Do we strictly enforce SSL verification?
	 * @return Request this
	 * @param bool $strict
	*/
	public function strictSSL($strict) {
		$this->strict_ssl = $strict;
		return $this;
	}
	public function withoutStrictSSL() { return $this->strictSSL(false); }
	public function withStrictSSL() { return $this->strictSSL(true); }
	
	/**
	 * Add an additional header to the request 
	 * Can also use the cleaner syntax of 
	 * $Request->withMyHeaderName($my_value);  See the 
	 * `__call` method.
	 * @return Request this
	 * @param string $header_name
	 * @param string $value
	 */
	public function addHeader($header_name, $value) {
		$this->headers[$header_name] = $value;
		return $this;
	}
	
	/**
	 * Use a custom function to parse the response.
	 * @return Request this
	 * @param Closure $callback Takes the raw body of 
	 *    the http response and returns a mixed
	 */
	public function parseWith(\Closure $callback) {
	    $this->parseCallback = $callback;
	    return $this;
	}
	// @alias parseWith
	public function parseResponsesWith(\Closure $callback) { return $this->parseWith($callback); }
	
	/**
	 * Magic method allows for neatly setting other headers in a 
	 * similar syntax as the other setters
	 * @return Request this
	 * @param string $method "missing" method name called 
	 *    the method name called should be the name of the header that you
	 *    are trying to set in camel case without dashes e.g. to set a 
	 *    header for Content-Type you would use contentType() or more commonly
	 *    to add a custom header like X-My-Header, you would use xMyHeader().
	 *    To promote readability, you can optionally prefix these methods with
	 *    "with"  (e.g. withXMyHeader("blah") instead of xMyHeader("blah")).
	 * @param array $args in this case, there should only ever be 1 argument provided
	 *    and that argument should be a string value of the header we're setting
	 */
	public function __call($method, $args) {
		if (count($args) === 0) 
			return;
		
		// Strip the sugar.  If it leads with "with", strip.
		// This is okay because: No defined HTTP headers begin with with,
		// and if you are defining a custom header, the standard is to prefix it
		// with an "X-", so that should take care of any collisions.
		if (substr($method, 0, 4) == 'with')
			$method = substr($method, 4);
		
		// Precede upper case letters with dashes, uppercase the first letter of method
		$header = ucwords(preg_replace('/[A-Z]/', '-$1', $method));
		$this->addHeader($header, $args[0]);
		return $this;
	}
	
	// Custom Handlers
	
    // /**
    //  * Ability to set a custom response handler
    //  * @param function $callback
    //  */
    // public function setResponseHandler($callback) {
    //  // TODO
    // }
	
	// Internal Functions
	
	/**
	 * This is the default template to use if no 
	 * template has been provided.  The template
	 * tells the class which default values to use.
	 * While there is a slight overhead for object
	 * creation once per execution (not once per
	 * Request instantiation), it promotes readability
	 * and flexibility within the class.
	 */
	private static function _initializeDefaults() {
		// This is the only place you will
		// see this constructor syntax.  It
		// is only done here to prevent infinite 
		// recusion.  Do not use this syntax elsewhere.
		// It goes against the whole readability
		// and transparency idea.
		self::$_template = new Request(array('method' => Http::GET));
		
		// This is more like it...
		self::$_template
			->sendsType(Mime::JSON)
			->expectsType(Mime::JSON)
			->withoutStrictSSL();
	}
	
	/**
	 * Set the defaults on a newly instantiated object
	 * Doesn't copy variables prefixed with _
	 * @return Request this
	 */
	private function _setDefaults() {
		if (!isset(self::$_template)) 
			self::_initializeDefaults();
		foreach (self::$_template as $k=>$v) {
			if ($k[0] != '_')
				$this->$k = $v;
		}
		return $this;
	}

	private function _error($error) {
        // Default actions write to error log
        erorr_log($error);
	}

	/** 
	 * Factory style constructor works nicer for chaining.  This
	 * should also really only be used internally.  The Request::get, 
	 * Request::post syntax is preferred as it is more readable.
	 * @return Request
	 * @param string $method Http Method
	 * @param string $mime Mime Type to Use
	 */
	public static function init($method = null, $mime = null) {
		// Setup the default template if need be
		if (!isset(self::$_template)) 
			self::_initializeDefaults();

		$request = new Request();
		return $request
			->_setDefaults()
			->method($method)
			->sendsType($mime)
			->expectsType($mime);
	}

	/**
	 * Does the heavy lifting.  Uses de facto HTTP
	 * library cURL to set up the HTTP request.
	 * Note: It does NOT actually send the request
	 * @return Request $this;
	 */ 
	private function _curlPrep() {
		// Check for required stuff
		if (!isset($this->uri))
			throw new \Exception('Attempting to send a request before defining a URI endpoint.');

		$ch = curl_init($this->uri);

		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method);

		if ($this->hasBasicAuth())
	    	curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);

	    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->strict_ssl);

	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$headers = array("Content-Type: {$this->content_type}", "Accept: {$this->expected_type}, text/plain");
		foreach ($this->headers as $header => $value) {
			$headers[] = "$header: $value";
		}
	    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		if (isset($this->payload))
			curl_setopt($ch, CURLOPT_POSTFIELDS, $this->_detectPayload($this->payload));

		if ($this->_debug) {
			curl_setopt($ch, CURLOPT_VERBOSE, true);
		}

		curl_setopt($ch, CURLOPT_HEADER, 1);

        // If there are some additional curl opts that the user wants
        // to set, we can tack them in here
        foreach ($this->additional_curl_opts as $curlopt => $curlval) {
            curl_setopt($ch, $curlopt, $curlval);
        }

		$this->_ch = $ch;

		return $this;
	}
	
	/**
	 * Semi-reluctantly added this as a way to add in curl opts
	 * that are not otherwise accessible from the rest of the API.
	 * @return Request $this
	 * @param string $curlopt
	 * @param $curloptval $mixed
	 */
	public function addOnCurlOption($curlopt, $curloptval) {
	    $this->additional_curl_opts[$curlopt] = $curloptval;
	    return $this;
	}
	
	/**
	 * Turn payload from structure data into 
	 * a string based on the current Mime type
	 * @return string
	 */
	private function _detectPayload($payload) {
		if (empty($payload) || is_string($payload))
			return $payload;
		
		switch($this->content_type) {
			case Mime::JSON: 
				return json_encode($payload);
			case Mime::FORM: 
				return http_build_query($payload);
			case Mime::XML:
				throw new \Exception('XML not yet supported');
			default:
				return (string) $payload;
		}
	}
	
	// Http Method Sugar
	
	/** 
	 * HTTP Method Get
	 * @return Request
	 * @param string $uri optional uri to use
	 * @param string $mime expected 
	 */
	public static function get($uri, $mime = null) {
		return self::init(Http::GET)->uri($uri)->mime($mime);
	}
	public static function getQuick($uri, $mime = null) { return self::get($uri, $mime)->send(); }
	
	/** 
	 * HTTP Method Post
	 * @param string $uri optional uri to use
	 * @param string $payload data to send in body of request
	 * @param string $mime MIME to use for Content-Type
	 */
	public static function post($uri, $payload = null, $mime = null) {
		return self::init(Http::POST)->uri($uri)->body($payload, $mime);
	}
	
	/** 
	 * HTTP Method Put
	 * @param string $uri optional uri to use
	 */
	public static function put($uri, $payload = null, $mime = null) {
		return self::init(Http::PUT)->uri($uri)->body($payload, $mime);
	}
	
	/** 
	 * HTTP Method Delete
	 * @param string $uri optional uri to use
	 */
	public static function delete($uri, $mime = null) {
		return self::init(Http::DELETE)->uri($uri)->mime($mime);
	}
	
	/** 
	 * HTTP Method Head
	 * @param string $uri optional uri to use
	 */
	public static function head($uri) {
		return self::init(Http::HEAD)->uri($uri);
	}
	
	/** 
	 * HTTP Method Options
	 * @param string $uri optional uri to use
	 */
	public static function options($uri) {
		return self::init(Http::OPTIONS)->uri($uri);
	}
}