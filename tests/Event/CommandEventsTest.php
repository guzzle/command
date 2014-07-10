<?php
namespace GuzzleHttp\Tests\Command\Event;

use GuzzleHttp\Adapter\Transaction;
use GuzzleHttp\Client;
use GuzzleHttp\Command\Event\CommandErrorEvent;
use GuzzleHttp\Command\Event\CommandEvents;
use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\Event\PrepareEvent;
use GuzzleHttp\Command\Event\ProcessEvent;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Event\RequestEvents;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Subscriber\Mock;

/**
 * @covers \GuzzleHttp\Command\Event\CommandEvents
 */
class CommandEventsTest extends \PHPUnit_Framework_TestCase
{
    public function testEmitsPrepareEvent()
    {
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        $command = new Command('foo', []);
        $request = new Request('GET', 'http://httbin.org');
        $command->getEmitter()->on(
            'prepare',
            function (PrepareEvent $e) use ($request) {
                $e->setRequest($request);
            }
        );
        $event = CommandEvents::prepare($command, $client);
        $this->assertSame($request, $event->getRequest());
        $this->assertFalse($event->isPropagationStopped());
        $this->assertNull($event->getResult());
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage No request was prepared for the command
     */
    public function testEnsuresThePrepareEventIsHandled()
    {
        $command = new Command('foo', []);
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        CommandEvents::prepare($command, $client);
    }

    public function testPrepareEventCanInterceptWithResultBeforeSending()
    {
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        $command = new Command('foo', []);
        // inject a result into the prepare event to skip sending over the wire
        $command->getEmitter()->on('prepare', function (PrepareEvent $e) {
            $e->setResult('123');
        });
        // Ensure that the result was injected and the process event triggered
        $called = false;
        $command->getEmitter()->on(
            'process',
            function (ProcessEvent $e) use (&$called) {
                $called = true;
                $this->assertEquals('123', $e->getResult());
            }
        );
        $event = CommandEvents::prepare($command, $client);
        $this->assertNull($event->getRequest());
        $this->assertTrue($event->isPropagationStopped());
        $this->assertEquals('123', $event->getResult());
        $this->assertTrue($called);
    }

    public function testPassesExceptionsThroughUntouchedInPrepareError()
    {
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        $command = new Command('foo', []);
        $ex = new CommandException('foo', $client, $command);
        $command->getEmitter()->on(
            'prepare',
            function (PrepareEvent $e) use ($ex) {
                throw $ex;
            }
        );
        try {
            CommandEvents::prepare($command, $client);
            $this->fail('Did not throw');
        } catch (CommandException $e) {
            $this->assertSame($ex, $e);
        }
    }

    public function testEmitsProcessEvent()
    {
        $req = new Request('GET', 'http://httbin.org');
        $res = new Response(200);
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        $cmd = new Command('foo', []);
        $c = false;
        $cmd->getEmitter()->on(
            'process',
            function (ProcessEvent $e) use (&$c, $client, $cmd, $req, $res) {
                $e->setResult('foo');
                $this->assertSame($client, $e->getClient());
                $this->assertSame($cmd, $e->getCommand());
                $this->assertSame($req, $e->getRequest());
                $this->assertSame($res, $e->getResponse());
                $c = true;
            }
        );
        $result = CommandEvents::process($cmd, $client, $req, $res);
        $this->assertEquals('foo', $result);
        $this->assertTrue($c);
    }

    public function testEmitsErrorEventAndCanInterceptWithSuccessfulResult()
    {
        $req = new Request('GET', 'http://httbin.org');
        $res = new Response(200);
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        $cmd = new Command('foo', []);
        $em = $cmd->getEmitter();

        $em->on('prepare', function (PrepareEvent $e) use ($req) {
            $e->setRequest($req);
        });

        $c1 = $c2 = false;
        $em->on(
            'error',
            function (CommandErrorEvent $e) use (&$c1, $client, $cmd, $req, $res) {
                $e->setResult('foo');
                $this->assertSame($client, $e->getClient());
                $this->assertSame($cmd, $e->getCommand());
                $this->assertSame($req, $e->getRequest());
                $this->assertSame(
                    $res,
                    $e->getRequestErrorEvent()->getResponse()
                );
                $c1 = true;
            }
        );

        $c2 = false;
        $em->on('process', function () use (&$c2) { $c2 = true;});

        $transaction = new Transaction(new Client(), $req);
        $exc = new RequestException('foo', $req, $res);
        $errorEvent = new ErrorEvent($transaction, $exc);
        CommandEvents::prepare($cmd, $client);
        $req->getEmitter()->emit('error', $errorEvent);
        $this->assertTrue($c1);
        $this->assertFalse($c2);
    }

    public function testCanInterceptErrorWithoutSendingRequest()
    {
        $client = new Client();
        $request = $client->createRequest('GET', 'http://www.foo.com');
        $mock = new Mock();
        $request->getEmitter()->attach($mock);
        $mock->addException(new RequestException('foo', $request));

        $g = $this->getMockBuilder('GuzzleHttp\Command\AbstractClient')
            ->setConstructorArgs([$client])
            ->setMethods(['getCommand'])
            ->getMockForAbstractClass();
        $em = $g->getEmitter();

        $em->on('prepare', function (PrepareEvent $e) use ($request) {
            $e->setRequest($request);
        });

        $em->on('error', function (CommandErrorEvent $e) {
            $e->setResult('foo');
        }, RequestEvents::EARLY);

        $called = 0;
        $em->on('process', function (ProcessEvent $e) use (&$called) {
            $called++;
            $e->stopPropagation();
        }, RequestEvents::EARLY);

        $g->expects($this->once())
            ->method('getCommand')
            ->will($this->returnValue(new Command('fooCommand', [], $em)));

        $command = $g->getCommand('foo');
        $this->assertEquals('foo', $g->execute($command));
        $this->assertEquals(1, $called);
    }

    public function testEmitsErrorAndThrowsGenericException()
    {
        $request = new Request('GET', 'http://httbin.org');
        $response = new Response(200);
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        $command = new Command('foo', []);

        $command->getEmitter()->on(
            'prepare',
            function (PrepareEvent $e) use ($request) {
                $e->setRequest($request);
            }
        );

        $transaction = new Transaction(new Client(), $request);
        $exc = new RequestException('foo', $request, $response);
        $errorEvent = new ErrorEvent($transaction, $exc);
        CommandEvents::prepare($command, $client);
        $request->getEmitter()->emit('error', $errorEvent);
        $this->assertTrue($command->getConfig()->hasKey('__exception'));

        try {
            CommandEvents::process($command, $client, $request, $response);
            $this->fail('Did not throw');
        } catch (CommandException $e) {
            $this->assertSame($command, $e->getCommand());
            $this->assertSame($client, $e->getClient());
            $this->assertSame($request, $e->getRequest());
            $this->assertSame($response, $e->getResponse());
            $this->assertSame($exc, $e->getPrevious());
        }
    }

    public function specificExceptionProvider()
    {
        return [
            [400, 'GuzzleHttp\Command\Exception\CommandClientException'],
            [500, 'GuzzleHttp\Command\Exception\CommandServerException']
        ];
    }

    /**
     * @dataProvider specificExceptionProvider
     */
    public function testEmitsErrorAndThrowsSpecificException($code, $type)
    {
        $client = new Client();
        $client->getEmitter()->attach(new Mock([new Response($code)]));

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

        try {
            $mock->execute($command);
            $this->fail('Did not throw');
        } catch (CommandException $e) {
            $this->assertInstanceOf($type, $e);
        }
    }

    public function testCorrectlyHandlesRequestsThatFailBeforeSending()
    {
        $client = new Client();
        $request = $client->createRequest('GET', 'http://httpbin.org');
        $request->getEmitter()->attach(new Mock([
            new RequestException('foo', $request, null)
        ]));

        $mock = $this->getMockBuilder('GuzzleHttp\\Command\\AbstractClient')
            ->setConstructorArgs([$client, []])
            ->getMockForAbstractClass();

        $command = new Command('foo');
        $emitter = $command->getEmitter();

        $emitter->on('prepare', function(PrepareEvent $event) use ($request) {
            $event->setRequest($request);
        });

        try {
            $mock->execute($command);
            $this->fail('Did not throw');
        } catch (CommandException $e) {}
    }
}
