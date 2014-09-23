<?php
namespace GuzzleHttp\Tests\Command;

use GuzzleHttp\Client;
use GuzzleHttp\Command\Command;
use GuzzleHttp\Event\Emitter;
use GuzzleHttp\Command\Event\PrepareEvent;
use GuzzleHttp\Message\Request;

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
    }

    public function testCanUseCustomEmitter()
    {
        $emitter = new Emitter();
        $c = new Command('foo', [], ['emitter' => $emitter]);
        $this->assertSame($emitter, $c->getEmitter());
    }

    public function testCanProvideFutureSettingInConstructor()
    {
        $c = new Command('foo', [], ['future' => true]);
        $this->assertTrue($c->getFuture());
    }

    public function testCloneUsesDifferentEmitter()
    {
        $command = new Command('foo');
        $e1 = $command->getEmitter();
        $command2 = clone $command;
        $this->assertNotSame($e1, $command2->getEmitter());
    }

    public function testCanControlFuture()
    {
        $command = new Command('foo');
        $this->assertFalse($command->getFuture());
        $command->setFuture(true);
        $this->assertTrue($command->getFuture());
    }
}
