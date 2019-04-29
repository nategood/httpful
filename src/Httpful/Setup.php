<?php

declare(strict_types=1);

namespace Httpful;

use Httpful\Handlers\DefaultHandler;
use Httpful\Handlers\MimeHandlerInterface;
use Psr\Log\LoggerInterface;

class Setup
{
    /**
     * @var MimeHandlerInterface[]
     */
    private static $mime_registrar = [];

    /**
     * @var bool
     */
    private static $mime_registered = false;

    /**
     * @var MimeHandlerInterface|null
     */
    private static $mime_default;

    /**
     * @var callable|LoggerInterface|null
     */
    private static $error_global_callback;

    /**
     * Does this particular Mime Type have a parser registered for it?
     *
     * @param string $mimeType
     *
     * @return bool
     */
    public static function hasParserRegistered(string $mimeType): bool
    {
        return isset(self::$mime_registrar[$mimeType]);
    }

    public static function reset()
    {
        self::$mime_registrar = [];
        self::$mime_registered = false;
        self::$error_global_callback = null;
        self::$mime_default = null;

        self::initMimeHandlers();

        self::setupGlobalMimeType();
    }

    /**
     * Register default mime handlers.
     */
    public static function initMimeHandlers()
    {
        if (self::$mime_registered === true) {
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

        self::$mime_registered = true;
    }

    /**
     * @param string               $mimeType
     * @param MimeHandlerInterface $handler
     */
    public static function register($mimeType, MimeHandlerInterface $handler)
    {
        self::$mime_registrar[$mimeType] = $handler;
    }

    /**
     * @param callable|LoggerInterface|null $error_handler
     */
    public static function setupGlobalErrorCallback($error_handler = null)
    {
        if (
            !$error_handler instanceof LoggerInterface
            &&
            !\is_callable($error_handler)
        ) {
            throw new \InvalidArgumentException('Only callable or LoggerInterface are allowed as global error callback.');
        }

        self::$error_global_callback = $error_handler;
    }

    /**
     * @return callable|\Psr\Log\LoggerInterface|null
     */
    public static function getGlobalErrorCallback()
    {
        return self::$error_global_callback;
    }

    /**
     * @param string $mimeType
     *
     * @return MimeHandlerInterface
     */
    public static function setupGlobalMimeType($mimeType = null): MimeHandlerInterface
    {
        self::initMimeHandlers();

        if (isset(self::$mime_registrar[$mimeType])) {
            return self::$mime_registrar[$mimeType];
        }

        if (empty(self::$mime_default)) {
            self::$mime_default = new DefaultHandler();
        }

        return self::$mime_default;
    }
}
