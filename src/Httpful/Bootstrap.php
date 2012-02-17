<?php

namespace Httpful;

/**
 * Bootstrap class that facilitates autoloading.  A naive
 * PSR-0 autoloader.
 *
 * @author Nate Good <me@nategood.com>
 */
class Bootstrap
{
    /**
     * By his bootstraps...
     */
    public static function init()
    {
        spl_autoload_register(array('\Httpful\Bootstrap', 'autoload'));
    }
    
    /**
     * The autoload magic (PSR-0 style)
     * @param string
     */
    public static function autoload($classname) 
    {
        $dir_glue   = '/';
        $ns_glue    = '\\';
        
        $base       = dirname(dirname(__FILE__));
        $parts      = explode($ns_glue, $classname);
        $path       = $base . $dir_glue . implode($dir_glue, $parts) . '.php';
        
        require_once($path);
    }
    
    /**
     * Compile the library into a single file
     */
    public static function compile() {
        // @todo 
    }
}