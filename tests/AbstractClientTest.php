<?php

namespace GuzzleHttp\Tests\Command\Guzzle;

use GuzzleHttp\Client;
use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\Event\ProcessEvent;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Command\Event\PrepareEvent;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Event\BeforeEvent;

/**
 * @covers \GuzzleHttp\Command\AbstractClient
 */
class AbstractClientTest extends \PHPUnit_Framework_TestCase
{
    public function testHasConfig()
    {
        $client = new Client();
        $config = [
            'foo' => 'bar',
            'baz' => ['bam' => 'boo']
        ];
        $sc = $this->getMockBuilder('GuzzleHttp\\Command\\AbstractClient')
            ->setConstructorArgs([$client, $config])
            ->getMockForAbstractClass();
        $this->assertSame($client, $sc->getHttpClient());
        $this->assertEquals('bar', $sc->getConfig('foo'));
        $this->assertEquals('boo', $sc->getConfig('baz/bam'));
        $this->assertEquals([], $sc->getConfig('defaults'));
        $sc->setConfig('abc/123', 'listen');
        $this->assertEquals('listen', $sc->getConfig('abc/123'));
    }

    public function testMagicMethodExecutesCommands()
    {
        $client = new Client();
        $mock = $this->getMockBuilder('GuzzleHttp\\Command\\AbstractClient')
            ->setConstructorArgs([$client, []])
            ->setMethods(['getCommand', 'execute'])
            ->getMock();

        $mock->expects($this->once())
            ->method('getCommand')
            ->with('foo', [])
            ->will($this->returnValue(new Command('foo')));

        $mock->expects($this->once())
            ->method('execute')
            ->will($this->returnValue('foo'));

        $this->assertEquals('foo', $mock->foo([]));
    }

    public function testMagicMethodExecutesCommandsWithNoArguments()
    {
        $client = new Client();
        $mock = $this->getMockBuilder('GuzzleHttp\\Command\\AbstractClient')
            ->setConstructorArgs([$client, []])
            ->setMethods(['getCommand', 'execute'])
            ->getMock();

        $mock->expects($this->once())
            ->method('getCommand')
            ->with('foo')
            ->will($this->returnValue(new Command('foo')));

        $mock->expects($this->once())
            ->method('execute')
            ->will($this->returnValue('foo'));

        $this->assertEquals('foo', $mock->foo());
    }

    /**
     * @expectedException \GuzzleHttp\Command\Exception\CommandException
     */
    public function testPassesCommandExceptionsThrough()
    {
        $client = new Client();
        $mock = $this->getMockBuilder('GuzzleHttp\\Command\\AbstractClient')
            ->setConstructorArgs([$client, []])
            ->setMethods(['getCommand'])
            ->getMock();
        $command = new Command('foo');
        $command->getEmitter()->on('prepare', function(PrepareEvent $event) {
            throw new CommandException(
                'foo',
                $event->getClient(),
                $event->getCommand(),
                $event->getRequest()
            );
        }, 1);
        $mock->execute($command);
    }

    /**
     * @expectedException \GuzzleHttp\Command\Exception\CommandException
     * @expectedExceptionMessage Error executing command: msg
     */
    public function testWrapsExceptionsInCommandExceptions()
    {
        $client = new Client();
        $mock = $this->getMockBuilder('GuzzleHttp\\Command\\AbstractClient')
            ->setConstructorArgs([$client, []])
            ->getMockForAbstractClass();
        $command = new Command('foo');
        $command->getEmitter()->on('prepare', function(PrepareEvent $event) {
            throw new \Exception('msg');
        });
        $mock->execute($command);
    }

    public function testReturnsInterceptedResult()
    {
        $client = new Client();
        $mock = $this->getMockBuilder('GuzzleHttp\\Command\\AbstractClient')
            ->setConstructorArgs([$client, []])
            ->getMockForAbstractClass();
        $command = new Command('foo');
        $command->getEmitter()->on('prepare', function(PrepareEvent $event) {
            $event->setResult('test');
        });
        $this->assertEquals('test', $mock->execute($command));
    }

    public function testReturnsProcessedResponse()
    {
        $client = new Client();
        $client->getEmitter()->on('before', function (BeforeEvent $event) {
            $event->intercept(new Response(201));
        });
        $mock = $this->getMockBuilder('GuzzleHttp\\Command\\AbstractClient')
            ->setConstructorArgs([$client, []])
            ->getMockForAbstractClass();
        $command = new Command('foo');
        $command->getEmitter()->on('prepare', function(PrepareEvent $e) {
            $e->setRequest($e->getClient()->getHttpClient()->createRequest(
                'GET', 'http://test.com')
            );
        });
        $command->getEmitter()->on('process', function(ProcessEvent $e) {
            $e->setResult('foo');
        });
        $this->assertEquals('foo', $mock->execute($command));
    }

    public function testExecutesCommandsInParallel()
    {
        $client = $this->getMockBuilder('GuzzleHttp\\Client')
            ->setMethods(['sendAll'])
            ->getMock();
        $mock = $this->getMockBuilder('GuzzleHttp\\Command\\AbstractClient')
            ->setConstructorArgs([$client, []])
            ->getMockForAbstractClass();
        $command = new Command('foo');
        $request = $client->createRequest('GET', 'http://httbin.org');
        $command->getEmitter()->on('prepare', function (PrepareEvent $e) use ($request) {
            $e->setRequest($request);
        });

        $client->expects($this->once())
            ->method('sendAll')
            ->will($this->returnCallback(function ($requests, $options) use ($request) {
                $this->assertEquals(10, $options['parallel']);
                $this->assertTrue($requests->valid());
                $this->assertSame($request, $requests->current());
            }));

        $mock->executeAll([$command], ['parallel' => 10]);
    }
}
