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
use GuzzleHttp\Message\Request;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Subscriber\Mock;

class CommandEventsTest extends \PHPUnit_Framework_TestCase
{
    public function testEmitsPrepareEvent()
    {
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        $command = new Command('foo', []);
        $trans = new CommandTransaction($client, $command);
        $request = new Request('GET', 'http://httbin.org');
        $command->getEmitter()->on(
            'prepare',
            function (PrepareEvent $e) use ($request) {
                $e->setRequest($request);
            }
        );
        CommandEvents::prepare($trans);
        $this->assertSame($request, $trans->getRequest());
        $this->assertNull($trans->getResult());
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage No request was prepared for the command
     */
    public function testEnsuresThePrepareEventIsHandled()
    {
        $command = new Command('foo', []);
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        $trans = new CommandTransaction($client, $command);
        CommandEvents::prepare($trans);
    }

    public function testPrepareEventCanInterceptWithResultBeforeSending()
    {
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        $command = new Command('foo', []);
        $trans = new CommandTransaction($client, $command);
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
        CommandEvents::prepare($trans);
        $this->assertNull($trans->getRequest());
        $this->assertEquals('123', $trans->getResult());
        $this->assertTrue($called);
    }

    public function testPassesExceptionsThroughUntouchedInPrepareError()
    {
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        $command = new Command('foo', []);
        $trans = new CommandTransaction($client, $command);
        $ex = new CommandException('foo', $trans);
        $command->getEmitter()->on(
            'prepare',
            function (PrepareEvent $e) use ($ex) {
                throw $ex;
            }
        );

        try {
            CommandEvents::prepare($trans);
            $this->fail('Did not throw');
        } catch (CommandException $e) {
            $this->assertSame($ex, $e);
            $this->assertSame($ex, $trans->getException());
        }
    }

    public function testEmitsProcessEvent()
    {
        $req = new Request('GET', 'http://httbin.org');
        $res = new Response(200);
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        $cmd = new Command('foo', []);
        $trans = new CommandTransaction($client, $cmd);
        $trans->setRequest($req);
        $trans->setResponse($res);
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
        $this->assertEquals('foo', $trans->getResult());
        $this->assertTrue($c);
    }

    public function testEmitsErrorEventAndCanInterceptWithSuccessfulResult()
    {
        $req = new Request('GET', 'http://httbin.org');
        $res = new Response(200);
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        $cmd = new Command('foo', []);
        $trans = new CommandTransaction($client, $cmd);
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
        CommandEvents::prepare($trans);
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

        try {
            $mock->execute($command);
            $this->fail('Did not throw');
        } catch (CommandException $e) {}
    }
}
