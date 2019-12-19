<?php

declare(strict_types=1);

namespace Httpful;

use Httpful\Handlers\CsvMimeHandler;
use Httpful\Handlers\DefaultMimeHandler;
use Httpful\Handlers\FormMimeHandler;
use Httpful\Handlers\HtmlMimeHandler;
use Httpful\Handlers\JsonMimeHandler;
use Httpful\Handlers\MimeHandlerInterface;
use Httpful\Handlers\XmlMimeHandler;
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
    private static $global_mime_handler;

    /**
     * @var callable|LoggerInterface|null
     */
    private static $global_error_handler;

    /**
     * @return callable|LoggerInterface|null
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
     *
     * @return void
     */
    public static function initMimeHandlers()
    {
        if (self::$mime_registered === true) {
            return;
        }

        $handlers = [
            Mime::CSV   => new CsvMimeHandler(),
            Mime::FORM  => new FormMimeHandler(),
            Mime::HTML  => new HtmlMimeHandler(),
            Mime::JS    => new DefaultMimeHandler(),
            Mime::JSON  => new JsonMimeHandler(['decode_as_array' => true]),
            Mime::PLAIN => new DefaultMimeHandler(),
            Mime::XHTML => new HtmlMimeHandler(),
            Mime::XML   => new XmlMimeHandler(),
            Mime::YAML  => new DefaultMimeHandler(),
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
     *
     * @return void
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
     * @param MimeHandlerInterface $global_mime_handler
     *
     * @return void
     */
    public static function registerGlobalMimeHandler(MimeHandlerInterface $global_mime_handler)
    {
        self::$global_mime_handler = $global_mime_handler;
    }

    /**
     * @param string               $mimeType
     * @param MimeHandlerInterface $handler
     *
     * @return void
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
