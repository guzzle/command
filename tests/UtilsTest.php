<?php
namespace GuzzleHttp\Tests\Command;

use GuzzleHttp\Command\Event\ProcessEvent;
use GuzzleHttp\Command\CommandUtils;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Ring\Client\MockAdapter;
use GuzzleHttp\Ring\Future;
use GuzzleHttp\Client;
use GuzzleHttp\Command\Command;
use GuzzleHttp\Subscriber\Mock;

class UtilsTest extends \PHPUnit_Framework_TestCase
{
    private function getClient(RequestInterface $request)
    {
        $http = new Client(['adapter' => new MockAdapter(
            new Future(function () {
                return ['status' => 200, 'headers' => []];
            })
        )]);

        $client = $this->getMockBuilder('GuzzleHttp\\Command\\AbstractClient')
            ->setConstructorArgs([$http, []])
            ->setMethods(['getCommand', 'serializeRequest'])
            ->getMockForAbstractClass();

        $client->expects($this->any())
            ->method('getCommand')
            ->will($this->returnCallback(function () use ($client) {
                return new Command('foo', [], [
                    'emitter' => clone $client->getEmitter()
                ]);
            }));

        $client->expects($this->any())
            ->method('serializeRequest')
            ->will($this->returnValue($request));

        return $client;
    }

    public function testCreatesPool()
    {
        $client = $this->getClient(new Request('GET', 'http://www.foo.com'));
        $commands = [$client->getCommand('foo')];
        $pool = CommandUtils::createPool($client, $commands, ['pool_size' => 10]);
        $this->assertInstanceOf('GuzzleHttp\Pool', $pool);
        $this->assertFalse($pool->realized());
        $this->assertEquals(10, $this->readAttribute($pool, 'poolSize'));
        return [$client, $commands, $pool];
    }

    /**
     * @depends testCreatesPool
     */
    public function testDoesNotThrowPoolErrors($things)
    {
        $client = $this->getClient(new Request('GET', 'http://foo.com'));
        $commands = [$client->getCommand('foo')];
        $pool = CommandUtils::createPool($client, $commands, ['pool_size' => 10]);
        $client->getHttpClient()->getEmitter()->attach(new Mock([
            new Response(404),
            new Response(404),
        ]));
        $called = false;
        $commands[0]->getEmitter()->on('error', function () use (&$called) {
            $called = true;
        });
        $pool->deref();
        $this->assertTrue($called);
    }

    public function testSendsBatch()
    {
        $client = $this->getClient(new Request('GET', 'http://foo.com'));
        $client->getEmitter()->on(
            'process',
            function (ProcessEvent $e) use ($client) {
                $e->setResult($e->getResponse()->getStatusCode());
            }
        );
        $client->getHttpClient()->getEmitter()->attach(new Mock([
            new Response(404),
            new Response(200),
        ]));
        $commands = [
            $client->getCommand('foo'),
            $client->getCommand('foo')
        ];
        $results = CommandUtils::batch($client, $commands, [
            'error' => function () use (&$calledError) {
                $calledError = true;
            },
            'process' => function () use (&$calledProcess) {
                $calledProcess = true;
            },
            'prepare' => function () use (&$calledPrepare) {
                $calledPrepare = true;
            },
        ]);
        $this->assertEquals(200, $results[$commands[1]]);
        $this->assertInstanceOf(
            'GuzzleHttp\Command\Exception\CommandException',
            $results[$commands[0]]
        );
        $this->assertTrue($calledError);
        $this->assertTrue($calledProcess);
        $this->assertTrue($calledPrepare);
    }
}
