<?php
namespace GuzzleHttp\Tests\Command\Event;

use GuzzleHttp\Command\CommandTransaction;
use GuzzleHttp\Message\Request;

/**
 * @covers \GuzzleHttp\Command\Event\AbstractCommandEvent
 */
class AbstractCommandEventTest extends \PHPUnit_Framework_TestCase
{
    public function testHasData()
    {
        $command = $this->getMock('GuzzleHttp\\Command\\CommandInterface');
        $client = $this->getMock('GuzzleHttp\\Command\\ServiceClientInterface');
        $request = new Request('GET', 'http://www.goo.com');
        $ctrans = new CommandTransaction($client, $command, $request);
        $ctrans->result = 'foo';
        $ex = new \Exception('foo');
        $ctrans->exception = $ex;
        $event = $this->getMockBuilder('GuzzleHttp\Command\Event\AbstractCommandEvent')
            ->setConstructorArgs([$ctrans])
            ->getMockForAbstractClass();
        $this->assertInstanceOf('GuzzleHttp\Collection', $event->getContext());
        $this->assertSame($ctrans, $event->getTransaction());
        $this->assertSame($command, $event->getCommand());
        $this->assertSame($client, $event->getClient());
        $this->assertSame($ctrans->result, $event->getResult());
        $this->assertSame($ctrans->request, $event->getRequest());
    }
}
