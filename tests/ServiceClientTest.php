<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Command;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Command\Result;
use GuzzleHttp\Command\ServiceClient;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @covers \GuzzleHttp\Command\ServiceClient
 */
class ServiceClientTest extends TestCase
{
    private function getServiceClient(array $responses): ServiceClient
    {
        return new ServiceClient(
            new HttpClient([
                'handler' => new MockHandler($responses),
            ]),
            function (CommandInterface $command): Request {
                $data = $command->toArray();
                $data['action'] = $command->getName();

                return new Request('POST', '/', [], http_build_query($data));
            },
            function (ResponseInterface $response, RequestInterface $request): Result {
                $data = json_decode((string) $response->getBody(), true);
                parse_str((string) $request->getBody(), $data['_request']);

                return new Result($data);
            }
        );
    }

    public function testCanGetHttpClientAndHandlers(): void
    {
        $httpClient = new HttpClient();
        $handlers = new HandlerStack();
        $fn = function (): void {};
        $serviceClient = new ServiceClient($httpClient, $fn, $fn, $handlers);
        $this->assertSame($httpClient, $serviceClient->getHttpClient());
        $this->assertSame($handlers, $serviceClient->getHandlerStack());
    }

    public function testResponseTransformerMayDeclareFewerArguments(): void
    {
        $client = $this->getServiceClient([
            new Response(200, [], '{"foo":"bar"}'),
        ]);

        $result = $client->execute($client->getCommand('foo'));

        $this->assertSame('bar', $result['foo']);
    }

    public function testResponseTransformerReceivesCommand(): void
    {
        $receivedCommand = null;
        $client = new ServiceClient(
            new HttpClient([
                'handler' => new MockHandler([
                    new Response(200, [], '{}'),
                ]),
            ]),
            function (CommandInterface $command): RequestInterface {
                return new Request('POST', '/', [], $command->getName());
            },
            function (
                ResponseInterface $response,
                RequestInterface $request,
                CommandInterface $command
            ) use (&$receivedCommand): Result {
                $receivedCommand = $command;

                return new Result([
                    'command' => $command->getName(),
                    'request' => (string) $request->getBody(),
                    'status' => $response->getStatusCode(),
                ]);
            }
        );

        $command = $client->getCommand('foo');
        $result = $client->execute($command);

        $this->assertSame($command, $receivedCommand);
        $this->assertSame('foo', $result['command']);
        $this->assertSame('foo', $result['request']);
        $this->assertSame(200, $result['status']);
    }

    public function testMagicMethodExecutesCommandSynchronously(): void
    {
        $client = $this->getServiceClient([
            new Response(200, [], '{"foo":"bar"}'),
        ]);

        $result = $client->doThatThingYouDo(['fizz' => 'buzz']);

        $this->assertSame('bar', $result['foo']);
        $this->assertSame('buzz', $result['_request']['fizz']);
        $this->assertSame('doThatThingYouDo', $result['_request']['action']);
    }

    public function testMagicMethodExecutesCommandAsynchronously(): void
    {
        $client = $this->getServiceClient([
            new Response(200, [], '{"foofoo":"barbar"}'),
        ]);

        $result = $client->doThatThingOtherYouDoAsync(['fizz' => 'buzz'])->wait();

        $this->assertSame('barbar', $result['foofoo']);
        $this->assertSame('buzz', $result['_request']['fizz']);
        $this->assertSame('doThatThingOtherYouDo', $result['_request']['action']);
    }

    public function testMagicMethodUsesEmptyArgumentsWhenNoneArePassed(): void
    {
        $client = $this->getServiceClient([
            new Response(200, [], '{"ok":true}'),
        ]);

        $result = $client->listWidgets();

        $this->assertTrue($result['ok']);
        $this->assertSame('listWidgets', $result['_request']['action']);
    }

    public function testCommandExceptionIsThrownWhenAnErrorOccurs(): void
    {
        $client = $this->getServiceClient([
            new BadResponseException(
                'Bad Response',
                $this->createMock(RequestInterface::class),
                $this->createMock(ResponseInterface::class)
            ),
        ]);

        $this->expectException(CommandException::class);
        $client->execute($client->getCommand('foo'));
    }

