<?php
/**
 * @author nick fox <quixand gmail com>
 */

namespace Httpful\Test;

use Httpful\Request;
use PHPUnit\Framework\TestCase;

/**
 * Class RequestTest
 *
 * @package Httpful\Test
 */
class RequestTest extends TestCase
{

  /**
   * @author                   Nick Fox
   * @expectedException        \Httpful\Exception\ConnectionErrorException
   * @expectedExceptionMessage Unable to connect
   */
  public function testGet_InvalidURL()
  {
    // Silence the default logger via whenError override
    Request::get('unavailable.url')->whenError(
        function ($error) {
        }
    )->send();
  }

}
