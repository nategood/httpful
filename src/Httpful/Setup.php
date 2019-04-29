<?php

declare(strict_types=1);

namespace Httpful;

use Httpful\Handlers\DefaultMimeHandler;
use Httpful\Handlers\MimeHandlerInterface;
use Psr\Log\LoggerInterface;

final class Setup
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
    private static $global_mime_handler;

    /**
     * @var callable|LoggerInterface|null
     */
    private static $global_error_handler;

    /**
     * @return callable|\Psr\Log\LoggerInterface|null
     */
    public static function getGlobalErrorHandler()
    {
        return self::$global_error_handler;
    }

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

    /**
     * Register default mime handlers.
     */
    public static function initMimeHandlers()
    {
        if (self::$mime_registered === true) {
            return;
        }

        $handlers = [
            Mime::JSON => new \Httpful\Handlers\JsonMimeHandler(),
            Mime::XML  => new \Httpful\Handlers\XmlMimeHandler(),
            Mime::HTML => new \Httpful\Handlers\HtmlMimeHandler(),
            Mime::FORM => new \Httpful\Handlers\FormMimeHandler(),
            Mime::CSV  => new \Httpful\Handlers\CsvMimeHandler(),
        ];

        foreach ($handlers as $mime => $handler) {
            // Don't overwrite if the handler has already been registered.
            if (self::hasParserRegistered($mime)) {
                continue;
            }

            self::registerMimeHandler($mime, $handler);
        }

        self::$mime_registered = true;
    }

    /**
     * @param callable|LoggerInterface|null $error_handler
     */
    public static function registerGlobalErrorHandler($error_handler = null)
    {
        if (
            !$error_handler instanceof LoggerInterface
            &&
            !\is_callable($error_handler)
        ) {
            throw new \InvalidArgumentException('Only callable or LoggerInterface are allowed as global error callback.');
        }

        self::$global_error_handler = $error_handler;
    }

    /**
     * @param \Httpful\Handlers\MimeHandlerInterface $global_mime_handler
     */
    public static function registerGlobalMimeHandler(MimeHandlerInterface $global_mime_handler)
    {
        self::$global_mime_handler = $global_mime_handler;
    }

    /**
     * @param string               $mimeType
     * @param MimeHandlerInterface $handler
     */
    public static function registerMimeHandler($mimeType, MimeHandlerInterface $handler)
    {
        self::$mime_registrar[$mimeType] = $handler;
    }

    /**
     * @return MimeHandlerInterface
     */
    public static function reset(): MimeHandlerInterface
    {
        self::$mime_registrar = [];
        self::$mime_registered = false;
        self::$global_error_handler = null;
        self::$global_mime_handler = null;

        self::initMimeHandlers();

        return self::setupGlobalMimeType();
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

        if (empty(self::$global_mime_handler)) {
            self::$global_mime_handler = new DefaultMimeHandler();
        }

        return self::$global_mime_handler;
    }
}
