<?php

namespace GuzzleHttp\Tests\Subscriber;

use GuzzleHttp\Client;
use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\Event\PrepareEvent;
use GuzzleHttp\Command\Exception\CommandException;
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
        $client = $this->getMock('GuzzleHttp\\Command\\ServiceClientInterface');
        $plugin = (new ResultMock)->addMultiple([
            new Model([]),
            new CommandException('foo', $client, new Command('foo')),
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

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testThrowsExceptionWhenInvalidExceptionMessage()
    {
        (new ResultMock())->addExceptionMessage(5);
    }

    public function testCanMockCommandResults()
    {
        $client = $this->getMockBuilder('GuzzleHttp\\Command\\AbstractClient')
            ->setConstructorArgs([new Client])
            ->getMockForAbstractClass();

        $plugin = (new ResultMock)
            ->addResult(new Model(['foo' => 'bar']))
            ->addException(new CommandException('A', $client, new Command('foo')))
            ->addExceptionMessage('B');

        // 1. The Model object
        $event = new PrepareEvent(new Command('foo'), $client);
        $plugin->onPrepare($event);
        $this->assertInstanceOf('GuzzleHttp\Command\Model', $event->getResult());

        // 2. The Exception with "A"
        try {
            $plugin->onPrepare(new PrepareEvent(new Command('foo'), $client));
        } catch (CommandException $e) {
            $this->assertEquals('A', $e->getMessage());
        }

        // 2. The Exception with "B"
        try {
            $plugin->onPrepare(new PrepareEvent(new Command('foo'), $client));
        } catch (CommandException $e) {
            $this->assertEquals('B', $e->getMessage());
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
        $event = new PrepareEvent(new Command('foo'), $client);
        (new ResultMock)->onPrepare($event);
    }
}
