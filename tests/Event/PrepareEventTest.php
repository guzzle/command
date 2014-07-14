<?php
namespace GuzzleHttp\Tests\Command\Event;

use GuzzleHttp\Command\CommandTransaction;
use GuzzleHttp\Command\Event\PrepareEvent;
use GuzzleHttp\Message\Request;

/**
 * @covers \GuzzleHttp\Command\Event\PrepareEvent
 * @covers \GuzzleHttp\Command\Event\AbstractCommandEvent
 */
class PrepareEventTest extends \PHPUnit_Framework_TestCase
{
    public function testHasData()
    {
        $command = $this->getMock('GuzzleHttp\\Command\\CommandInterface');
        $client = $this->getMock('GuzzleHttp\\Command\\ServiceClientInterface');
        $trans = new CommandTransaction($client, $command);
        $event = new PrepareEvent($trans);
        $this->assertSame($trans, $event->getTransaction());
        $this->assertSame($command, $event->getCommand());
        $this->assertSame($client, $event->getClient());
        $this->assertNull($event->getResult());
    }

    public function testCanSetResultAndStopPropagation()
    {
        $command = $this->getMock('GuzzleHttp\\Command\\CommandInterface');
        $client = $this->getMock('GuzzleHttp\\Command\\ServiceClientInterface');
        $trans = new CommandTransaction($client, $command);
        $event = new PrepareEvent($trans);
        $event->setResult('foo');
        $this->assertEquals('foo', $event->getResult());
        $this->assertTrue($event->isPropagationStopped());
    }

    public function testCanSetRequest()
    {
        $command = $this->getMock('GuzzleHttp\\Command\\CommandInterface');
        $client = $this->getMock('GuzzleHttp\\Command\\ServiceClientInterface');
        $trans = new CommandTransaction($client, $command);
        $event = new PrepareEvent($trans);
        $request = new Request('GET', 'http://httbin.org');
        $event->setRequest($request);
        $this->assertSame($request, $event->getRequest());
        $this->assertFalse($event->isPropagationStopped());
    }
}
