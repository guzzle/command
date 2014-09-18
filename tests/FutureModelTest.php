<?php
namespace GuzzleHttp\Tests\Command;

use GuzzleHttp\Command\FutureModel;

/**
 * @covers \GuzzleHttp\Command\FutureModel
 */
class FutureModelTest extends \PHPUnit_Framework_TestCase
{
    public function testHasData()
    {
        $called = false;
        $c = new FutureModel(function () use (&$called) {
            $called = true;
            return ['a' => 1];
        });
        $this->assertFalse($called);
        $this->assertEquals(['a' => 1], $c->deref());
        $this->assertTrue($called);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testValidatesResult()
    {
        $c = new FutureModel(function () use (&$called) {
            return true;
        });
        $c->deref();
    }

    public function testProxiesToUnderlyingData()
    {
        $c = new FutureModel(function () {
            return ['a' => 1];
        });
        $this->assertEquals(['a' => 1], $c->toArray());
        $this->assertEquals(['a' => 1], $c->getIterator()->getArrayCopy());
        $this->assertEquals(1, $c['a']);
        $this->assertEquals(1, $c->get('a'));
        $this->assertNull($c['b']);
        $this->assertTrue(isset($c['a']));
        $c['b'] = 2;
        $this->assertTrue(isset($c['b']));
        unset($c['b']);
        $this->assertFalse(isset($c['b']));
        $this->assertEquals(1, $c->getPath('a'));
        $c->setPath('foo/bar', 'baz');
        $this->assertEquals('baz', $c['foo']['bar']);
        $this->assertTrue($c->hasKey('a'));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testThrowsWhenPropertyInvalid()
    {
        $c = new FutureModel(function () { return ['a' => 1]; });
        $c->notThere;
    }

    /**
     * @expectedException \GuzzleHttp\Ring\Exception\CancelledFutureAccessException
     */
    public function testThrowsWhenAccessingCancelledFuture()
    {
        $c = new FutureModel(function () {});
        $c->cancel();
        $c['foo'];
    }
}
