<?php
namespace GuzzleHttp\Tests\Command\Event;

use GuzzleHttp\Client;
use GuzzleHttp\Command\CommandTransaction;
use GuzzleHttp\Command\Event\CommandErrorEvent;
use GuzzleHttp\Command\Event\CommandEvents;
use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\Event\PrepareEvent;
use GuzzleHttp\Command\Event\ProcessEvent;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Event\RequestEvents;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Message\FutureResponse;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Subscriber\Mock;
use GuzzleHttp\Transaction;

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
        $trans = CommandEvents::prepareTransaction($client, $command);
        $this->assertInstanceOf('GuzzleHttp\Command\CommandTransaction', $trans);
        $this->assertSame($request, $trans->request);
        $this->assertNull($trans->result);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage No request was prepared for the command
     */
    public function testEnsuresThePrepareEventIsHandled()
    {
        $command = new Command('foo', []);
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        CommandEvents::prepareTransaction($client, $command);
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
        $trans = CommandEvents::prepareTransaction($client, $command);
        $this->assertNull($trans->request);
        $this->assertEquals('123', $trans->result);
        $this->assertTrue($called);
    }

    public function testPassesExceptionsThroughUntouchedInPrepareError()
    {
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        $command = new Command('foo', []);
        $trans = new CommandTransaction($client, $command);
        $trans->request = new Request('GET', 'http://www.foo.bar');
        $ex = new CommandException('foo', $trans);
        $command->getEmitter()->on(
            'prepare',
            function (PrepareEvent $e) use ($ex) {
                throw $ex;
            }
        );

        try {
            CommandEvents::prepareTransaction($client, $command);
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
        $trans = new CommandTransaction($client, $cmd);
        $trans->request = $req;
        $trans->response = $res;
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
        CommandEvents::process($trans);
        $this->assertEquals('foo', $trans->result);
        $this->assertTrue($c);
    }

    public function testEmitsErrorEventAndCanInterceptWithSuccessfulResult()
    {
        $req = new Request('GET', 'http://httbin.org');
        $res = new Response(200);
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        $cmd = new Command('foo', []);
        $em = $cmd->getEmitter();
        $exc = new RequestException('foo', $req, $res);

        $em->on('prepare', function (PrepareEvent $e) use ($req, $exc) {
            $e->setRequest($req);
            throw $exc;
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
                    $e->getException()->getResponse()
                );
                $c1 = true;
            }
        );

        $c2 = false;
        $em->on('process', function () use (&$c2) { $c2 = true;});
        CommandEvents::prepareTransaction($client, $cmd);
        $this->assertTrue($c1);
        $this->assertFalse($c2);
    }

    public function testCanInterceptErrorWithoutSendingRequest()
    {
        $client = new Client();
        $request = $client->createRequest('GET', 'http://www.foo.com');
        $mock = new Mock();
        $request->getEmitter()->attach($mock);
        $reqex = new RequestException('foo', $request);
        $mock->addException($reqex);

        $g = $this->getMockBuilder('GuzzleHttp\Command\AbstractClient')
            ->setConstructorArgs([$client])
            ->setMethods(['getCommand'])
            ->getMockForAbstractClass();
        $em = $g->getEmitter();
        $command = new Command('fooCommand', [], ['emitter' => $em]);
        $g->expects($this->once())
            ->method('getCommand')
            ->will($this->returnValue($command));

        $em->on('prepare', function (PrepareEvent $e) use ($request) {
            $e->setRequest($request);
        });

        $em->on('error', function (CommandErrorEvent $e) use ($reqex) {
            $this->assertInstanceOf(
                'GuzzleHttp\Command\Exception\CommandException',
                $e->getException()
            );
            $this->assertSame($reqex, $e->getException()->getPrevious());
            $e->setResult('foo');
        }, RequestEvents::EARLY);

        // process should not be called because we intercepted with result.
        $called = false;
        $em->on('process', function (ProcessEvent $e) use (&$called) {
            $called = true;
        }, RequestEvents::EARLY);

        $c = $g->getCommand('foo');
        $this->assertEquals('foo', $g->execute($c));
        $this->assertFalse($called);
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
        $command->getEmitter()->on('prepare', function(PrepareEvent $event) {
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

        $called = false;
        $emitter->on('error', function() use ($request, &$called) {
            $called = true;
        });

        try {
            $mock->execute($command);
            $this->fail('Did not throw');
        } catch (CommandException $e) {
            $this->assertTrue($called);
        }
    }

    public function testCorrectlyHandlesRequestsThatFailWhenProcessing()
    {
        $client = new Client();
        $request = $client->createRequest('GET', 'http://httpbin.org');
        $request->getEmitter()->attach(new Mock([new Response(200)]));
        $mock = $this->getMockBuilder('GuzzleHttp\\Command\\AbstractClient')
            ->setConstructorArgs([$client, []])
            ->getMockForAbstractClass();
        $command = new Command('foo');
        $emitter = $command->getEmitter();
        $emitter->on('prepare', function(PrepareEvent $event) use ($request) {
            $event->setRequest($request);
        });
        $ex = new \Exception('baz');
        $c = false;
        $emitter->on('process', function(ProcessEvent $event) use ($request, $ex, &$c) {
            $c = true;
            throw $ex;
        });
        $emitter->on('error', function(CommandErrorEvent $event) use ($request, $ex) {
            $this->assertSame($event->getException(), $ex);
            $event->setResult('interception!');
        });
        $this->assertEquals('interception!', $mock->execute($command));
        $this->assertTrue($c);
    }

    public function testProcessesEventWhenResponseCompletes()
    {
        $response = new Response(200);
        $httpClient = new Client();
        $httpClient->getEmitter()->attach(new Mock([$response]));
        $client = $this->getMockBuilder('GuzzleHttp\\Command\\AbstractClient')
            ->setConstructorArgs([$httpClient])
            ->getMockForAbstractClass();
        $command = new Command('foo', []);
        $request = $httpClient->createRequest('GET', 'http://httbin.org');
        $command->getEmitter()->on(
            'prepare',
            function (PrepareEvent $e) use ($request) {
                $e->setRequest($request);
            }
        );
        $command->getEmitter()->on(
            'process',
            function (ProcessEvent $e) use ($request) {
                $e->setResult('processed!');
            }
        );
        $trans = CommandEvents::prepareTransaction($client, $command);
        $this->assertSame($response, $client->getHttpClient()->send($trans->request));
        $this->assertEquals('processed!', $trans->result);
    }

    public function testProcessesEventWhenFutureResponseCompletes()
    {
        $httpClient = new Client();
        $client = $this->getMockBuilder('GuzzleHttp\\Command\\AbstractClient')
            ->setConstructorArgs([$httpClient])
            ->getMockForAbstractClass();
        $response = new Response(200);
        $request = $httpClient->createRequest('GET', 'http://httbin.org');
        // note: when mocking future responses, you need to handle emitting the
        // complete events manually when dereferencing a response.
        $future = new FutureResponse(function () use ($httpClient, $request, $response) {
            $trans = new Transaction($httpClient, $request);
            $trans->response = $response;
            RequestEvents::emitComplete($trans);
            return $response;
        });
        $request->getEmitter()->attach(new Mock([$future]));
        $command = new Command('foo', [], ['future' => true]);
        $command->getEmitter()->on(
            'prepare',
            function (PrepareEvent $e) use ($request) {
                $e->setRequest($request);
            }
        );
        $command->getEmitter()->on(
            'process',
            function (ProcessEvent $e) use ($request) {
                $e->setResult('processed!');
            }
        );
        $trans = CommandEvents::prepareTransaction($client, $command);
        $this->assertTrue($trans->request->getConfig()->get('future'));
        $res = $client->getHttpClient()->send($trans->request);
        $this->assertInstanceOf('GuzzleHttp\Message\FutureResponse', $res);
        $this->assertNotSame($response, $res);
        $this->assertSame($res, $future);
        $this->assertNull($trans->result);
        $this->assertSame($res->deref(), $response);
        $this->assertEquals('processed!', $trans->result);
    }
}
