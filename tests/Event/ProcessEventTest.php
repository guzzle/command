<?php
namespace GuzzleHttp\Tests\Command\Event;

use GuzzleHttp\Command\Event\ProcessEvent;
use GuzzleHttp\Command\CommandTransaction;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Message\Response;

/**
 * @covers \GuzzleHttp\Command\Event\ProcessEvent
 * @covers \GuzzleHttp\Command\Event\CommandEvent
 */
class ProcessEventTest extends \PHPUnit_Framework_TestCase
{
    public function testHasData()
    {
        $command = $this->getMock('GuzzleHttp\\Command\\CommandInterface');
        $client = $this->getMock('GuzzleHttp\\Command\\ServiceClientInterface');
        $request = new Request('GET', 'http://foo.com');
        $ctrans = new CommandTransaction($client, $command);
        $ctrans->request = $request;
        $ex = new \Exception('foo');
        $ctrans->exception = $ex;
        $ctrans->response = new Response(200);
        $event = new ProcessEvent($ctrans);
        $this->assertSame($ctrans->response, $event->getResponse());
        $event->setResult('foo');
        $this->assertSame('foo', $ctrans->result);
        $this->assertSame('foo', $event->getResult());
        $this->assertFalse($event->isPropagationStopped());
        $this->assertSame($ctrans->request, $event->getRequest());
    }
}
