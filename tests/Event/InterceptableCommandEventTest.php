<?php
namespace GuzzleHttp\Tests\Command\Event;

use GuzzleHttp\Command\CommandTransaction;
use GuzzleHttp\Message\Request;

/**
 * @covers \GuzzleHttp\Command\Event\InterceptableCommandEvent
 */
class InterceptableCommandEventTest extends \PHPUnit_Framework_TestCase
{
    public function testCanIntercept()
    {
        $command = $this->getMock('GuzzleHttp\\Command\\CommandInterface');
        $client = $this->getMock('GuzzleHttp\\Command\\ServiceClientInterface');
        $request = new Request('GET', 'http://www.foo.com');
        $ctrans = new CommandTransaction($client, $command, $request);
        $ctrans->request = new Request('GET', 'http://www.goo.com');
        $event = $this->getMockBuilder('GuzzleHttp\Command\Event\InterceptableCommandEvent')
            ->setConstructorArgs([$ctrans])
            ->getMockForAbstractClass();
        $event->setResult('foo');
        $this->assertTrue($event->isPropagationStopped());
        $this->assertEquals('foo', $event->getResult());
        $this->assertEquals('foo', $ctrans->result);
    }
}
