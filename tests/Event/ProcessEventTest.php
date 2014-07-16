<?php
namespace GuzzleHttp\Tests\Command\Event;

use GuzzleHttp\Command\Event\ProcessEvent;
use GuzzleHttp\Command\CommandTransaction;
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
        $trans = new CommandTransaction($client, $command);
        $request = new Request('GET', 'http://httbin.org');
        $response = new Response(200);
        $trans->setRequest($request);
        $trans->setResponse($response);
        $event = new ProcessEvent($trans);
        $this->assertSame($command, $event->getCommand());
        $this->assertSame($client, $event->getClient());
        $this->assertSame($request, $event->getRequest());
        $this->assertSame($response, $event->getResponse());
        $this->assertSame($trans, $event->getTransaction());
        $this->assertNull($event->getResult());
    }

    public function testCanSetResultAndDoesNotStopPropagation()
    {
        $command = $this->getMock('GuzzleHttp\\Command\\CommandInterface');
        $client = $this->getMock('GuzzleHttp\\Command\\ServiceClientInterface');
        $trans = new CommandTransaction($client, $command);
        $result = null;
        $event = new ProcessEvent($trans);
        $event->setResult('foo');
        $this->assertSame('foo', $event->getResult());
        $this->assertSame('foo', $trans->getResult());
        $this->assertFalse($event->isPropagationStopped());
    }

    public function testCanCreateWithResult()
    {
        $command = $this->getMock('GuzzleHttp\\Command\\CommandInterface');
        $client = $this->getMock('GuzzleHttp\\Command\\ServiceClientInterface');
        $trans = new CommandTransaction($client, $command);
        $trans->setResult('foo');
        $event = new ProcessEvent($trans);
        $this->assertSame('foo', $event->getResult());
    }
}
