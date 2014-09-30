<?php
namespace GuzzleHttp\Tests\Command;

use GuzzleHttp\Command\Event\ProcessEvent;
use GuzzleHttp\Command\CommandUtils;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Ring\Client\MockAdapter;
use GuzzleHttp\Ring\RingFuture;
use GuzzleHttp\Client;
use GuzzleHttp\Command\Command;

class CommandUtilsTest extends \PHPUnit_Framework_TestCase
{
    private function getClient(RequestInterface $request, array $responses)
    {
        $http = new Client([
            'adapter' => new MockAdapter(
                function () use (&$responses) {
                    $res = array_shift($responses);
                    return new RingFuture(function () use ($res) { return $res; });
                }
            )
        ]);

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
            ->will($this->returnCallback(function () use ($request) {
                return clone $request;
            }));

        return $client;
    }

    public function testSendsBatch()
    {
        $http = new Client();
        $client = $this->getClient(
            $http->createRequest('GET', 'http://foo.com'),
            [
                ['status' => 200],
                ['status' => 404],
            ]
        );

        $commands = [
            $client->getCommand('foo'),
            $client->getCommand('bar')
        ];

        $results = CommandUtils::batch($client, $commands, [
            'init' => function () use (&$calledInit) {
                $calledInit = true;
            },
            'prepared' => function () use (&$calledPrepared) {
                $calledPrepared = true;
            },
            'process' => function (ProcessEvent $e) use (&$calledProcess) {
                $calledProcess = true;
                if (!$e->getException()) {
                    $e->setResult($e->getResponse()->getStatusCode());
                }
            }
        ]);

        $this->assertTrue($calledInit);
        $this->assertTrue($calledProcess);
        $this->assertTrue($calledPrepared);

        $this->assertEquals(200, $results[0]);
        $this->assertInstanceOf(
            'GuzzleHttp\Command\Exception\CommandException',
            $results[1]
        );
    }
}
