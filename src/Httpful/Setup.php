<?php

declare(strict_types=1);

namespace Httpful;

use Httpful\Handlers\MimeHandlerAdapter;
use Httpful\Handlers\MimeHandlerAdapterInterface;

class Setup
{
    /**
     * @var MimeHandlerAdapterInterface[]
     */
    private static $mimeRegistrar = [];

    /**
     * @var bool
     */
    private static $registered = false;

    /**
     * @var MimeHandlerAdapterInterface
     */
    private static $default;

    /**
     * Does this particular Mime Type have a parser registered for it?
     *
     * @param string $mimeType
     *
     * @return bool
     */
    public static function hasParserRegistered(string $mimeType): bool
    {
        return isset(self::$mimeRegistrar[$mimeType]);
    }

    public static function reset()
    {
        self::$mimeRegistrar = [];
        self::$registered = false;

        self::initMimeHandlers();

        self::setupMimeType();
    }

    /**
     * Register default mime handlers.
     */
    public static function initMimeHandlers()
    {
        if (self::$registered === true) {
            return;
        }

        $handlers = [
            Mime::JSON => new \Httpful\Handlers\JsonHandler(),
            Mime::XML  => new \Httpful\Handlers\XmlHandler(),
            Mime::HTML => new \Httpful\Handlers\HtmlHandler(),
            Mime::FORM => new \Httpful\Handlers\FormHandler(),
            Mime::CSV  => new \Httpful\Handlers\CsvHandler(),
        ];

        foreach ($handlers as $mime => $handler) {
            // Don't overwrite if the handler has already been registered.
            if (self::hasParserRegistered($mime)) {
                continue;
            }

            self::register($mime, $handler);
        }

        self::$registered = true;
    }

    /**
     * @param string                      $mimeType
     * @param MimeHandlerAdapterInterface $handler
     */
    public static function register($mimeType, MimeHandlerAdapterInterface $handler)
    {
        self::$mimeRegistrar[$mimeType] = $handler;
    }

    /**
     * @param string $mimeType
     *
     * @return MimeHandlerAdapterInterface
     */
    public static function setupMimeType($mimeType = null): MimeHandlerAdapterInterface
    {
        self::initMimeHandlers();

        if (isset(self::$mimeRegistrar[$mimeType])) {
            return self::$mimeRegistrar[$mimeType];
        }

        if (empty(self::$default)) {
            self::$default = new MimeHandlerAdapter();
        }

        return self::$default;
    }
}
