<?php
namespace GuzzleHttp\Tests\Command;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Command\Command;
use GuzzleHttp\HandlerStack;

/**
 * @covers \GuzzleHttp\Command\Command
 */
class CommandTest extends TestCase
{
    public function testHasData()
    {
        $c = new Command('foo', ['baz' => 'bar']);
        $this->assertSame('bar', $c['baz']);
        $this->assertTrue($c->hasParam('baz'));
        $this->assertFalse($c->hasParam('boo'));
        $this->assertSame(['baz' => 'bar'], $c->toArray());
        $this->assertEquals('foo', $c->getName());
        $this->assertCount(1, $c);
        $this->assertInstanceOf('Traversable', $c->getIterator());
    }

    public function testCanInjectHandlerStack()
    {
        $handlerStack = new HandlerStack();
        $c = new Command('foo', [], $handlerStack);
        $this->assertSame($handlerStack, $c->getHandlerStack());
    }

    public function testCloneUsesDifferentHandlerStack()
    {
        $originalStack = new HandlerStack();
        $command = new Command('foo', [], $originalStack);
        $this->assertSame($originalStack, $command->getHandlerStack());
        $command2 = clone $command;
        $this->assertNotSame($originalStack, $command2->getHandlerStack());
    }
}
