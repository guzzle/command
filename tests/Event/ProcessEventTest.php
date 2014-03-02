<?php

namespace GuzzleHttp\Tests\Command\Event;

use GuzzleHttp\Command\Event\ProcessEvent;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Message\Response;

/**
 * @covers \GuzzleHttp\Command\Event\ProcessEvent
 * @covers \GuzzleHttp\Command\Event\AbstractCommandEvent
 */
class ProcessEventTest extends \PHPUnit_Framework_TestCase
{
    public function testHasData()
    {
        $command = $this->getMock('GuzzleHttp\\Command\\CommandInterface');
        $client = $this->getMock('GuzzleHttp\\Command\\ServiceClientInterface');
        $request = new Request('GET', 'http://httbin.org');
        $response = new Response(200);
        $result = null;
        $event = new ProcessEvent($command, $client, $request, $response);
        $this->assertSame($command, $event->getCommand());
        $this->assertSame($client, $event->getClient());
        $this->assertSame($request, $event->getRequest());
        $this->assertSame($response, $event->getResponse());
        $this->assertNull($event->getResult());
    }

    public function testCanSetResultAndDoesNotStopPropagation()
    {
        $command = $this->getMock('GuzzleHttp\\Command\\CommandInterface');
        $client = $this->getMock('GuzzleHttp\\Command\\ServiceClientInterface');
        $request = new Request('GET', 'http://httbin.org');
        $response = new Response(200);
        $result = null;
        $event = new ProcessEvent($command, $client, $request, $response);
        $event->setResult('foo');
        $this->assertSame('foo', $event->getResult());
        $this->assertFalse($event->isPropagationStopped());
    }

    public function testCanCreateWithResult()
    {
        $command = $this->getMock('GuzzleHttp\\Command\\CommandInterface');
        $client = $this->getMock('GuzzleHttp\\Command\\ServiceClientInterface');
        $request = new Request('GET', 'http://httbin.org');
        $response = new Response(200);
        $event = new ProcessEvent($command, $client, $request, $response, 'hi');
        $this->assertSame('hi', $event->getResult());
    }
}
