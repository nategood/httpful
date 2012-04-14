<?php

namespace Httpful;

class Httpful {
    private static $mimeRegistrar = array();
    private static $default = null;
    
    /**
     * @param string $mime_type
     * @param AbstractHandler $handler
     */
    public static function register($mimeType, \Httpful\Handlers\AbstractMimeHandler $handler)
    {
        self::$mimeRegistrar[$mimeType] = $handler;
    }
    
    /**
     * @param string $mime_type
     * @return AbstractHandler
     */
    public static function get($mimeType)
    {
        if (isset(self::$mimeRegistrar[$mimeType])) {
            return self::$mimeRegistrar[$mimeType];
        }

        if (empty(self::$default)) {
            self::$default = new \Httpful\Handlers\AbstractMimeHandler();
        }

        return self::$default;
    }
    
    /**
     * Does this particular Mime Type have a parser registered
     * for it?
     * @return bool
     */
    public static function hasParserRegistered($mimeType)
    {
        return isset(self::$mimeRegistrar[$mimeType]);
    }
}