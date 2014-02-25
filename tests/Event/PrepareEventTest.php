<?php

namespace GuzzleHttp\Tests\Command\Event;

use GuzzleHttp\Command\Event\PrepareEvent;
use GuzzleHttp\Message\Request;

/**
 * @covers \GuzzleHttp\Command\Event\PrepareEvent
 */
class PrepareEventTest extends \PHPUnit_Framework_TestCase
{
    public function testHasData()
    {
        $command = $this->getMock('GuzzleHttp\\Command\\CommandInterface');
        $client = $this->getMock('GuzzleHttp\\Command\\ServiceClientInterface');
        $event = new PrepareEvent($command, $client);
        $this->assertSame($command, $event->getCommand());
        $this->assertSame($client, $event->getClient());
        $this->assertNull($event->getResult());
    }

    public function testCanSetResultAndStopPropagation()
    {
        $command = $this->getMock('GuzzleHttp\\Command\\CommandInterface');
        $client = $this->getMock('GuzzleHttp\\Command\\ServiceClientInterface');
        $event = new PrepareEvent($command, $client);
        $event->setResult('foo');
        $this->assertEquals('foo', $event->getResult());
        $this->assertTrue($event->isPropagationStopped());
    }

    public function testCanSetRequest()
    {
        $command = $this->getMock('GuzzleHttp\\Command\\CommandInterface');
        $client = $this->getMock('GuzzleHttp\\Command\\ServiceClientInterface');
        $event = new PrepareEvent($command, $client);
        $request = new Request('GET', '/');
        $event->setRequest($request);
        $this->assertSame($request, $event->getRequest());
        $this->assertFalse($event->isPropagationStopped());
    }
}