    public function testExecuteMultipleCommands(): void
    {
        // Set up commands to execute concurrently.
        $generateCommands = function (): \Generator {
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
                $this->createMock(RequestInterface::class),
                new Response(200, [], '{"error":"Not a letter"}')
            ),
            new Response(200, [], '{"letter":"Z"}'),
        ]);

        // Setup fulfilled/rejected callbacks, just to confirm they are called.
        $fulfilledFnCalled = false;
        $rejectedFnCalled = false;
        $fulfilledArgs = [];
        $rejectedArgs = [];
        $options = [
            'fulfilled' => function (...$args) use (&$fulfilledFnCalled, &$fulfilledArgs): void {
                $fulfilledFnCalled = true;
                $fulfilledArgs = $args;
            },
            'rejected' => function (...$args) use (&$rejectedFnCalled, &$rejectedArgs): void {
                $rejectedFnCalled = true;
                $rejectedArgs = $args;
            },
        ];

        // Execute multiple commands.
        $results = $client->executeAll($generateCommands(), $options);

        // Make sure the callbacks were called
        $this->assertTrue($fulfilledFnCalled);
        $this->assertTrue($rejectedFnCalled);
        $this->assertCount(2, $fulfilledArgs);
        $this->assertInstanceOf(Result::class, $fulfilledArgs[0]);
        $this->assertContains($fulfilledArgs[1], [0, 2]);
        $this->assertCount(2, $rejectedArgs);
        $this->assertInstanceOf(CommandException::class, $rejectedArgs[0]);
        $this->assertSame(1, $rejectedArgs[1]);

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

    public function testExecuteAllNormalizesNullResultKeys(): void
    {
        $generateCommands = function (): \Generator {
            yield null => new Command('capitalize', ['letter' => 'a']);
        };

        $client = $this->getServiceClient([
            new Response(200, [], '{"letter":"A"}'),
        ]);

        $fulfilledKey = 'not-called';
        $results = $client->executeAll($generateCommands(), [
            'fulfilled' => function (Result $result, ?string $key) use (&$fulfilledKey): void {
                $fulfilledKey = $key;
            },
        ]);

        $this->assertNull($fulfilledKey);
        $this->assertArrayHasKey('', $results);
        $this->assertInstanceOf(Result::class, $results['']);
        $this->assertSame('A', $results['']['letter']);
    }

    public function testExecuteAllNormalizesNullResultKeysForRejectedCommands(): void
    {
        $generateCommands = function (): \Generator {
            yield null => new Command('capitalize', ['letter' => '2']);
        };

        $client = $this->getServiceClient([
            new BadResponseException(
                'Bad Response',
                $this->createMock(RequestInterface::class),
                new Response(200, [], '{"error":"Not a letter"}')
            ),
        ]);

        $rejectedKey = 'not-called';
        $results = $client->executeAll($generateCommands(), [
            'rejected' => function (CommandException $reason, ?string $key) use (&$rejectedKey): void {
                $rejectedKey = $key;
            },
        ]);

        $this->assertNull($rejectedKey);
        $this->assertArrayHasKey('', $results);
        $this->assertInstanceOf(CommandException::class, $results['']);
    }

    public function testMultipleCommandsFailsForNonCommands(): void
    {
        $generateCommands = function (): \Generator {
            yield 'foo';
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('got string');

        $client = $this->getServiceClient([]);
        $client->executeAll($generateCommands());
    }

    public function testExecuteAllRejectsSingleCommand(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('requires an iterable of commands');

        $client = $this->getServiceClient([]);
        $client->executeAll(new Command('capitalize', ['letter' => 'a']));
    }

    public function testExecuteAllAsyncRejectsSingleCommand(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('requires an iterable of commands');

        $client = $this->getServiceClient([]);
        $client->executeAllAsync(new Command('capitalize', ['letter' => 'a']));
    }

    public function testExecuteAllAsyncCallbacksReceiveAggregatePromise(): void
    {
        $client = $this->getServiceClient([
            new Response(200, [], '{"letter":"A"}'),
            new BadResponseException(
                'Bad Response',
                $this->createMock(RequestInterface::class),
                new Response(200, [], '{"error":"Not a letter"}')
            ),
        ]);

        $commands = [
            'success' => new Command('capitalize', ['letter' => 'a']),
            'failure' => new Command('capitalize', ['letter' => '2']),
        ];

        $fulfilledKey = null;
        $fulfilledPromise = null;
        $rejectedKey = null;
        $rejectedPromise = null;
        $promise = $client->executeAllAsync($commands, [
            'fulfilled' => function (Result $result, $key, PromiseInterface $aggregate) use (&$fulfilledKey, &$fulfilledPromise): void {
                $fulfilledKey = $key;
                $fulfilledPromise = $aggregate;
            },
            'rejected' => function ($reason, $key, PromiseInterface $aggregate) use (&$rejectedKey, &$rejectedPromise): void {
                $this->assertInstanceOf(CommandException::class, $reason);
                $rejectedKey = $key;
                $rejectedPromise = $aggregate;
            },
        ]);

        $promise->wait();

        $this->assertSame('success', $fulfilledKey);
        $this->assertSame($promise, $fulfilledPromise);
        $this->assertSame('failure', $rejectedKey);
        $this->assertSame($promise, $rejectedPromise);
    }
}
