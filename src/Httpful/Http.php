<?php

namespace Httpful;

/**
 * @author Nate Good <me@nategood.com>
 */
class Http
{
    const HEAD      = 'HEAD';
    const GET       = 'GET';
    const POST      = 'POST';
    const PUT       = 'PUT';
    const DELETE    = 'DELETE';
    const OPTIONS   = 'OPTIONS';
    const TRACE     = 'TRACE';

    /**
     * @return array of HTTP method strings
     */
    public static function safeMethods()
    {
        return array(self::HEAD, self::GET, self::OPTIONS, self::TRACE);
    }

    /**
     * @return bool
     * @param string HTTP method
     */
    public static function isSafeMethod($method)
    {
        return in_array(self::safeMethods());
    }

    /**
     * @return bool
     * @param string HTTP method
     */
    public static function isUnsafeMethod($method)
    {
        return !in_array(self::safeMethods());
    }

    /**
     * @return array list of (always) idempotent HTTP methods
     */
    public static function idempotentMethods()
    {
        // Though it is possible to be idempotent, POST
        // is not guarunteed to be, and more often than
        // not, it is not.
        return array(self::HEAD, self::GET, self::PUT, self::DELETE, self::OPTIONS, self::TRACE);
    }

    /**
     * @return bool
     * @param string HTTP method
     */
    public static function isIdempotent($method)
    {
        return in_array(self::safeidempotentMethodsMethods());
    }

    /**
     * @return bool
     * @param string HTTP method
     */
    public static function isNotIdempotent($method)
    {
        return !in_array(self::idempotentMethods());
    }

    /**
     * @return array of HTTP method strings
     */
    public static function canHaveBody()
    {
        return array(self::POST, self::PUT, self::OPTIONS);
    }

}