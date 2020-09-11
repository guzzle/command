<?php
namespace GuzzleHttp\Tests\Command\Guzzle;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Command\Result;
use GuzzleHttp\Command\ServiceClient;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\Request;

/**
 * @covers \GuzzleHttp\Command\ServiceClient
 */
class ServiceClientTest extends TestCase
{
    private function getServiceClient(array $responses)
    {
        return new ServiceClient(
            new HttpClient([
                'handler' => new MockHandler($responses)
            ]),
            function (CommandInterface $command) {
                $data = $command->toArray();
                $data['action'] = $command->getName();
                return new Request('POST', '/', [], http_build_query($data));
            },
            function (ResponseInterface $response, RequestInterface $request) {
                $data = json_decode($response->getBody(), true);
                parse_str($request->getBody(), $data['_request']);
                return new Result($data);
            }
        );
    }

    public function testCanGetHttpClientAndHandlers()
    {
        $httpClient = new HttpClient();
        $handlers = new HandlerStack();
        $fn = function () {};
        $serviceClient = new ServiceClient($httpClient, $fn, $fn, $handlers);
        $this->assertSame($httpClient, $serviceClient->getHttpClient());
        $this->assertSame($handlers, $serviceClient->getHandlerStack());
    }

    public function testExecuteCommandViaMagicMethod()
    {
        $client = $this->getServiceClient([
            new Response(200, [], '{"foo":"bar"}'),
            new Response(200, [], '{"foofoo":"barbar"}'),
        ]);

        // Synchronous
        $result1 = $client->doThatThingYouDo(['fizz' => 'buzz']);
        $this->assertEquals('bar', $result1['foo']);
        $this->assertEquals('buzz', $result1['_request']['fizz']);
        $this->assertEquals('doThatThingYouDo', $result1['_request']['action']);

        // Asynchronous
        $result2 = $client->doThatThingOtherYouDoAsync(['fizz' => 'buzz'])->wait();
        $this->assertEquals('barbar', $result2['foofoo']);
        $this->assertEquals('doThatThingOtherYouDo', $result2['_request']['action']);
    }

    public function testCommandExceptionIsThrownWhenAnErrorOccurs()
    {
        $client = $this->getServiceClient([
            new BadResponseException(
                'Bad Response',
                $this->getMockForAbstractClass(RequestInterface::class),
                $this->getMockForAbstractClass(ResponseInterface::class)
            ),
        ]);

        $this->expectException(CommandException::class);
        $client->execute($client->getCommand('foo'));
    }

    public function testExecuteMultipleCommands()
    {
        // Set up commands to execute concurrently.
        $generateCommands = function () {
            yield new Command('capitalize', ['letter' => 'a']);
            yield new Command('capitalize', ['letter' => '2']);
            yield new Command('capitalize', ['letter' => 'z']);
        };

        // Setup a client with mock responses for the commands.
        // Note: the second one will be a failed request.
        $client = $this->getServiceClient([
            new Response(200, [], '{"letter":"A"}'),
            new BadResponseException(
                'Bad Response',
                $this->getMockForAbstractClass(RequestInterface::class),
                new Response(200, [], '{"error":"Not a letter"}')
            ),
            new Response(200, [], '{"letter":"Z"}'),
        ]);

        // Setup fulfilled/rejected callbacks, just to confirm they are called.
        $fulfilledFnCalled = false;
        $rejectedFnCalled = false;
        $options = [
            'fulfilled' => function () use (&$fulfilledFnCalled) {
                $fulfilledFnCalled = true;
            },
            'rejected' => function () use (&$rejectedFnCalled) {
                $rejectedFnCalled = true;
            },
        ];

        // Execute multiple commands.
        $results = $client->executeAll($generateCommands(), $options);

        // Make sure the callbacks were called
        $this->assertTrue($fulfilledFnCalled);
        $this->assertTrue($rejectedFnCalled);

        // Validate that the results are as expected.
        $this->assertCount(3, $results);
        $this->assertInstanceOf(Result::class, $results[0]);
        $this->assertEquals('A', $results[0]['letter']);
        $this->assertInstanceOf(CommandException::class, $results[1]);
        $this->assertStringContainsString(
            'Not a letter',
            (string) $results[1]->getResponse()->getBody()
        );
        $this->assertInstanceOf(Result::class, $results[2]);
        $this->assertEquals('Z', $results[2]['letter']);
    }

    public function testMultipleCommandsFailsForNonCommands()
    {
        $generateCommands = function () {
            yield 'foo';
        };

        $this->expectException(\InvalidArgumentException::class);

        $client = $this->getServiceClient([]);
        $client->executeAll($generateCommands());
    }
}
