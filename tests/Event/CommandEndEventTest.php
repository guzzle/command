<?php
namespace GuzzleHttp\Tests\Command\Event;

use GuzzleHttp\Command\Event\CommandEndEvent;
use GuzzleHttp\Command\CommandTransaction;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Message\Response;

/**
 * @covers \GuzzleHttp\Command\Event\CommandEndEvent
 */
class CommandEndEventTest extends \PHPUnit_Framework_TestCase
{
    public function testHasData()
    {
        $command = $this->getMock('GuzzleHttp\\Command\\CommandInterface');
        $client = $this->getMock('GuzzleHttp\\Command\\ServiceClientInterface');
        $request = new Request('GET', 'http://foo.com');
        $ctrans = new CommandTransaction($client, $command, $request);
        $ex = new \Exception('foo');
        $ctrans->exception = $ex;
        $ctrans->response = new Response(200);
        $event = new CommandEndEvent($ctrans);
        $this->assertSame($ex, $event->getException());
        $this->assertSame($ctrans->response, $event->getResponse());
    }
}
