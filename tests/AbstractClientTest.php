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
        $this->assertEquals([
            'foo'      => 'bar',
            'baz'      => ['bam' => 'boo'],
            'defaults' => [],
            'abc'      => ['123' => 'listen']
        ], $sc->getConfig());
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

    public function testDoesNotWrapExceptionsMoreThanOnce()
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
            $this->assertEquals(2, $this->getWrapCount($e));
        }
    }

    public function testDoesNotWrapNonCommandExceptions()
    {
        $client = new Client();
        $mock = $this->getMockBuilder('GuzzleHttp\\Command\\AbstractClient')
            ->setConstructorArgs([$client, []])
            ->getMockForAbstractClass();
        $command = new Command('foo');
        $emitter = $command->getEmitter();
        $e1 = new \Exception('foo');
        $emitter->on('prepare', function() use ($e1) { throw $e1; });

        try {
            $mock->execute($command);
            $this->fail('Did not throw');
        } catch (\Exception $e) {
            $this->assertSame($e, $e1);
            $this->assertEquals(1, $this->getWrapCount($e));
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

    public function testExecutesCommandsInBatch()
    {
        $client = new Client();
        $mock = $this->getMockBuilder('GuzzleHttp\\Command\\AbstractClient')
            ->setConstructorArgs([$client, []])
            ->getMockForAbstractClass();
        $request = $client->createRequest('GET', 'http://httbin.org');

        $command1 = new Command('foo');
        $command1->getEmitter()->on(
            'prepare',
            function (PrepareEvent $e) use ($request) {
                $e->setResult('foo');
            }
        );

        $command2 = new Command('foo');
        $command2->getEmitter()->on(
            'prepare',
            function (PrepareEvent $e) use ($request) {
                $e->setResult('bar');
            }
        );

        $command3 = new Command('foo');
        $command3->getEmitter()->on('prepare', function () {
            throw new \Exception('foo');
        });

        $hash = $mock->batch([$command1, $command2, $command3]);
        $this->assertCount(3, $hash);
        $this->assertEquals('foo', $hash[$command1]);
        $this->assertEquals('bar', $hash[$command2]);
        $this->assertEquals('foo', $hash[$command3]->getPrevious()->getMessage());
    }

    public function testCanInjectEmitter()
    {
        $guzzleClient = $this->getMock('GuzzleHttp\\ClientInterface');
        $emitter = $this->getMockBuilder('GuzzleHttp\Event\EmitterInterface')
            ->setMethods(['listeners'])
            ->getMockForAbstractClass();
        $serviceClient = $this->getMockBuilder('GuzzleHttp\\Command\\AbstractClient')
            ->setConstructorArgs([$guzzleClient, ['emitter' => $emitter]])
            ->getMockForAbstractClass();

        $emitter->expects($this->once())
            ->method('listeners')
            ->will($this->returnValue('foo'));

        $this->assertEquals('foo', $serviceClient->getEmitter()->listeners());
    }

    private function getWrapCount(\Exception $e)
    {
        $c = 0;
        while ($e) {
            $e = $e->getPrevious();
            $c++;
        }

        return $c;
    }
}
