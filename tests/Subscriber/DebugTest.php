<?php
namespace GuzzleHttp\Tests\Subscriber;

use GuzzleHttp\Client;
use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\Event\PrepareEvent;
use GuzzleHttp\Command\Event\ProcessEvent;
use GuzzleHttp\Command\Subscriber\Debug;
use GuzzleHttp\Message\Response;
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
        $command = new Command('CmdName', ['arg' => 'value']);
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
            'Starting command >',
            'Starting the command:before event: ',
            'Done with the command:before event (took ',
            'Starting the request:before event: ',
            'Done with the request:before event',
            'Starting the request:complete event:',
            'Done with the request:complete event',
            'Starting the command:process event',
            'Done with the command:process event',
            'Sending the following command took',
            'End command      <'
        ];

        foreach ($checks as $check) {
            $this->assertContains($check, $out);
        }
    }
}
