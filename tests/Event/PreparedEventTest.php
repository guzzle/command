<?php
namespace GuzzleHttp\Tests\Command\Event;

use GuzzleHttp\Command\CommandTransaction;
use GuzzleHttp\Command\Event\PreparedEvent;
use GuzzleHttp\Message\Request;

/**
 * @covers \GuzzleHttp\Command\Event\PreparedEvent
 */
class PreparedEventTest extends \PHPUnit_Framework_TestCase
{
    public function testCanIntercept()
    {
        $command = $this->getMock('GuzzleHttp\\Command\\CommandInterface');
        $client = $this->getMock('GuzzleHttp\\Command\\ServiceClientInterface');
        $ctrans = new CommandTransaction($client, $command);
        $ctrans->request = new Request('GET', 'http://www.goo.com');
        $event = $this->getMockBuilder('GuzzleHttp\Command\Event\PreparedEvent')
            ->setConstructorArgs([$ctrans])
            ->getMockForAbstractClass();
        $event->intercept('foo');
        $this->assertTrue($event->isPropagationStopped());
        $this->assertSame($ctrans->request, $event->getRequest());
        $this->assertEquals('foo', $event->getResult());
        $this->assertEquals('foo', $ctrans->result);
    }

    /**
     * @expectedException \GuzzleHttp\Exception\StateException
     */
    public function testEnsuresRequestIsPresent()
    {
        $command = $this->getMock('GuzzleHttp\\Command\\CommandInterface');
        $client = $this->getMock('GuzzleHttp\\Command\\ServiceClientInterface');
        new PreparedEvent(new CommandTransaction($client, $command));
    }
}
