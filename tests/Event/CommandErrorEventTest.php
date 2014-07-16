<?php
namespace GuzzleHttp\Tests\Command\Event;

use GuzzleHttp\Command\Event\CommandErrorEvent;
use GuzzleHttp\Command\CommandTransaction;
use GuzzleHttp\Message\Response;

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
        $ctrans = new CommandTransaction($client, $command);
        $response = new Response(200);
        $ex = new \Exception('foo');
        $ctrans->setException($ex);
        $ctrans->setResponse($response);

        $event = new CommandErrorEvent($ctrans);
        $this->assertSame($ctrans, $event->getTransaction());
        $this->assertSame($command, $event->getCommand());
        $this->assertSame($client, $event->getClient());
        $this->assertSame($ex, $event->getException());
        $this->assertSame($response, $event->getResponse());
        $this->assertNull($event->getResult());

        $event->setResult('foo');
        $this->assertSame('foo', $event->getResult());
        $this->assertTrue($event->isPropagationStopped());

        $event->getContext()->set('abc', '123');
        $this->assertEquals('123', $ctrans->getContext()->get('abc'));
    }
}
