<?php
namespace GuzzleHttp\Tests\Command;

use GuzzleHttp\Command\Event\ProcessEvent;
use GuzzleHttp\Command\CommandUtils;
use GuzzleHttp\Ring\Client\MockHandler;
use GuzzleHttp\Ring\Future\FutureArray;
use GuzzleHttp\Client;
use GuzzleHttp\Command\Command;
use React\Promise\Deferred;

class CommandUtilsTest extends \PHPUnit_Framework_TestCase
{
    public function testSendsBatch()
    {
        $http = new Client();
        $requests = [
            $http->createRequest('GET', 'http://foo.com/baz'),
            $http->createRequest('GET', 'http://foo.com/bar')
        ];
        $this->assertNotSame($requests[0], $requests[1]);
        $responses = [['status' => 200], ['status' => 404]];

        $http = new Client([
            'handler' => new MockHandler(
                function () use (&$responses) {
                    $deferred = new Deferred();
                    $res = array_shift($responses);
                    return new FutureArray(
                        $deferred->promise(),
                        function () use ($res, $deferred) {
                            $deferred->resolve($res);
                        }
                    );
                }
            )
        ]);

        $client = $this->getMockBuilder('GuzzleHttp\\Command\\AbstractClient')
            ->setConstructorArgs([$http, []])
            ->setMethods(['getCommand', 'serializeRequest'])
            ->getMockForAbstractClass();

        $client->expects($this->any())
            ->method('getCommand')
            ->will($this->returnCallback(function ($name) use ($client) {
                return new Command($name);
            }));

        $client->expects($this->any())
            ->method('serializeRequest')
            ->will($this->returnCallback(function () use (&$requests) {
                return array_shift($requests);
            }));

        $commands = [$client->getCommand('foo'), $client->getCommand('bar')];
        $this->assertNotSame($commands[0], $commands[1]);

        $results = CommandUtils::batch($client, $commands, [
            'init' => function () use (&$calledInit) {
                $calledInit++;
            },
            'prepared' => function () use (&$calledPrepared) {
                $calledPrepared++;
            },
            'process' => function (ProcessEvent $e) use (&$calledProcess) {
                $calledProcess++;
                if (!$e->getException()) {
                    $e->setResult($e->getResponse()->getStatusCode());
                }
            }
        ]);

        $this->assertEquals(2, $calledInit);
        $this->assertEquals(2, $calledProcess);
        $this->assertEquals(2, $calledPrepared);

        $this->assertEquals(200, $results[0]);
        // The second command result is set to the exception.
        $this->assertInstanceOf(
            'GuzzleHttp\Command\Exception\CommandException',
            $results[1]
        );
    }
}
