<?php

declare(strict_types=1);

namespace Httpful;

use Psr\Http\Message\UriInterface;

class Uri implements UriInterface
{
    /**
     * Absolute http and https URIs require a host per RFC 7230 Section 2.7
     * but in generic URIs the host can be empty. So for http(s) URIs
     * we apply this default host when no host is given yet to form a
     * valid URI.
     */
    const HTTP_DEFAULT_HOST = 'localhost';

    /**
     * @var array
     */
    private static $defaultPorts = [
        'http'   => 80,
        'https'  => 443,
        'ftp'    => 21,
        'gopher' => 70,
        'nntp'   => 119,
        'news'   => 119,
        'telnet' => 23,
        'tn3270' => 23,
        'imap'   => 143,
        'pop'    => 110,
        'ldap'   => 389,
    ];

    /**
     * @var string
     */
    private static $charUnreserved = 'a-zA-Z0-9_\-\.~';

    /**
     * @var string
     */
    private static $charSubDelims = '!\$&\'\(\)\*\+,;=';

    /**
     * @var array
     */
    private static $replaceQuery = [
        '=' => '%3D',
        '&' => '%26',
    ];

    /**
     * @var string uri scheme
     */
    private $scheme = '';

    /**
     * @var string uri user info
     */
    private $userInfo = '';

    /**
     * @var string uri host
     */
    private $host = '';

    /**
     * @var int|null uri port
     */
    private $port;

    /**
     * @var string uri path
     */
    private $path = '';

    /**
     * @var string uri query string
     */
    private $query = '';

    /**
     * @var string uri fragment
     */
    private $fragment = '';

    /**
     * @param string $uri URI to parse
     */
    public function __construct($uri = '')
    {
        // weak type check to also accept null until we can add scalar type hints
        if ($uri !== '') {
            $parts = \parse_url($uri);

            if ($parts === false) {
                throw new \InvalidArgumentException("Unable to parse URI: ${uri}");
            }

            $this->_applyParts($parts);
        }
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return self::composeComponents(
            $this->scheme,
            $this->getAuthority(),
            $this->path,
            $this->query,
            $this->fragment
        );
    }

    /**
     * @return string
     */
    public function getAuthority(): string
    {
        if ($this->host === '') {
            return '';
        }

        $authority = $this->host;
        if ($this->userInfo !== '') {
            $authority = $this->userInfo . '@' . $authority;
        }

        if ($this->port !== null) {
            $authority .= ':' . $this->port;
        }

        return $authority;
    }

