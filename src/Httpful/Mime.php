<?php

declare(strict_types=1);

namespace Httpful;

class Mime
{
    const CSV = 'text/csv';

    const FORM = 'application/x-www-form-urlencoded';

    const HTML = 'text/html';

    const JS = 'text/javascript';

    const JSON = 'application/json';

    const PLAIN = 'text/plain';

    const UPLOAD = 'multipart/form-data';

    const XHTML = 'application/html+xml';

    const XML = 'application/xml';

    const YAML = 'application/x-yaml';

    /**
     * Map short name for a mime type to a full proper mime type.
     *
     * @var array<string, string>
     */
    private static $mimes = [
        'json'       => self::JSON,
        'xml'        => self::XML,
        'form'       => self::FORM,
        'plain'      => self::PLAIN,
        'text'       => self::PLAIN,
        'upload'     => self::UPLOAD,
        'html'       => self::HTML,
        'xhtml'      => self::XHTML,
        'js'         => self::JS,
        'javascript' => self::JS,
        'yaml'       => self::YAML,
        'csv'        => self::CSV,
    ];

    /**
     * Get the full Mime Type name from a "short name".
     * Returns the short if no mapping was found.
     *
     * @param string $short_name common name for mime type (e.g. json)
     *
     * @return string full mime type (e.g. application/json)
     */
    public static function getFullMime($short_name): string
    {
        if (\array_key_exists($short_name, self::$mimes)) {
            return self::$mimes[$short_name];
        }

        return $short_name;
    }

    /**
     * @param string $short_name
     *
     * @return bool
     */
    public static function supportsMimeType($short_name): bool
    {
        return \array_key_exists($short_name, self::$mimes);
    }
}
