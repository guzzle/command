<?php
namespace GuzzleHttp\Tests\Command;

use GuzzleHttp\Command\Command;
use GuzzleHttp\Event\Emitter;

/**
 * @covers \GuzzleHttp\Command\Command
 */
class CommandTest extends \PHPUnit_Framework_TestCase
{
    public function testHasData()
    {
        $c = new Command('foo', ['baz' => 'bar']);
        $this->assertSame('bar', $c['baz']);
        $this->assertTrue($c->hasParam('baz'));
        $this->assertFalse($c->hasParam('boo'));
        $this->assertEquals('foo', $c->getName());
        $config = $c->getConfig();
        $this->assertInstanceOf('GuzzleHttp\\Collection', $config);
        $this->assertSame($config, $c->getConfig());
        $this->assertEquals(['baz' => 'bar'], $c->toArray());
    }

    public function testCanUseCustomEmitter()
    {
        $emitter = new Emitter();
        $c = new Command('foo', [], $emitter);
        $this->assertSame($emitter, $c->getEmitter());
    }

    public function testCloneUsesDifferentEmitter()
    {
        $command = new Command('foo');
        $e1 = $command->getEmitter();
        $command2 = clone $command;
        $this->assertNotSame($e1, $command2->getEmitter());
    }
}
