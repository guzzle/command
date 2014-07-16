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

    public function testCanCreateRequestsFromCommand()
    {
        $client = $this->getMockBuilder('GuzzleHttp\Command\AbstractClient')
            ->setConstructorArgs([new Client()])
            ->getMockForAbstractClass();
        $request = new Request('GET', 'http://httpbin.org/get');
        $command = new Command('foo');
        $command->getEmitter()->on(
            'prepare',
            function(PrepareEvent $event) use ($request) {
                $event->setRequest($request);
            }
        );
        $this->assertSame($request,  Command::createRequest($client, $command));
    }
}
