<?php
namespace GuzzleHttp\Tests\Command;

use GuzzleHttp\Command\Event\InitEvent;
use GuzzleHttp\Command\Event\PreparedEvent;
use GuzzleHttp\Ring\Client\MockAdapter;
use GuzzleHttp\Ring\RingFuture;
use GuzzleHttp\Client;
use GuzzleHttp\Command\CommandToRequestIterator;
use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\Event\ProcessEvent;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Message\Response;

/**
 * @covers \GuzzleHttp\Command\CommandToRequestIterator
 */
class CommandToRequestIteratorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testValidatesSource()
    {
        new CommandToRequestIterator(function () {}, 'foo', []);
    }

    public function testCanUseArray()
    {
        $http = new Client();
        $req = $http->createRequest('GET', 'http://foo.com');
        $cmd = new Command('foo');
        $commands = [$cmd];
        $i = new CommandToRequestIterator(function () use ($req) {
            return ['request' => $req];
        }, $commands, []);
        $this->assertTrue($i->valid());
        $this->assertSame($req, $i->current());
        $i->next();
        $this->assertFalse($i->valid());
        $this->assertNull($i->current());
    }

    public function testCanUseAnIterator()
    {
        $http = new Client();
        $request1 = $http->createRequest('GET', 'http://httbin.org');
        $request2 = $http->createRequest('GET', 'http://httbin.org');
        $cmd = new Command('foo');
        $cmd2 = new Command('foo');
        $commands = new \ArrayIterator([$cmd, $cmd2]);
        $i = new CommandToRequestIterator(function ($c) use ($request1, $request2, $cmd) {
            return ($c === $cmd)
                ? ['request' => $request1]
                : ['request' => $request2];
        }, $commands, []);

        $this->assertEquals(0, $i->key());
        $this->assertTrue($i->valid());
        $this->assertTrue($i->valid());
        $this->assertSame($request1, $i->current());
        $i->next();
        $this->assertEquals(1, $i->key());
        $this->assertTrue($i->valid());
        $this->assertSame($request2, $i->current());
        $i->next();
        $this->assertEquals(null, $i->key());
        $this->assertFalse($i->valid());
        $this->assertNull($i->current());

        $i->rewind();
        $this->assertEquals(0, $i->key());
        $this->assertTrue($i->valid());
        $this->assertSame($request1, $i->current());
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testEnsuresEachValueIsCommand()
    {
        $commands = ['foo'];
        $i = new CommandToRequestIterator(function () {}, $commands);
        $i->valid();
    }

    public function testHooksUpEvents()
    {
        $http = new Client(['adapter' => new MockAdapter(
            new RingFuture(function () {
                return ['status' => 200, 'headers' => []];
            })
        )]);

        $client = $this->getMockBuilder('GuzzleHttp\\Command\\AbstractClient')
            ->setConstructorArgs([$http, []])
            ->setMethods(['getCommand', 'serializeRequest'])
            ->getMockForAbstractClass();
        $client->expects($this->once())
            ->method('getCommand')
            ->will($this->returnValue(new Command('foo')));
        $client->expects($this->any())
            ->method('serializeRequest')
            ->will($this->returnValue(
                $http->createRequest('GET', 'http://httbin.org')
            ));

        $command = $client->getCommand('foo');
        $calledPrepared = $calledProcess = $calledInit = $responseSet = false;

        $client->executeAll([$command], [
            'init' => function (InitEvent $e) use (&$calledInit) {
                $calledInit = true;
            },
            'prepared' => function (PreparedEvent $e) use (&$calledPrepared) {
                $calledPrepared = true;
            },
            'process' => function (ProcessEvent $e) use (&$calledProcess, &$responseSet) {
                $calledProcess = true;
                $responseSet = $e->getResponse() instanceof Response;
            }
        ]);

        $this->assertCount(3, $command->getEmitter()->listeners());
        $this->assertTrue($calledInit);
        $this->assertTrue($calledPrepared);
        $this->assertTrue($calledProcess);
    }

    public function testSkipsInterceptedCommands()
    {
        $req = new Request('GET', 'http://foo.com');
        $command1 = new Command('foo');
        $command2 = new Command('bar');
        $command2->getEmitter()->on(
            'prepare',
            function (PreparedEvent $e) {
                $e->intercept('foo');
            }
        );

        $commands = [$command1, $command2];
        $i = new CommandToRequestIterator(function ($c) use ($command1, $req) {
            return $c === $command1
                ? ['request' => $req]
                : ['result' => 'foo'];
        }, $commands);
        $this->assertTrue($i->valid());
        $this->assertSame($req, $i->current());
        $i->next();
        $this->assertFalse($i->valid());
        $this->assertNull($i->current());
    }

    public function testPreventsTransferExceptions()
    {
        $http = new Client(['adapter' => new MockAdapter(['status' => 404])]);
        $client = $this->getMockBuilder('GuzzleHttp\\Command\\AbstractClient')
            ->setConstructorArgs([$http, []])
            ->setMethods(['getCommand', 'serializeRequest'])
            ->getMockForAbstractClass();
        $client->expects($this->once())
            ->method('getCommand')
            ->will($this->returnValue(new Command('foo')));
        $client->expects($this->any())
            ->method('serializeRequest')
            ->will($this->returnValue(
                $http->createRequest('GET', 'http://httbin.org')
            ));
        $command = $client->getCommand('foo');
        $called = false;
        $client->executeAll([$command], [
            'process' => function (ProcessEvent $e) use (&$called) {
                $called = [$e->getResponse(), $e->getException()];
            }
        ]);

        $this->assertNotFalse($called);
    }
}
