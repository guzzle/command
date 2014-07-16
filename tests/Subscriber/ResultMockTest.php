<?php
namespace GuzzleHttp\Tests\Subscriber;

use GuzzleHttp\Client;
use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\CommandTransaction;
use GuzzleHttp\Command\Event\PrepareEvent;
use GuzzleHttp\Command\Model;
use GuzzleHttp\Command\Subscriber\ResultMock;

/**
 * @covers GuzzleHttp\Command\Subscriber\ResultMock
 */
class ResultMockTest extends \PHPUnit_Framework_TestCase
{
    public function testDescribesSubscribedEvents()
    {
        $mock = new ResultMock();
        $this->assertInternalType('array', $mock->getEvents());
    }

    public function testIsCountable()
    {
        $plugin = (new ResultMock)->addMultiple([
            new Model([]),
            new \Exception('foo'),
        ]);
        $this->assertEquals(2, count($plugin));
    }

    public function testCanClearQueue()
    {
        $plugin = new ResultMock();
        $plugin->addResult(['foo' => 'bar']);
        $plugin->clearQueue();
        $this->assertEquals(0, count($plugin));
    }

    public function testCanMockCommandResults()
    {
        $client = $this->getMockBuilder('GuzzleHttp\\Command\\AbstractClient')
            ->setConstructorArgs([new Client])
            ->getMockForAbstractClass();

        $trans = new CommandTransaction($client, new Command('foo'));
        $e1 = new \Exception('Foo');
        $e2 = new \Exception('Bar');

        $plugin = (new ResultMock)
            ->addResult(new Model(['foo' => 'bar']))
            ->addException($e1)
            ->addException($e2);

        // 1. The Model object
        $event = new PrepareEvent($trans);
        $plugin->onPrepare($event);
        $this->assertInstanceOf('GuzzleHttp\Command\Model', $event->getResult());

        // 2. The Exception with "Foo"
        try {
            $plugin->onPrepare(new PrepareEvent($trans));
        } catch (\Exception $e) {
            $this->assertEquals('Foo', $e->getMessage());
        }

        // 2. The Exception with "Bar"
        try {
            $plugin->onPrepare(new PrepareEvent($trans));
        } catch (\Exception $e) {
            $this->assertEquals('Bar', $e->getMessage());
        }
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testUpdateThrowsExceptionWhenEmpty()
    {
        $client = $this->getMockBuilder('GuzzleHttp\\Command\\AbstractClient')
            ->setConstructorArgs([new Client])
            ->getMockForAbstractClass();
        $event = new PrepareEvent(
            new CommandTransaction($client, new Command('foo'))
        );
        (new ResultMock)->onPrepare($event);
    }
}
