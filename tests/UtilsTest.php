<?php
namespace GuzzleHttp\Tests\Command;

use GuzzleHttp\Command\Event\PrepareEvent;
use GuzzleHttp\Command\Event\ProcessEvent;
use GuzzleHttp\Command\Utils;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Ring\Client\MockAdapter;
use GuzzleHttp\Ring\Future;
use GuzzleHttp\Client;
use GuzzleHttp\Command\Command;
use GuzzleHttp\Subscriber\Mock;

class UtilsTest extends \PHPUnit_Framework_TestCase
{
    private function getClient()
    {
        $http = new Client(['adapter' => new MockAdapter(
            new Future(function () {
                return ['status' => 200, 'headers' => []];
            })
        )]);

        $client = $this->getMockBuilder('GuzzleHttp\\Command\\AbstractClient')
            ->setConstructorArgs([$http, []])
            ->setMethods(['getCommand'])
            ->getMockForAbstractClass();

        $client->expects($this->any())
            ->method('getCommand')
            ->will($this->returnCallback(function () use ($client) {
                    return new Command('foo', [], [
                        'emitter' => clone $client->getEmitter()
                    ]);
                }));

        return $client;
    }

    public function testCreatesPool()
    {
        $client = $this->getClient();
        $commands = [$client->getCommand('foo')];
        $pool = Utils::createPool($client, $commands, ['pool_size' => 10]);
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
        list($client, $commands, $pool) = $things;
        $client->getHttpClient()->getEmitter()->attach(new Mock([
            new Response(404),
            new Response(404),
        ]));
        $called = false;
        $commands[0]->getEmitter()->on(
            'prepare',
            function (PrepareEvent $e) use ($client) {
                $e->setRequest($client->getHttpClient()->createRequest('GET', 'http://foo.com'));
            }
        );
        $commands[0]->getEmitter()->on('error', function () use (&$called) {
            $called = true;
        });
        $pool->deref();
        $this->assertTrue($called);
    }

    public function testSendsBatch()
    {
        $client = $this->getClient();
        $client->getEmitter()->on(
            'prepare',
            function (PrepareEvent $e) use ($client) {
                $e->setRequest($client->getHttpClient()->createRequest('GET', 'http://foo.com'));
            }
        );
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
        $results = Utils::batch($client, $commands, [
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