    /**
     * @return string
     */
    public function getFragment(): string
    {
        return $this->fragment;
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return int|null
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @return string
     */
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * @return string
     */
    public function getScheme(): string
    {
        return $this->scheme;
    }

    /**
     * @return string
     */
    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    /**
     * @param string $fragment
     *
     * @return $this|Uri|UriInterface
     */
    public function withFragment($fragment)
    {
        $fragment = $this->_filterQueryAndFragment($fragment);

        if ($this->fragment === $fragment) {
            return $this;
        }

        $new = clone $this;
        $new->fragment = $fragment;

        return $new;
    }

    /**
     * @param string $host
     *
     * @return $this|Uri|UriInterface
     */
    public function withHost($host)
    {
        $host = $this->_filterHost($host);

        if ($this->host === $host) {
            return $this;
        }

        $new = clone $this;
        $new->host = $host;
        $new->_validateState();

        return $new;
    }

    /**
     * @param string $path
     *
     * @return $this|Uri|UriInterface
     */
    public function withPath($path)
    {
        $path = $this->_filterPath($path);

        if ($this->path === $path) {
            return $this;
        }

        $new = clone $this;
        $new->path = $path;
        $new->_validateState();

        return $new;
    }

    /**
     * @param int|null $port
     *
     * @return $this|Uri|UriInterface
     */
    public function withPort($port)
    {
        $port = $this->_filterPort($port);

        if ($this->port === $port) {
            return $this;
        }

        $new = clone $this;
        $new->port = $port;
        $new->_removeDefaultPort();
        $new->_validateState();

        return $new;
    }

    /**
     * @param string $query
     *
     * @return $this|Uri|UriInterface
     */
    public function withQuery($query)
    {
        $query = $this->_filterQueryAndFragment($query);

        if ($this->query === $query) {
            return $this;
        }

        $new = clone $this;
        $new->query = $query;

        return $new;
    }

    /**
     * @param string $scheme
     *
     * @return $this|Uri|UriInterface
     */
    public function withScheme($scheme)
    {
        $scheme = $this->_filterScheme($scheme);

        if ($this->scheme === $scheme) {
            return $this;
        }

        $new = clone $this;
        $new->scheme = $scheme;
        $new->_removeDefaultPort();
        $new->_validateState();

        return $new;
    }

    /**
     * @param string      $user
     * @param string|null $password
     *
     * @return $this|Uri|UriInterface
     */
    public function withUserInfo($user, $password = null)
    {
        $info = $this->_filterUserInfoComponent($user);
        if ($password !== null) {
            $info .= ':' . $this->_filterUserInfoComponent($password);
        }

        if ($this->userInfo === $info) {
            return $this;
        }

        $new = clone $this;
        $new->userInfo = $info;
        $new->_validateState();

        return $new;
    }

    /**
     * Composes a URI reference string from its various components.
     *
     * Usually this method does not need to be called manually but instead is used indirectly via
     * `Psr\Http\Message\UriInterface::__toString`.
     *
     * PSR-7 UriInterface treats an empty component the same as a missing component as
     * getQuery(), getFragment() etc. always return a string. This explains the slight
     * difference to RFC 3986 Section 5.3.
     *
     * Another adjustment is that the authority separator is added even when the authority is missing/empty
     * for the "file" scheme. This is because PHP stream functions like `file_get_contents` only work with
     * `file:///myfile` but not with `file:/myfile` although they are equivalent according to RFC 3986. But
     * `file:///` is the more common syntax for the file scheme anyway (Chrome for example redirects to
     * that format).
     *
     * @param string $scheme
     * @param string $authority
     * @param string $path
     * @param string $query
     * @param string $fragment
     *
     * @return string
     *
     * @see https://tools.ietf.org/html/rfc3986#section-5.3
     */
    public static function composeComponents($scheme, $authority, $path, $query, $fragment): string
    {
        // init
        $uri = '';

        // weak type checks to also accept null until we can add scalar type hints
        if ($scheme !== '') {
            $uri .= $scheme . ':';
        }

        if ($authority !== '' || $scheme === 'file') {
            $uri .= '//' . $authority;
        }

        $uri .= $path;

        if ($query !== '') {
            $uri .= '?' . $query;
        }

        if ($fragment !== '') {
            $uri .= '#' . $fragment;
        }

        return $uri;
    }

    /**
     * Creates a URI from a hash of `parse_url` components.
     *
     * @param array $parts
     *
     * @throws \InvalidArgumentException if the components do not form a valid URI
     *
     * @return UriInterface
     *
     * @see http://php.net/manual/en/function.parse-url.php
     */
    public static function fromParts(array $parts): UriInterface
    {
        $uri = new self();
        $uri->_applyParts($parts);
        $uri->_validateState();

        return $uri;
    }

    /**
     * Whether the URI is absolute, i.e. it has a scheme.
     *
     * An instance of UriInterface can either be an absolute URI or a relative reference. This method returns true
     * if it is the former. An absolute URI has a scheme. A relative reference is used to express a URI relative
     * to another URI, the base URI. Relative references can be divided into several forms:
     * - network-path references, e.g. '//example.com/path'
     * - absolute-path references, e.g. '/path'
     * - relative-path references, e.g. 'subpath'
     *
     * @param UriInterface $uri
     *
     * @return bool
     *
     * @see Uri::isNetworkPathReference
     * @see Uri::isAbsolutePathReference
     * @see Uri::isRelativePathReference
     * @see https://tools.ietf.org/html/rfc3986#section-4
     */
    public static function isAbsolute(UriInterface $uri): bool
    {
        return $uri->getScheme() !== '';
    }

    /**
     * Whether the URI is a absolute-path reference.
     *
     * A relative reference that begins with a single slash character is termed an absolute-path reference.
     *
     * @param UriInterface $uri
     *
     * @return bool
     *
     * @see https://tools.ietf.org/html/rfc3986#section-4.2
     */
    public static function isAbsolutePathReference(UriInterface $uri): bool
    {
        return $uri->getScheme() === ''
               &&
               $uri->getAuthority() === ''
               &&
               isset($uri->getPath()[0])
               &&
               $uri->getPath()[0] === '/';
    }

    /**
     * Whether the URI has the default port of the current scheme.
     *
     * `Psr\Http\Message\UriInterface::getPort` may return null or the standard port. This method can be used
     * independently of the implementation.
     *
     * @param UriInterface $uri
     *
     * @return bool
     */
    public static function isDefaultPort(UriInterface $uri): bool
    {
        return $uri->getPort() === null
               ||
               (
                   isset(self::$defaultPorts[$uri->getScheme()])
                   &&
                   $uri->getPort() === self::$defaultPorts[$uri->getScheme()]
               );
    }

    /**
     * Whether the URI is a network-path reference.
     *
     * A relative reference that begins with two slash characters is termed an network-path reference.
     *
     * @param UriInterface $uri
     *
     * @return bool
     *
     * @see https://tools.ietf.org/html/rfc3986#section-4.2
     */
    public static function isNetworkPathReference(UriInterface $uri): bool
    {
        return $uri->getScheme() === '' && $uri->getAuthority() !== '';
    }

    /**
     * Whether the URI is a relative-path reference.
     *
     * A relative reference that does not begin with a slash character is termed a relative-path reference.
     *
     * @param UriInterface $uri
     *
     * @return bool
     *
     * @see https://tools.ietf.org/html/rfc3986#section-4.2
     */
    public static function isRelativePathReference(UriInterface $uri): bool
    {
        return $uri->getScheme() === ''
               &&
               $uri->getAuthority() === ''
               &&
               (!isset($uri->getPath()[0]) || $uri->getPath()[0] !== '/');
    }

    /**
     * Whether the URI is a same-document reference.
     *
     * A same-document reference refers to a URI that is, aside from its fragment
     * component, identical to the base URI. When no base URI is given, only an empty
     * URI reference (apart from its fragment) is considered a same-document reference.
     *
     * @param UriInterface      $uri  The URI to check
     * @param UriInterface|null $base An optional base URI to compare against
     *
     * @return bool
     *
     * @see https://tools.ietf.org/html/rfc3986#section-4.4
     */
    public static function isSameDocumentReference(UriInterface $uri, UriInterface $base = null): bool
    {
        if ($base !== null) {
            $uri = UriResolver::resolve($base, $uri);

            return ($uri->getScheme() === $base->getScheme())
                   &&
                   ($uri->getAuthority() === $base->getAuthority())
                   &&
                   ($uri->getPath() === $base->getPath())
                   &&
                   ($uri->getQuery() === $base->getQuery());
        }

        return $uri->getScheme() === '' && $uri->getAuthority() === '' && $uri->getPath() === '' && $uri->getQuery() === '';
    }

    /**
     * Creates a new URI with a specific query string value.
     *
     * Any existing query string values that exactly match the provided key are
     * removed and replaced with the given key value pair.
     *
     * A value of null will set the query string key without a value, e.g. "key"
     * instead of "key=value".
     *
     * @param UriInterface $uri   URI to use as a base
     * @param string       $key   key to set
     * @param string|null  $value Value to set
     *
     * @return UriInterface
     */
    public static function withQueryValue(UriInterface $uri, $key, $value): UriInterface
    {
        $result = self::_getFilteredQueryString($uri, [$key]);

        $result[] = self::_generateQueryString($key, $value);

        /** @noinspection ImplodeMissUseInspection */
        return $uri->withQuery(\implode('&', $result));
    }

    /**
     * Creates a new URI with multiple specific query string values.
     *
     * It has the same behavior as withQueryValue() but for an associative array of key => value.
     *
     * @param UriInterface $uri           URI to use as a base
     * @param array        $keyValueArray Associative array of key and values
     *
     * @return UriInterface
     */
    public static function withQueryValues(UriInterface $uri, array $keyValueArray): UriInterface
    {
        $result = self::_getFilteredQueryString($uri, \array_keys($keyValueArray));

        foreach ($keyValueArray as $key => $value) {
            $result[] = self::_generateQueryString($key, $value);
        }

        /** @noinspection ImplodeMissUseInspection */
        return $uri->withQuery(\implode('&', $result));
    }

    /**
     * Creates a new URI with a specific query string value removed.
     *
     * Any existing query string values that exactly match the provided key are
     * removed.
     *
     * @param uriInterface $uri URI to use as a base
     * @param string       $key query string key to remove
     *
     * @return UriInterface
     */
    public static function withoutQueryValue(UriInterface $uri, $key): UriInterface
    {
        $result = self::_getFilteredQueryString($uri, [$key]);

        /** @noinspection ImplodeMissUseInspection */
        return $uri->withQuery(\implode('&', $result));
    }

    /**
     * Apply parse_url parts to a URI.
     *
     * @param array<string,mixed> $parts array of parse_url parts to apply
     *
     * @return void
     */
    private function _applyParts(array $parts)
    {
        $this->scheme = isset($parts['scheme'])
            ? $this->_filterScheme($parts['scheme'])
            : '';
        $this->userInfo = isset($parts['user'])
            ? $this->_filterUserInfoComponent($parts['user'])
            : '';
        $this->host = isset($parts['host'])
            ? $this->_filterHost($parts['host'])
            : '';
        $this->port = isset($parts['port'])
            ? $this->_filterPort($parts['port'])
            : null;
        $this->path = isset($parts['path'])
            ? $this->_filterPath($parts['path'])
            : '';
        $this->query = isset($parts['query'])
            ? $this->_filterQueryAndFragment($parts['query'])
            : '';
        $this->fragment = isset($parts['fragment'])
            ? $this->_filterQueryAndFragment($parts['fragment'])
            : '';
        if (isset($parts['pass'])) {
            $this->userInfo .= ':' . $this->_filterUserInfoComponent($parts['pass']);
        }

        $this->_removeDefaultPort();
    }

    /**
     * @param string $host
     *
     * @throws \InvalidArgumentException if the host is invalid
     *
     * @return string
     */
    private function _filterHost($host): string
    {
        if (!\is_string($host)) {
            throw new \InvalidArgumentException('Host must be a string');
        }

        return \strtolower($host);
    }

    /**
     * Filters the path of a URI
     *
     * @param string $path
     *
     * @throws \InvalidArgumentException if the path is invalid
     *
     * @return string
     */
    private function _filterPath($path): string
    {
        if (!\is_string($path)) {
            throw new \InvalidArgumentException('Path must be a string');
        }

        return (string) \preg_replace_callback(
            '/(?:[^' . self::$charUnreserved . self::$charSubDelims . '%:@\/]++|%(?![A-Fa-f0-9]{2}))/',
            [$this, '_rawurlencodeMatchZero'],
            $path
        );
    }

    /**
     * @param int|null $port
     *
     * @throws \InvalidArgumentException if the port is invalid
     *
     * @return int|null
     */
    private function _filterPort($port)
    {
        if ($port === null) {
            return null;
        }

        $port = (int) $port;
        if ($port < 1 || $port > 0xffff) {
            throw new \InvalidArgumentException(
                \sprintf('Invalid port: %d. Must be between 1 and 65535', $port)
            );
        }

        return $port;
    }

    /**
     * Filters the query string or fragment of a URI.
     *
     * @param string $str
     *
     * @throws \InvalidArgumentException if the query or fragment is invalid
     *
     * @return string
     */
    private function _filterQueryAndFragment($str): string
    {
        if (!\is_string($str)) {
            throw new \InvalidArgumentException('Query and fragment must be a string');
        }

        return (string) \preg_replace_callback(
            '/(?:[^' . self::$charUnreserved . self::$charSubDelims . '%:@\/\?]++|%(?![A-Fa-f0-9]{2}))/',
            [$this, '_rawurlencodeMatchZero'],
            $str
        );
    }

    /**
     * @param string $scheme
     *
     * @throws \InvalidArgumentException if the scheme is invalid
     *
     * @return string
     */
    private function _filterScheme($scheme): string
    {
        if (!\is_string($scheme)) {
            throw new \InvalidArgumentException('Scheme must be a string');
        }

        return \strtolower($scheme);
    }

    /**
     * @param string $component
     *
     * @throws \InvalidArgumentException if the user info is invalid
     *
     * @return string
     */
    private function _filterUserInfoComponent($component): string
    {
        if (!\is_string($component)) {
            throw new \InvalidArgumentException('User info must be a string');
        }

        return (string) \preg_replace_callback(
            '/(?:[^%' . self::$charUnreserved . self::$charSubDelims . ']+|%(?![A-Fa-f0-9]{2}))/',
            [$this, '_rawurlencodeMatchZero'],
            $component
        );
    }

    /**
     * @param string      $key
     * @param string|null $value
     *
     * @return string
     */
    private static function _generateQueryString($key, $value): string
    {
        // Query string separators ("=", "&") within the key or value need to be encoded
        // (while preventing double-encoding) before setting the query string. All other
        // chars that need percent-encoding will be encoded by withQuery().
        $queryString = \strtr($key, self::$replaceQuery);

        if ($value !== null) {
            $queryString .= '=' . \strtr($value, self::$replaceQuery);
        }

        return $queryString;
    }

    /**
     * @param UriInterface $uri
     * @param string[]     $keys
     *
     * @return array
     */
    private static function _getFilteredQueryString(UriInterface $uri, array $keys): array
    {
        $current = $uri->getQuery();

        if ($current === '') {
            return [];
        }

        $decodedKeys = \array_map('rawurldecode', $keys);

        return \array_filter(
            \explode('&', $current),
            static function ($part) use ($decodedKeys) {
                return !\in_array(\rawurldecode(\explode('=', $part, 2)[0]), $decodedKeys, true);
            }
        );
    }

    /**
     * @param string[] $match
     *
     * @return string
     */
    private function _rawurlencodeMatchZero(array $match): string
    {
        return \rawurlencode($match[0]);
    }

    /**
     * @return void
     */
    private function _removeDefaultPort()
    {
        if ($this->port !== null && self::isDefaultPort($this)) {
            $this->port = null;
        }
    }

    /**
     * @return void
     */
    private function _validateState()
    {
        if ($this->host === '' && ($this->scheme === 'http' || $this->scheme === 'https')) {
            $this->host = self::HTTP_DEFAULT_HOST;
        }

        if ($this->getAuthority() === '') {
            if (\strpos($this->path, '//') === 0) {
                throw new \InvalidArgumentException('The path of a URI without an authority must not start with two slashes "//"');
            }
            if ($this->scheme === '' && \strpos(\explode('/', $this->path, 2)[0], ':') !== false) {
                throw new \InvalidArgumentException('A relative URI must not have a path beginning with a segment containing a colon');
            }
        } elseif (isset($this->path[0]) && $this->path[0] !== '/') {
            /** @noinspection PhpUsageOfSilenceOperatorInspection */
            @\trigger_error(
                'The path of a URI with an authority must start with a slash "/" or be empty. Automagically fixing the URI ' .
                'by adding a leading slash to the path is deprecated since version 1.4 and will throw an exception instead.',
                \E_USER_DEPRECATED
            );
            $this->path = '/' . $this->path;
        }
    }
}
