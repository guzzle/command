<?php
namespace GuzzleHttp\Tests\Command;

use GuzzleHttp\Command\Model;

/**
 * @covers \GuzzleHttp\Command\Model
 */
class ModelTest extends \PHPUnit_Framework_TestCase
{
    public function testHasData()
    {
        $c = new Model(['a' => 'b', 'c' => 'd']);
        $this->assertEquals('b', $c['a']);
        $this->assertEquals('d', $c['c']);
        $this->assertTrue($c->hasKey('c'));
        $this->assertFalse($c->hasKey('f'));
    }
}
