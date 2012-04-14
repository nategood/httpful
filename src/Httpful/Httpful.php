<?php

namespace \Httpful;

class Httpful {
    private static $mimeRegistrar = array();
    private static $default = null;
    
    /**
     * @param string $mime_type
     * @param AbstractHandler $handler
     */
    public static function register($mime_type, AbstractHandler $handler)
    {
        self::$registrar[$mime_type] = $handler;
    }
    
    /**
     * @param string $mime_type
     * @return AbstractHandler
     */
    public static function get($mime_type)
    {
        if (isset(self::$registrar[$mime_type])) {
            return self::$registrar[$mime_type];
        }

        if (empty(self::$default)) {
            self::$default = new AbstractHandler();
        }

        return self::$default;
    }
}