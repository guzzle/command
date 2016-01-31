<?php
namespace GuzzleHttp\Tests\Command;

use GuzzleHttp\Command\Result;

/**
 * @covers \GuzzleHttp\Command\Result
 * @covers \GuzzleHttp\Command\HasDataTrait
 */
class ResultTest extends \PHPUnit_Framework_TestCase
{
    public function testHasData()
    {
        $c = new Result(['baz' => 'bar']);
        $this->assertSame('bar', $c['baz']);
        $this->assertSame(['baz' => 'bar'], $c->toArray());
        $this->assertTrue(isset($c['baz']));
        $c['fizz'] = 'buzz';
        $this->assertCount(2, $c);
        unset($c['fizz']);
        $this->assertCount(1, $c);
        $this->assertInstanceOf('Traversable', $c->getIterator());
        $this->assertContains('bar', (string) $c);
    }
}
