<?php
namespace GuzzleHttp\Tests\Command\Guzzle;

use GuzzleHttp\Client;
use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\CommandTransaction;
use GuzzleHttp\Command\Event\ProcessEvent;
use GuzzleHttp\Message\FutureResponse;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Command\Event\PrepareEvent;
use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Ring\Client\MockAdapter;
use GuzzleHttp\Ring\Future;
use GuzzleHttp\Command\FutureModel;
use GuzzleHttp\Event\RequestEvents;
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

    public function testSendsFutureCommandAsynchronously()
    {
        $mockAdapter = new MockAdapter(new Future(function () {
            return ['status' => 200, 'headers' => [], 'body' => 'foo'];
        }));
        $client = new Client(['adapter' => $mockAdapter]);
        $request = $client->createRequest('GET', 'http://www.foo.com');
        $g = $this->getMockBuilder('GuzzleHttp\Command\AbstractClient')
            ->setConstructorArgs([$client])
            ->setMethods(['getCommand'])
            ->getMockForAbstractClass();

        $em = $g->getEmitter();
        $em->on('prepare', function (PrepareEvent $e) use ($request) {
            $e->setRequest($request);
        });

        $called = 0;
        $em->on('process', function (ProcessEvent $e) use (&$called) {
            $called++;
            $this->assertInstanceOf('GuzzleHttp\Message\Response', $e->getResponse());
            $e->setResult(['foo' => 'bar']);
        }, RequestEvents::EARLY);

        $cmd = new Command('fooCommand', [], ['emitter' => $em, 'future' => true]);
        $g->expects($this->once())
            ->method('getCommand')
            ->will($this->returnValue($cmd));

        $command = $g->getCommand('foo');
        $result = $g->execute($command);
        $this->assertInstanceOf('GuzzleHttp\Command\FutureModel', $result);
        $this->assertEquals(0, $called);
        $this->assertEquals(['foo' => 'bar'], $result->toArray());
        $this->assertEquals(1, $called);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Must be a FutureInterface. Found string
     */
    public function testEnsuresFutureResultIsFromFuture()
    {
        $client = new Client();
        $g = $this->getMockBuilder('GuzzleHttp\Command\AbstractClient')
            ->setConstructorArgs([$client])
            ->getMockForAbstractClass();
        $ref = new \ReflectionMethod($g, 'createFutureResult');
        $ref->setAccessible(true);
        $trans = new CommandTransaction($g, new Command('foo'));
        $trans->response = 'baz';
        $ref->invoke($g, $trans);
    }

    public function testProxiesCancelCall()
    {
        $client = new Client();
        $g = $this->getMockBuilder('GuzzleHttp\Command\AbstractClient')
            ->setConstructorArgs([$client])
            ->getMockForAbstractClass();
        $c = false;
        $response = new Response(200);
        $future = new FutureResponse(function () use ($response) {
            return $response;
        }, function () use (&$c) {
            return $c = true;
        });
        $ref = new \ReflectionMethod($g, 'createFutureResult');
        $ref->setAccessible(true);
        $trans = new CommandTransaction($g, new Command('foo'));
        $trans->response = $future;
        $m = $ref->invoke($g, $trans);
        $this->assertInstanceOf('GuzzleHttp\Command\FutureModel', $m);
        $this->assertFalse($future->realized());
        $this->assertTrue($m->cancel());
        $this->assertTrue($c);
    }

    public function testExecuteAllSendsInPool()
    {
        $client = new Client();
        $client->getEmitter()->attach(new Mock([new Response(200)]));
        $g = $this->getMockBuilder('GuzzleHttp\Command\AbstractClient')
            ->setConstructorArgs([$client])
            ->getMockForAbstractClass();
        $command = new Command('foo');
        $command->getEmitter()->on('prepare', function ($e) use (&$c, $client) {
            $c[] = 'prepare';
            $e->setRequest($client->createRequest('GET', 'http://foo.com'));
        });
        $command->getEmitter()->on('process', function ($e) use (&$c, $client) {
            $c[] = 'process';
        });
        $commands = [$command];
        $g->executeAll($commands);
        $this->assertEquals(['prepare', 'process'], $c);
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
