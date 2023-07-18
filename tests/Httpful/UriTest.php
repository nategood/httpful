<?php

declare(strict_types=1);

namespace Httpful\tests;

use Httpful\Uri;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class UriTest extends TestCase
{
    const RFC3986_BASE = 'http://a/b/c/d;p?q';

    public function testParsesProvidedUri()
    {
        $uri = new Uri('https://user:pass@example.com:8080/path/123?q=abc#test');

        static::assertSame('https', $uri->getScheme());
        static::assertSame('user:pass@example.com:8080', $uri->getAuthority());
        static::assertSame('user:pass', $uri->getUserInfo());
        static::assertSame('example.com', $uri->getHost());
        static::assertSame(8080, $uri->getPort());
        static::assertSame('/path/123', $uri->getPath());
        static::assertSame('q=abc', $uri->getQuery());
        static::assertSame('test', $uri->getFragment());
        static::assertSame('https://user:pass@example.com:8080/path/123?q=abc#test', (string) $uri);
    }

    public function testCanTransformAndRetrievePartsIndividually()
    {
        $uri = (new Uri())
            ->withScheme('https')
            ->withUserInfo('user', 'pass')
            ->withHost('example.com')
            ->withPort(8080)
            ->withPath('/path/123')
            ->withQuery('q=abc')
            ->withFragment('test');

        static::assertSame('https', $uri->getScheme());
        static::assertSame('user:pass@example.com:8080', $uri->getAuthority());
        static::assertSame('user:pass', $uri->getUserInfo());
        static::assertSame('example.com', $uri->getHost());
        static::assertSame(8080, $uri->getPort());
        static::assertSame('/path/123', $uri->getPath());
        static::assertSame('q=abc', $uri->getQuery());
        static::assertSame('test', $uri->getFragment());
        static::assertSame('https://user:pass@example.com:8080/path/123?q=abc#test', (string) $uri);
    }

    /**
     * @dataProvider getValidUris
     *
     * @param array $input
     */
    public function testValidUrisStayValid($input)
    {
        $uri = new Uri($input);

        static::assertSame($input, (string) $uri);
    }

    public function getValidUris()
    {
        return [
            ['urn:path-rootless'],
            ['urn:path:with:colon'],
            ['urn:/path-absolute'],
            ['urn:/'],
            // only scheme with empty path
            ['urn:'],
            // only path
            ['/'],
            ['relative/'],
            ['0'],
            // same document reference
            [''],
            // network path without scheme
            ['//example.org'],
            ['//example.org/'],
            ['//example.org?q#h'],
            // only query
            ['?q'],
            ['?q=abc&foo=bar'],
            // only fragment
            ['#fragment'],
            // dot segments are not removed automatically
            ['./foo/../bar'],
        ];
    }

    /**
     * @dataProvider getInvalidUris
     *
     * @param mixed $invalidUri
     */
    public function testInvalidUrisThrowException($invalidUri)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to parse URI');

        new Uri($invalidUri);
    }

    public function getInvalidUris()
    {
        return [
            // parse_url() requires the host component which makes sense for http(s)
            // but not when the scheme is not known or different. So '//' or '///' is
            // currently invalid as well but should not according to RFC 3986.
            ['http://'],
            ['urn://host:with:colon'], // host cannot contain ":"
        ];
    }

    public function testPortMustBeValid()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid port: 100000. Must be between 1 and 65535');

        (new Uri())->withPort(100000);
    }

    public function testWithPortCannotBeNegative()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid port: -1. Must be between 1 and 65535');

        (new Uri())->withPort(-1);
    }

    public function testParseUriPortCannotBeZero()
    {
        $this->expectException(\InvalidArgumentException::class);
        if (\voku\helper\Bootup::is_php('7.3')) {
            $this->expectExceptionMessage('Invalid port: 0');
        } else {
            $this->expectExceptionMessage('Unable to parse URI');
        }

        new Uri('//example.com:0');
    }

    public function testSchemeMustHaveCorrectType()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Scheme must be a string');

        (new Uri())->withScheme([]);
    }

    public function testHostMustHaveCorrectType()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Host must be a string');

        (new Uri())->withHost([]);
    }

    public function testPathMustHaveCorrectType()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Path must be a string');

        (new Uri())->withPath([]);
    }

    public function testQueryMustHaveCorrectType()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Query and fragment must be a string');

        (new Uri())->withQuery([]);
    }

    public function testFragmentMustHaveCorrectType()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Query and fragment must be a string');

        (new Uri())->withFragment([]);
    }

    public function testCanParseFalseyUriParts()
    {
        $uri = new Uri('0://0:0@0/0?0#0');

        static::assertSame('0', $uri->getScheme());
        static::assertSame('0:0@0', $uri->getAuthority());
        static::assertSame('0:0', $uri->getUserInfo());
        static::assertSame('0', $uri->getHost());
        static::assertSame('/0', $uri->getPath());
        static::assertSame('0', $uri->getQuery());
        static::assertSame('0', $uri->getFragment());
        static::assertSame('0://0:0@0/0?0#0', (string) $uri);
    }

    public function testCanConstructFalseyUriParts()
    {
        $uri = (new Uri())
            ->withScheme('0')
            ->withUserInfo('0', '0')
            ->withHost('0')
            ->withPath('/0')
            ->withQuery('0')
            ->withFragment('0');

        static::assertSame('0', $uri->getScheme());
        static::assertSame('0:0@0', $uri->getAuthority());
        static::assertSame('0:0', $uri->getUserInfo());
        static::assertSame('0', $uri->getHost());
        static::assertSame('/0', $uri->getPath());
        static::assertSame('0', $uri->getQuery());
        static::assertSame('0', $uri->getFragment());
        static::assertSame('0://0:0@0/0?0#0', (string) $uri);
    }

    public function getResolveTestCases()
    {
        return [
            [self::RFC3986_BASE, 'g:h',           'g:h'],
            [self::RFC3986_BASE, 'g',             'http://a/b/c/g'],
            [self::RFC3986_BASE, './g',           'http://a/b/c/g'],
            [self::RFC3986_BASE, 'g/',            'http://a/b/c/g/'],
            [self::RFC3986_BASE, '/g',            'http://a/g'],
            [self::RFC3986_BASE, '//g',           'http://g'],
            [self::RFC3986_BASE, '?y',            'http://a/b/c/d;p?y'],
            [self::RFC3986_BASE, 'g?y',           'http://a/b/c/g?y'],
            [self::RFC3986_BASE, '#s',            'http://a/b/c/d;p?q#s'],
            [self::RFC3986_BASE, 'g#s',           'http://a/b/c/g#s'],
            [self::RFC3986_BASE, 'g?y#s',         'http://a/b/c/g?y#s'],
            [self::RFC3986_BASE, ';x',            'http://a/b/c/;x'],
            [self::RFC3986_BASE, 'g;x',           'http://a/b/c/g;x'],
            [self::RFC3986_BASE, 'g;x?y#s',       'http://a/b/c/g;x?y#s'],
            [self::RFC3986_BASE, '',              self::RFC3986_BASE],
            [self::RFC3986_BASE, '.',             'http://a/b/c/'],
            [self::RFC3986_BASE, './',            'http://a/b/c/'],
            [self::RFC3986_BASE, '..',            'http://a/b/'],
            [self::RFC3986_BASE, '../',           'http://a/b/'],
            [self::RFC3986_BASE, '../g',          'http://a/b/g'],
            [self::RFC3986_BASE, '../..',         'http://a/'],
            [self::RFC3986_BASE, '../../',        'http://a/'],
            [self::RFC3986_BASE, '../../g',       'http://a/g'],
            [self::RFC3986_BASE, '../../../g',    'http://a/g'],
            [self::RFC3986_BASE, '../../../../g', 'http://a/g'],
            [self::RFC3986_BASE, '/./g',          'http://a/g'],
            [self::RFC3986_BASE, '/../g',         'http://a/g'],
            [self::RFC3986_BASE, 'g.',            'http://a/b/c/g.'],
            [self::RFC3986_BASE, '.g',            'http://a/b/c/.g'],
            [self::RFC3986_BASE, 'g..',           'http://a/b/c/g..'],
            [self::RFC3986_BASE, '..g',           'http://a/b/c/..g'],
            [self::RFC3986_BASE, './../g',        'http://a/b/g'],
            [self::RFC3986_BASE, 'foo////g',      'http://a/b/c/foo////g'],
            [self::RFC3986_BASE, './g/.',         'http://a/b/c/g/'],
            [self::RFC3986_BASE, 'g/./h',         'http://a/b/c/g/h'],
            [self::RFC3986_BASE, 'g/../h',        'http://a/b/c/h'],
            [self::RFC3986_BASE, 'g;x=1/./y',     'http://a/b/c/g;x=1/y'],
            [self::RFC3986_BASE, 'g;x=1/../y',    'http://a/b/c/y'],
            // dot-segments in the query or fragment
            [self::RFC3986_BASE, 'g?y/./x',       'http://a/b/c/g?y/./x'],
            [self::RFC3986_BASE, 'g?y/../x',      'http://a/b/c/g?y/../x'],
            [self::RFC3986_BASE, 'g#s/./x',       'http://a/b/c/g#s/./x'],
            [self::RFC3986_BASE, 'g#s/../x',      'http://a/b/c/g#s/../x'],
            [self::RFC3986_BASE, 'g#s/../x',      'http://a/b/c/g#s/../x'],
            [self::RFC3986_BASE, '?y#s',          'http://a/b/c/d;p?y#s'],
            ['http://a/b/c/d;p?q#s', '?y',        'http://a/b/c/d;p?y'],
            ['http://u@a/b/c/d;p?q', '.',         'http://u@a/b/c/'],
            ['http://u:p@a/b/c/d;p?q', '.',       'http://u:p@a/b/c/'],
            ['http://a/b/c/d/', 'e',              'http://a/b/c/d/e'],
            ['urn:no-slash', 'e',                 'urn:e'],
            // falsey relative parts
            [self::RFC3986_BASE, '//0',           'http://0'],
            [self::RFC3986_BASE, '0',             'http://a/b/c/0'],
            [self::RFC3986_BASE, '?0',            'http://a/b/c/d;p?0'],
            [self::RFC3986_BASE, '#0',            'http://a/b/c/d;p?q#0'],
        ];
    }

    public function testSchemeIsNormalizedToLowercase()
    {
        $uri = new Uri('HTTP://example.com');

        static::assertSame('http', $uri->getScheme());
        static::assertSame('http://example.com', (string) $uri);

        $uri = (new Uri('//example.com'))->withScheme('HTTP');

        static::assertSame('http', $uri->getScheme());
        static::assertSame('http://example.com', (string) $uri);
    }

    public function testHostIsNormalizedToLowercase()
    {
        $uri = new Uri('//eXaMpLe.CoM');

        static::assertSame('example.com', $uri->getHost());
        static::assertSame('//example.com', (string) $uri);

        $uri = (new Uri())->withHost('eXaMpLe.CoM');

        static::assertSame('example.com', $uri->getHost());
        static::assertSame('//example.com', (string) $uri);
    }

    public function testPortIsNullIfStandardPortForScheme()
    {
        // HTTPS standard port
        $uri = new Uri('https://example.com:443');
        static::assertNull($uri->getPort());
        static::assertSame('example.com', $uri->getAuthority());

        $uri = (new Uri('https://example.com'))->withPort(443);
        static::assertNull($uri->getPort());
        static::assertSame('example.com', $uri->getAuthority());

        // HTTP standard port
        $uri = new Uri('http://example.com:80');
        static::assertNull($uri->getPort());
        static::assertSame('example.com', $uri->getAuthority());

        $uri = (new Uri('http://example.com'))->withPort(80);
        static::assertNull($uri->getPort());
        static::assertSame('example.com', $uri->getAuthority());
    }

    public function testPortIsReturnedIfSchemeUnknown()
    {
        $uri = (new Uri('//example.com'))->withPort(80);

        static::assertSame(80, $uri->getPort());
        static::assertSame('example.com:80', $uri->getAuthority());
    }

    public function testStandardPortIsNullIfSchemeChanges()
    {
        $uri = new Uri('http://example.com:443');
        static::assertSame('http', $uri->getScheme());
        static::assertSame(443, $uri->getPort());

        $uri = $uri->withScheme('https');
        static::assertNull($uri->getPort());
    }

    public function testPortPassedAsStringIsCastedToInt()
    {
        $uri = (new Uri('//example.com'))->withPort('8080');

        static::assertSame(8080, $uri->getPort(), 'Port is returned as integer');
        static::assertSame('example.com:8080', $uri->getAuthority());
    }

    public function testPortCanBeRemoved()
    {
        $uri = (new Uri('http://example.com:8080'))->withPort(null);

        static::assertNull($uri->getPort());
        static::assertSame('http://example.com', (string) $uri);
    }

    public function testAuthorityWithUserInfoButWithoutHost()
    {
        $uri = (new Uri())->withUserInfo('user', 'pass');

        static::assertSame('user:pass', $uri->getUserInfo());
        static::assertSame('', $uri->getAuthority());
    }

    public function uriComponentsEncodingProvider()
    {
        $unreserved = 'a-zA-Z0-9.-_~!$&\'()*+,;=:@';

        return [
            // Percent encode spaces
            ['/pa th?q=va lue#frag ment', '/pa%20th', 'q=va%20lue', 'frag%20ment', '/pa%20th?q=va%20lue#frag%20ment'],
            // Percent encode multibyte
            ['/€?€#€', '/%E2%82%AC', '%E2%82%AC', '%E2%82%AC', '/%E2%82%AC?%E2%82%AC#%E2%82%AC'],
            // Don't encode something that's already encoded
            ['/pa%20th?q=va%20lue#frag%20ment', '/pa%20th', 'q=va%20lue', 'frag%20ment', '/pa%20th?q=va%20lue#frag%20ment'],
            // Percent encode invalid percent encodings
            ['/pa%2-th?q=va%2-lue#frag%2-ment', '/pa%252-th', 'q=va%252-lue', 'frag%252-ment', '/pa%252-th?q=va%252-lue#frag%252-ment'],
            // Don't encode path segments
            ['/pa/th//two?q=va/lue#frag/ment', '/pa/th//two', 'q=va/lue', 'frag/ment', '/pa/th//two?q=va/lue#frag/ment'],
            // Don't encode unreserved chars or sub-delimiters
            ["/{$unreserved}?{$unreserved}#{$unreserved}", "/{$unreserved}", $unreserved, $unreserved, "/{$unreserved}?{$unreserved}#{$unreserved}"],
            // Encoded unreserved chars are not decoded
            ['/p%61th?q=v%61lue#fr%61gment', '/p%61th', 'q=v%61lue', 'fr%61gment', '/p%61th?q=v%61lue#fr%61gment'],
        ];
    }

    /**
     * @dataProvider uriComponentsEncodingProvider
     *
     * @param mixed $input
     * @param mixed $path
     * @param mixed $query
     * @param mixed $fragment
     * @param mixed $output
     */
    public function testUriComponentsGetEncodedProperly($input, $path, $query, $fragment, $output)
    {
        $uri = new Uri($input);
        static::assertSame($path, $uri->getPath());
        static::assertSame($query, $uri->getQuery());
        static::assertSame($fragment, $uri->getFragment());
        static::assertSame($output, (string) $uri);
    }

    public function testWithPathEncodesProperly()
    {
        $uri = (new Uri())->withPath('/baz?#€/b%61r');
        // Query and fragment delimiters and multibyte chars are encoded.
        static::assertSame('/baz%3F%23%E2%82%AC/b%61r', $uri->getPath());
        static::assertSame('/baz%3F%23%E2%82%AC/b%61r', (string) $uri);
    }

    public function testWithQueryEncodesProperly()
    {
        $uri = (new Uri())->withQuery('?=#&€=/&b%61r');
        // A query starting with a "?" is valid and must not be magically removed. Otherwise it would be impossible to
        // construct such an URI. Also the "?" and "/" does not need to be encoded in the query.
        static::assertSame('?=%23&%E2%82%AC=/&b%61r', $uri->getQuery());
        static::assertSame('??=%23&%E2%82%AC=/&b%61r', (string) $uri);
    }

    public function testWithFragmentEncodesProperly()
    {
        $uri = (new Uri())->withFragment('#€?/b%61r');
        // A fragment starting with a "#" is valid and must not be magically removed. Otherwise it would be impossible to
        // construct such an URI. Also the "?" and "/" does not need to be encoded in the fragment.
        static::assertSame('%23%E2%82%AC?/b%61r', $uri->getFragment());
        static::assertSame('#%23%E2%82%AC?/b%61r', (string) $uri);
    }

    public function testAllowsForRelativeUri()
    {
        $uri = (new Uri())->withPath('foo');
        static::assertSame('foo', $uri->getPath());
        static::assertSame('foo', (string) $uri);
    }

    public function testAddsSlashForRelativeUriStringWithHost()
    {
        // If the path is rootless and an authority is present, the path MUST
        // be prefixed by "/".
        $uri = (new Uri())->withPath('foo')->withHost('example.com');
        static::assertSame('/foo', $uri->getPath());
        // concatenating a relative path with a host doesn't work: "//example.comfoo" would be wrong
        static::assertSame('//example.com/foo', (string) $uri);
    }

    public function testRemoveExtraSlashesWithoutHost()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The path of a URI without an authority must not start with two slashes');

        (new Uri())->withPath('//foo');
    }

    public function testDefaultReturnValuesOfGetters()
    {
        $uri = new Uri();

        static::assertSame('', $uri->getScheme());
        static::assertSame('', $uri->getAuthority());
        static::assertSame('', $uri->getUserInfo());
        static::assertSame('', $uri->getHost());
        static::assertNull($uri->getPort());
        static::assertSame('', $uri->getPath());
        static::assertSame('', $uri->getQuery());
        static::assertSame('', $uri->getFragment());
    }

    public function testImmutability()
    {
        $uri = new Uri();

        static::assertNotSame($uri, $uri->withScheme('https'));
        static::assertNotSame($uri, $uri->withUserInfo('user', 'pass'));
        static::assertNotSame($uri, $uri->withHost('example.com'));
        static::assertNotSame($uri, $uri->withPort(8080));
        static::assertNotSame($uri, $uri->withPath('/path/123'));
        static::assertNotSame($uri, $uri->withQuery('q=abc'));
        static::assertNotSame($uri, $uri->withFragment('test'));
    }
}
