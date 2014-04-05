<?php
namespace GuzzleHttp\Tests\Command\Guzzle;

use GuzzleHttp\Client;
use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\Event\ProcessEvent;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Command\Event\PrepareEvent;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Subscriber\Mock;

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

    public function testWrapsExceptionsInCommandExceptions()
    {
        $client = new Client();
        $client->getEmitter()->attach(new Mock([new Response(404)]));

        $mock = $this->getMockBuilder('GuzzleHttp\\Command\\AbstractClient')
            ->setConstructorArgs([$client, []])
            ->getMockForAbstractClass();

        $command = new Command('foo');
        $emitter = $command->getEmitter();

        $emitter->on('prepare', function(PrepareEvent $event) {
            $event->setRequest($event->getClient()
                ->getHttpClient()
                ->createRequest('PUT', 'http://httpbin.org/get'));
        });

        $cp = $ce = false;
        $emitter->on('process', function() use (&$cp) { $cp = true; });
        $emitter->on('error', function() use (&$ce) { $ce = true; });

        try {
            $mock->execute($command);
            $this->fail('Did not throw');
        } catch (CommandException $e) {
            $this->assertContains('Error executing command:', (string) $e);
            $this->assertFalse($cp, 'The process event was called');
            $this->assertTrue($ce, 'The error event was not called');
            // Ensure that there isn't a bunch of competing exception stacking
            // where the command exception wraps the requests exception > once.
            $c = 0;
            while ($e) {
                $e = $e->getPrevious();
                $c++;
            }
            $this->assertEquals(2, $c);
        }
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
        $command->getEmitter()->on(
            'prepare',
            function (PrepareEvent $e) use ($request) {
                $e->setRequest($request);
            }
        );

        $client->expects($this->once())
            ->method('sendAll')
            ->will($this->returnCallback(
                function ($requests, $options) use ($request) {
                    $this->assertEquals(10, $options['parallel']);
                    $this->assertTrue($requests->valid());
                    $this->assertSame($request, $requests->current());
                }
            ));

        $mock->executeAll([$command], ['parallel' => 10]);
    }
}
