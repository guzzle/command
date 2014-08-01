<?php
namespace GuzzleHttp\Tests\Command;

use GuzzleHttp\Adapter\Transaction;
use GuzzleHttp\Client;
use GuzzleHttp\Command\CommandToRequestIterator;
use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\Event\CommandErrorEvent;
use GuzzleHttp\Command\Event\PrepareEvent;
use GuzzleHttp\Command\Event\ProcessEvent;
use GuzzleHttp\Event\CompleteEvent;
use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Exception\RequestException;
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
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        new CommandToRequestIterator('foo', $client, []);
    }

    public function testCanUseArray()
    {
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        $cmd = new Command('foo');
        $request = new Request('GET', 'http://httbin.org');
        $cmd->getEmitter()->on(
            'prepare',
            function (PrepareEvent $event) use ($request) {
                $event->setRequest($request);
            }
        );
        $commands = [$cmd];
        $i = new CommandToRequestIterator($commands, $client, []);
        $this->assertTrue($i->valid());
        $this->assertSame($request, $i->current());
        $i->next();
        $this->assertFalse($i->valid());
        $this->assertNull($i->current());
    }

    public function testCanUseAnIterator()
    {
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        $request1 = new Request('GET', 'http://httbin.org');
        $request2 = new Request('GET', 'http://httbin.org');

        $cmd = new Command('foo');
        $cmd->getEmitter()->on(
            'prepare',
            function (PrepareEvent $event) use ($request1) {
                $event->setRequest($request1);
            }
        );
        $cmd2 = new Command('foo');
        $cmd2->getEmitter()->on(
            'prepare',
            function (PrepareEvent $event) use ($request2) {
                $event->setRequest($request2);
            }
        );
        $commands = new \ArrayIterator([$cmd, $cmd2]);
        $i = new CommandToRequestIterator($commands, $client, []);

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
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        $commands = ['foo'];
        $i = new CommandToRequestIterator($commands, $client);
        $i->valid();
    }

    public function testHooksUpEvents()
    {
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        $request = new Request('GET', 'http://httbin.org');
        $command = new Command('foo');
        $calledPrepare = $calledProcess = $calledError = $responseSet = false;
        $commands = [$command];

        $i = new CommandToRequestIterator($commands, $client, [
            'prepare' => function (PrepareEvent $event) use (&$calledPrepare, $request) {
                $calledPrepare = true;
                $event->setRequest($request);
            },
            'process' => function (ProcessEvent $event) use (&$calledProcess, &$responseSet) {
                $calledProcess = true;
                $responseSet = $event->getResponse() instanceof Response;
            },
            'error' => function (CommandErrorEvent $event) use (&$calledError) {
                $calledError = true;
                $event->setResult(null);
            }
        ]);

        $this->assertTrue($i->valid());
        $this->assertTrue($calledPrepare);
        $this->assertFalse($calledProcess);
        $this->assertFalse($calledError);
        $this->assertFalse($responseSet);

        $transaction = new Transaction(new Client(), $request);
        $transaction->setResponse(new Response(200));
        $mockComplete = new CompleteEvent($transaction);
        $request->getEmitter()->emit('complete', $mockComplete);
        $this->assertTrue($calledProcess);
        $this->assertFalse($calledError);

        $mockError = new ErrorEvent(
            $transaction,
            new RequestException('foo', $request)
        );
        $request->getEmitter()->emit('error', $mockError);
        $this->assertTrue($calledError);
    }

    public function testSkipsInterceptedCommands()
    {
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        $request = new Request('GET', 'http://httbin.org');

        $command1 = new Command('foo');
        $command1->getEmitter()->on(
            'prepare',
            function (PrepareEvent $e) use ($request) {
                $e->setRequest($request);
            }
        );
        $command2 = new Command('bar');
        $command2->getEmitter()->on(
            'prepare',
            function (PrepareEvent $e) {
                $e->setResult('baz');
            }
        );

        $commands = [$command1, $command2];
        $i = new CommandToRequestIterator($commands, $client);
        $this->assertTrue($i->valid());
        $this->assertSame($request, $i->current());
        $i->next();
        $this->assertFalse($i->valid());
        $this->assertNull($i->current());
    }
}
