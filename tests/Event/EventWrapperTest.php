<?php

namespace GuzzleHttp\Tests\Command\Event;

use GuzzleHttp\Adapter\Transaction;
use GuzzleHttp\Client;
use GuzzleHttp\Command\Event\CommandErrorEvent;
use GuzzleHttp\Command\Event\EventWrapper;
use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\Event\PrepareEvent;
use GuzzleHttp\Command\Event\ProcessEvent;
use GuzzleHttp\Command\Exception\CommandClientException;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Command\Exception\CommandServerException;
use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Message\Response;

/**
 * @covers \GuzzleHttp\Command\Event\EventWrapper
 */
class EventWrapperTest extends \PHPUnit_Framework_TestCase
{
    public function testEmitsPrepareEvent()
    {
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        $command = new Command('foo', []);
        $request = new Request('GET', '/');
        $command->getEmitter()->on('prepare', function (PrepareEvent $e) use ($request) {
            $e->setRequest($request);
        });
        $event = EventWrapper::prepareCommand($command, $client);
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
        EventWrapper::prepareCommand($command, $client);
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
        $command->getEmitter()->on('process', function (ProcessEvent $e) use (&$called) {
            $called = true;
            $this->assertEquals('123', $e->getResult());
        });
        $event = EventWrapper::prepareCommand($command, $client);
        $this->assertNull($event->getRequest());
        $this->assertTrue($event->isPropagationStopped());
        $this->assertEquals('123', $event->getResult());
        $this->assertTrue($called);
    }

    public function testWrapsLowLevelExceptionsOnPrepareEventError()
    {
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        $command = new Command('foo', []);
        $ex = new \Exception('foo');
        $command->getEmitter()->on('prepare', function (PrepareEvent $e) use ($ex) {
            throw $ex;
        });
        try {
            EventWrapper::prepareCommand($command, $client);
            $this->fail('Did not throw');
        } catch (CommandException $e) {
            $this->assertSame($command, $e->getCommand());
            $this->assertSame($ex, $e->getPrevious());
            $this->assertSame($client, $e->getClient());
            $this->assertNull($e->getRequest());
            $this->assertNull($e->getResponse());
        }
    }

    public function testPassesCommandExceptionsThroughUntouchedInPrepareError()
    {
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        $command = new Command('foo', []);
        $ex = new CommandException('foo', $client, $command);
        $command->getEmitter()->on('prepare', function (PrepareEvent $e) use ($ex) {
            throw $ex;
        });
        try {
            EventWrapper::prepareCommand($command, $client);
            $this->fail('Did not throw');
        } catch (CommandException $e) {
            $this->assertSame($ex, $e);
        }
    }

    public function testEmitsProcessEvent()
    {
        $request = new Request('GET', '/');
        $response = new Response(200);
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        $command = new Command('foo', []);
        $called = false;
        $command->getEmitter()->on(
            'process',
            function (ProcessEvent $e) use (&$called, $client, $command, $request, $response) {
                $e->setResult('foo');
                $this->assertSame($client, $e->getClient());
                $this->assertSame($command, $e->getCommand());
                $this->assertSame($request, $e->getRequest());
                $this->assertSame($response, $e->getResponse());
                $called = true;
            }
        );
        $result = EventWrapper::processCommand($command, $client, $request, $response);
        $this->assertEquals('foo', $result);
        $this->assertTrue($called);
    }

    public function testEmitsErrorEventAndCanInterceptWithSuccessfulResult()
    {
        $request = new Request('GET', '/');
        $response = new Response(200);
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        $command = new Command('foo', []);

        $command->getEmitter()->on('prepare', function (PrepareEvent $e) use ($request) {
            $e->setRequest($request);
        });

        $called1 = false;
        $command->getEmitter()->on(
            'error',
            function (CommandErrorEvent $e) use (&$called1, $client, $command, $request, $response) {
                $e->setResult('foo');
                $this->assertSame($client, $e->getClient());
                $this->assertSame($command, $e->getCommand());
                $this->assertSame($request, $e->getRequest());
                $this->assertSame($response, $e->getRequestErrorEvent()->getResponse());
                $called1 = true;
            }
        );

        $called2 = false;
        $command->getEmitter()->on('process', function (ProcessEvent $e) use (&$called2) {
            $this->assertEquals('foo', $e->getResult());
            $called2 = true;
        });

        $transaction = new Transaction(new Client(), $request);
        $exc = new RequestException('foo', $request, $response);
        $errorEvent = new ErrorEvent($transaction, $exc);
        EventWrapper::prepareCommand($command, $client);
        $request->getEmitter()->emit('error', $errorEvent);
        $this->assertTrue($called1);
        $this->assertTrue($called2);
    }

    public function testEmitsErrorAndThrowsGenericException()
    {
        $request = new Request('GET', '/');
        $response = new Response(200);
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        $command = new Command('foo', []);

        $command->getEmitter()->on('prepare', function (PrepareEvent $e) use ($request) {
            $e->setRequest($request);
        });

        $transaction = new Transaction(new Client(), $request);
        $exc = new RequestException('foo', $request, $response);
        $errorEvent = new ErrorEvent($transaction, $exc);
        EventWrapper::prepareCommand($command, $client);

        try {
            $request->getEmitter()->emit('error', $errorEvent);
            $this->fail('Did not throw');
        } catch (CommandException $e) {
            $this->assertSame($command, $e->getCommand());
            $this->assertSame($client, $e->getClient());
            $this->assertSame($request, $e->getRequest());
            $this->assertSame($response, $e->getResponse());
            $this->assertSame($exc, $e->getPrevious());
        }
    }

    public function testEmitsErrorAndThrowsClientException()
    {
        $request = new Request('GET', '/');
        $response = new Response(400);
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        $command = new Command('foo', []);
        $command->getEmitter()->on('prepare', function (PrepareEvent $e) use ($request) {
            $e->setRequest($request);
        });
        EventWrapper::prepareCommand($command, $client);

        try {
            $request->getEmitter()->emit(
                'error',
                new ErrorEvent(
                    new Transaction(new Client(), $request),
                    new RequestException('foo', $request, $response)
                )
            );
            $this->fail('Did not throw');
        } catch (CommandClientException $e) {
        }
    }

    public function testEmitsErrorAndThrowsServerException()
    {
        $request = new Request('GET', '/');
        $response = new Response(500);
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        $command = new Command('foo', []);
        $command->getEmitter()->on('prepare', function (PrepareEvent $e) use ($request) {
            $e->setRequest($request);
        });
        EventWrapper::prepareCommand($command, $client);

        try {
            $request->getEmitter()->emit(
                'error',
                new ErrorEvent(
                    new Transaction(new Client(), $request),
                    new RequestException('foo', $request, $response)
                )
            );
            $this->fail('Did not throw');
        } catch (CommandServerException $e) {
        }
    }
}
