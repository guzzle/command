<?php
namespace GuzzleHttp\Tests\Command\Guzzle;

use GuzzleHttp\Client;
use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\CommandTransaction;
use GuzzleHttp\Command\Event\ProcessEvent;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Message\FutureResponse;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Command\Event\PreparedEvent;
use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Ring\Client\MockHandler;
use GuzzleHttp\Event\RequestEvents;
use GuzzleHttp\Ring\Future\FutureArray;
use GuzzleHttp\Subscriber\Mock;
use React\Promise\Deferred;

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
        $this->assertEquals([
            'foo'      => 'bar',
            'baz'      => ['bam' => 'boo'],
            'defaults' => []
        ], $sc->getConfig());
    }

    public function testMagicMethodExecutesCommands()
    {
        $client = new Client();
        $mock = $this->getMockBuilder('GuzzleHttp\\Command\\AbstractClient')
            ->setConstructorArgs([$client, []])
            ->setMethods(['getCommand', 'execute'])
            ->getMockForAbstractClass();

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
            ->getMockForAbstractClass();

        $mock->expects($this->once())
            ->method('getCommand')
            ->with('foo')
            ->will($this->returnValue(new Command('foo')));

        $mock->expects($this->once())
            ->method('execute')
            ->will($this->returnValue('foo'));

        $this->assertEquals('foo', $mock->foo());
    }

    public function testWrapsNonCommandExceptionsByDefault()
    {
        $client = new Client();
        $mock = $this->getMockBuilder('GuzzleHttp\\Command\\AbstractClient')
            ->setConstructorArgs([$client, []])
            ->getMockForAbstractClass();
        $command = new Command('foo');
        $emitter = $command->getEmitter();
        $e1 = new \Exception('foo');
        $emitter->on('prepared', function() use ($e1) { throw $e1; });

        try {
            $mock->execute($command);
            $this->fail('Did not throw');
        } catch (\Exception $e) {
            $this->assertNotSame($e, $e1);
        }
    }

    public function testReturnsInterceptedResult()
    {
        $client = new Client();
        $mock = $this->getMockBuilder('GuzzleHttp\\Command\\AbstractClient')
            ->setConstructorArgs([$client, []])
            ->setMethods(['serializeRequest'])
            ->getMockForAbstractClass();
        $mock->expects($this->once())
            ->method('serializeRequest')
            ->will($this->returnValue(new Request('GET', 'http://foo.com')));
        $command = new Command('foo');
        $command->getEmitter()->on('prepared', function(PreparedEvent $event) {
            $event->intercept('test');
        });
        $called = [];
        $command->getEmitter()->on('process', function(ProcessEvent $event) use (&$called) {
            $called[] = 'process';
        });
        $this->assertEquals('test', $mock->execute($command));
        $this->assertEquals(['process'], $called);
    }

    public function testEmitsProcessWhenCommandFailsInPrepare()
    {
        $client = new Client();
        $mock = $this->getMockBuilder('GuzzleHttp\\Command\\AbstractClient')
            ->setConstructorArgs([$client, []])
            ->setMethods(['serializeRequest'])
            ->getMockForAbstractClass();
        $mock->expects($this->once())
            ->method('serializeRequest')
            ->will($this->returnValue(new Request('GET', 'http://foo.com')));
        $command = new Command('foo');
        $called = [];
        $command->getEmitter()->on('prepared', function() use (&$called) {
            $called[] = 'prepared';
            throw new \Exception('test');
        });
        $command->getEmitter()->on('process', function(ProcessEvent $event) use (&$called) {
            $this->assertNotNull($event->getException());
            $called[] = 'process';
            $event->setResult(['foo' => 'bar']);
            $this->assertNull($event->getException());
        });
        $this->assertEquals(['foo' => 'bar'], $mock->execute($command));
        $this->assertEquals(['prepared', 'process'], $called);
    }

    public function testReturnsProcessedResponse()
    {
        $client = new Client();
        $client->getEmitter()->on('before', function (BeforeEvent $event) {
            $event->intercept(new Response(201));
        });
        $mock = $this->getMockBuilder('GuzzleHttp\\Command\\AbstractClient')
            ->setConstructorArgs([$client, []])
            ->setMethods(['serializeRequest'])
            ->getMockForAbstractClass();
        $req = $client->createRequest('GET', 'http://test.com');
        $mock->expects($this->once())
            ->method('serializeRequest')
            ->will($this->returnValue($req));
        $command = new Command('foo');
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
        $deferred = new Deferred();
        $future = new FutureArray(
            $deferred->promise(),
            function () use ($deferred) {
                $deferred->resolve(['status' => 200, 'headers' => [], 'body' => 'foo']);
            }
        );
        $mockAdapter = new MockHandler($future);
        $client = new Client(['handler' => $mockAdapter]);
        $request = $client->createRequest('GET', 'http://www.foo.com');
        $g = $this->getMockBuilder('GuzzleHttp\Command\AbstractClient')
            ->setConstructorArgs([$client])
            ->setMethods(['getCommand', 'serializeRequest'])
            ->getMockForAbstractClass();
        $g->expects($this->once())
            ->method('serializeRequest')
            ->will($this->returnValue($request));

        $called = 0;
        $em = $g->getEmitter();
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
        $calledValue = null;
        $result->then(function ($result) use (&$calledValue) {
            $calledValue = $result;
            return $result;
        });
        $this->assertNull($calledValue);
        $this->assertInstanceOf('GuzzleHttp\Ring\Future\FutureValue', $result);
        $this->assertEquals(0, $called);
        $this->assertEquals(['foo' => 'bar'], $result->wait());
        $this->assertEquals(1, $called);
        $this->assertEquals(['foo' => 'bar'], $calledValue);
    }

    public function testProxiesCancelCall()
    {
        $client = new Client();
        $g = $this->getMockBuilder('GuzzleHttp\Command\AbstractClient')
            ->setConstructorArgs([$client])
            ->getMockForAbstractClass();
        $c = false;
        $response = new Response(200);
        $deferred = new Deferred();
        $future = new FutureResponse(
            $deferred->promise(),
            function () use ($response, $deferred) {
                $deferred->resolve($response);
            }, function () use (&$c) {
                return $c = true;
            }
        );
        $ref = new \ReflectionMethod($g, 'createFutureResult');
        $ref->setAccessible(true);
        $trans = new CommandTransaction($g, new Command('foo'));
        $trans->response = $future;
        $m = $ref->invoke($g, $trans);
        $this->assertInstanceOf('GuzzleHttp\Ring\Future\FutureValue', $m);
        $this->assertFalse($this->readAttribute($future, 'isRealized'));
        $m->cancel();
        $this->assertTrue($c);
    }

    public function testExecuteAllSendsInPool()
    {
        $client = new Client();
        $client->getEmitter()->attach(new Mock([new Response(200)]));
        $g = $this->getMockBuilder('GuzzleHttp\Command\AbstractClient')
            ->setConstructorArgs([$client])
            ->setMethods(['serializeRequest'])
            ->getMockForAbstractClass();
        $g->expects($this->once())
            ->method('serializeRequest')
            ->will($this->returnValue($client->createRequest('GET', 'http://foo.com')));
        $command = new Command('foo');
        $command->getEmitter()->on('init', function () use (&$c) {
            $c[] = 'init';
        });
        $command->getEmitter()->on('prepared', function () use (&$c) {
            $c[] = 'prepared';
        });
        $command->getEmitter()->on('process', function () use (&$c) {
            $c[] = 'process';
        });
        $commands = [$command];
        $g->executeAll($commands);
        $this->assertEquals(['init', 'prepared', 'process'], $c);
    }

    public function testExecuteAllCatchesExceptions()
    {
        $client = new Client();
        $client->getEmitter()->attach(new Mock([new Response(404)]));
        $g = $this->getMockBuilder('GuzzleHttp\Command\AbstractClient')
            ->setConstructorArgs([$client])
            ->setMethods(['serializeRequest'])
            ->getMockForAbstractClass();
        $g->expects($this->once())
            ->method('serializeRequest')
            ->will($this->returnValue($client->createRequest('GET', 'http://foo.com')));
        $command = new Command('foo');
        $e = null;
        $command->getEmitter()->on('process', function (ProcessEvent $ev) use (&$e) {
            $e = $ev->getException();
        });
        $commands = [$command];
        $g->executeAll($commands);
        $this->assertInstanceOf('GuzzleHttp\Exception\RequestException', $e);
        $this->assertInstanceOf('GuzzleHttp\Command\Exception\CommandException', $e);
    }

    public function testDoesNotWrapExistingCommandExceptions()
    {
        $http = new Client();
        $client = $this->getMockBuilder('GuzzleHttp\Command\AbstractClient')
            ->setConstructorArgs([$http])
            ->getMockForAbstractClass();
        $command = new Command('foo');
        $trans = new CommandTransaction($client, $command);
        $ex = new CommandException('foo', $trans);
        $trans->exception = $ex;
        $this->assertSame($ex, $client->createCommandException($trans));
    }

    public function testThrowsTransactionExceptionAfterProcess()
    {
        $client = new Client();
        $client->getEmitter()->attach(new Mock([new Response(200)]));
        $g = $this->getMockBuilder('GuzzleHttp\Command\AbstractClient')
            ->setConstructorArgs([$client])
            ->setMethods(['serializeRequest'])
            ->getMockForAbstractClass();
        $g->expects($this->once())
            ->method('serializeRequest')
            ->will($this->returnValue($client->createRequest('GET', 'http://foo.com')));
        $ex = new \Exception('foo');
        $command = new Command('foo');
        $command->getEmitter()->on('process', function () use ($ex) {
            throw $ex;
        });

        try {
            $g->execute($command);
            $this->fail('did not throw');
        } catch (\Exception $e) {
            $this->assertInstanceOf('GuzzleHttp\Command\Exception\CommandException', $e);
        }
    }

    public function testThrowingInRequestLayerDoesNotAffectInterceptedCommand()
    {
        $client = new Client();
        $client->getEmitter()->attach(new Mock([new Response(404)]));
        $g = $this->getMockBuilder('GuzzleHttp\Command\AbstractClient')
            ->setConstructorArgs([$client])
            ->setMethods(['serializeRequest'])
            ->getMockForAbstractClass();
        $g->expects($this->once())
            ->method('serializeRequest')
            ->will($this->returnValue($client->createRequest('GET', 'http://foo.com')));
        $command = new Command('foo');
        $e = null;
        $command->getEmitter()->on('process', function (ProcessEvent $ev) use (&$e) {
            $e = $ev->getException();
            $ev->setResult('foo!');
        });
        $this->assertSame('foo!', $g->execute($command));
        $this->assertInstanceOf('GuzzleHttp\Exception\RequestException', $e);
        $this->assertInstanceOf('GuzzleHttp\Command\Exception\CommandException', $e);
    }
}
