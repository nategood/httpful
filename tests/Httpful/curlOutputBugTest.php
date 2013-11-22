<?php
/**
 * Port over the original tests into a more traditional PHPUnit
 * format.  Still need to hook into a lightweight HTTP server to
 * better test some things (e.g. obscure cURL settings).  I've moved
 * the old tests and node.js server to the tests/.legacy directory.
 * @author nick fox <quixand gmail com>
 */
namespace Httpful\Test;

class curlOutputBugTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @author Nick Fox
     * @expectedException        Httpful\Exception\ConnectionErrorException
     * @expectedExceptionMessage Unable to connect
     */
    public function testInvalidURLGeneratesUnexpectedOutput()
    {
        \Httpful\Request::get('unavailable.url')->send();
    }

    /**
     * @author Nick Fox
     */
    public function testInvalidURLGeneratesUnexpectedOutput_catchException()
    {
        try {
            $output = \Httpful\Request::get('unavailable.url')->send();
        } catch (\Httpful\Exception\ConnectionErrorException $expected) {
            $this->assertEquals('Unable to connect: 6 Couldn\'t resolve host \'unavailable.url\'', $expected->getMessage());
            return;
        }
        $this->fail('An expected exception has not been raised.');
    }
}
