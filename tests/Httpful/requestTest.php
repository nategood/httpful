<?php
/**
 * @author nick fox <quixand gmail com>
 */
namespace Httpful\Test;

class requestTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @author Nick Fox
     * @expectedException        Httpful\Exception\ConnectionErrorException
     * @expectedExceptionMessage Unable to connect
     */
    public function testGet_InvalidURL()
    {
        // Silence the default logger via whenError override
        \Httpful\Request::get('unavailable.url')->whenError(function($error) {})->send();
    }

}
