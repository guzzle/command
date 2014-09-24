<?php
namespace GuzzleHttp\Tests\Command\Event;

use GuzzleHttp\Command\CommandTransaction;

/**
 * @covers \GuzzleHttp\Command\Event\CommandEvent
 */
class CommandEventTest extends \PHPUnit_Framework_TestCase
{
    public function testHasData()
    {
        $command = $this->getMock('GuzzleHttp\\Command\\CommandInterface');
        $client = $this->getMock('GuzzleHttp\\Command\\ServiceClientInterface');
        $ctrans = new CommandTransaction($client, $command);
        $ex = new \Exception('foo');
        $ctrans->exception = $ex;
        $event = $this->getMockBuilder('GuzzleHttp\Command\Event\CommandEvent')
            ->setConstructorArgs([$ctrans])
            ->getMockForAbstractClass();
        $this->assertInstanceOf('GuzzleHttp\Collection', $event->getContext());
        $this->assertSame($ctrans, $event->getTransaction());
        $this->assertSame($command, $event->getCommand());
        $this->assertSame($client, $event->getClient());
    }
}
