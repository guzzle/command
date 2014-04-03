<?php

namespace GuzzleHttp\Tests\Command\Event;

use GuzzleHttp\Adapter\Transaction;
use GuzzleHttp\Client;
use GuzzleHttp\Command\Event\CommandErrorEvent;
use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Message\Request;

/**
 * @covers \GuzzleHttp\Command\Event\CommandErrorEvent
 * @covers \GuzzleHttp\Command\Event\AbstractCommandEvent
 */
class ErrorEventTest extends \PHPUnit_Framework_TestCase
{
    public function testHasData()
    {
        $command = $this->getMock('GuzzleHttp\\Command\\CommandInterface');
        $client = $this->getMock('GuzzleHttp\\Command\\ServiceClientInterface');

        $httpClient = new Client();
        $request = new Request('GET', 'http://httbin.org');
        $transaction = new Transaction($httpClient, $request);
        $requestException = new RequestException('foo', $request);
        $errorEvent = new ErrorEvent($transaction, $requestException, []);

        $event = new CommandErrorEvent($command, $client, $errorEvent);
        $this->assertSame($command, $event->getCommand());
        $this->assertSame($client, $event->getClient());
        $this->assertSame($request, $event->getRequest());
        $this->assertSame($errorEvent, $event->getRequestErrorEvent());
        $this->assertNull($event->getResult());

        $event->setResult('foo');
        $this->assertSame('foo', $event->getResult());
        $this->assertTrue($event->isPropagationStopped());

        $this->assertNull($event['abc']);
        $event['abc'] = 'foo';
        $this->assertEquals('foo', $event['abc']);
    }
}
