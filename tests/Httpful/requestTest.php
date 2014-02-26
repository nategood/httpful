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
        \Httpful\Request::get('unavailable.url')->send();
    }

}
