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
    private static $mimeRegistrar = [];

    /**
     * @var bool
     */
    private static $mimeRegistered = false;

    /**
     * @var MimeHandlerInterface|null
     */
    private static $mimeDefault;

    /**
     * @var callable|LoggerInterface|null
     */
    private static $errorGlobalCallback;

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
        self::$mimeRegistered = false;
        self::$errorGlobalCallback = null;
        self::$mimeDefault = null;

        self::initMimeHandlers();

        self::setupGlobalMimeType();
    }

    /**
     * Register default mime handlers.
     */
    public static function initMimeHandlers()
    {
        if (self::$mimeRegistered === true) {
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

        self::$mimeRegistered = true;
    }

    /**
     * @param string               $mimeType
     * @param MimeHandlerInterface $handler
     */
    public static function register($mimeType, MimeHandlerInterface $handler)
    {
        self::$mimeRegistrar[$mimeType] = $handler;
    }

    /**
     * @param callable|LoggerInterface $error_callback
     */
    public static function setupGlobalErrorCallback($error_callback)
    {
        if (
            !$error_callback instanceof LoggerInterface
            &&
            !\is_callable($error_callback)
        ) {
            throw new \InvalidArgumentException('Only callable or LoggerInterface are allowed as global error callback.');
        }

        self::$errorGlobalCallback = $error_callback;
    }

    /**
     * @return callable|\Psr\Log\LoggerInterface|null
     */
    public static function getGlobalErrorCallback()
    {
        return self::$errorGlobalCallback;
    }

    /**
     * @param string $mimeType
     *
     * @return MimeHandlerInterface
     */
    public static function setupGlobalMimeType($mimeType = null): MimeHandlerInterface
    {
        self::initMimeHandlers();

        if (isset(self::$mimeRegistrar[$mimeType])) {
            return self::$mimeRegistrar[$mimeType];
        }

        if (empty(self::$mimeDefault)) {
            self::$mimeDefault = new DefaultHandler();
        }

        return self::$mimeDefault;
    }
}
