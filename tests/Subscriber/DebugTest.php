<?php
namespace GuzzleHttp\Tests\Subscriber;

use GuzzleHttp\Client;
use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\Event\PrepareEvent;
use GuzzleHttp\Command\Event\ProcessEvent;
use GuzzleHttp\Command\Subscriber\Debug;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;
use GuzzleHttp\Subscriber\Mock;

/**
 * @covers GuzzleHttp\Command\Subscriber\Debug
 */
class DebugTest extends \PHPUnit_Framework_TestCase
{
    public function testDescribesSubscribedEvents()
    {
        $d = new Debug();
        $this->assertInternalType('array', $d->getEvents());
    }

    public function testProvidesDebugCommandEvents()
    {
        $http = new Client();
        $http->getEmitter()->attach(new Mock([
            new Response(200, ['foo' => 'bar'])
        ]));
        $command = new Command('CmdName', [
            'arg' => 'value',
            'foo' => Stream::factory('foo')
        ]);
        $client = $this->getMockBuilder('GuzzleHttp\\Command\\AbstractClient')
            ->setConstructorArgs([$http])
            ->getMockForAbstractClass();

        $command->getEmitter()->on(
            'prepare',
            function (PrepareEvent $e) use ($http) {
                $e->setRequest($http->createRequest('GET', 'http://foo.com'));
            }
        );

        $command->getEmitter()->on(
            'process',
            function (ProcessEvent $e) use ($http) {
                $e->setResult(['result' => 'value']);
            }
        );

        $res = fopen('php://temp', 'r+');
        $debug = new Debug(['output' => $res]);
        $command->getEmitter()->attach($debug);
        $client->execute($command);
        rewind($res);
        $out = stream_get_contents($res);

        $checks = [
            'Starting the command:prepare event for ',
            'Done with the command:prepare event for ',
            '(took ',
            'Starting the request:before event for ',
            'Done with the request:before event for ',
            'Starting the request:complete event for ',
            'Done with the request:complete event for ',
            'Starting the command:process event for ',
            'Done with the command:process event for ',
        ];

        foreach ($checks as $check) {
            $this->assertContains($check, $out);
        }
    }
}
