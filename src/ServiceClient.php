<?php

declare(strict_types=1);

namespace GuzzleHttp\Command;

use GuzzleHttp\ClientInterface as HttpClient;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * The Guzzle ServiceClient serves as the foundation for creating web service
 * clients that interact with RPC-style APIs.
 */
class ServiceClient implements ServiceClientInterface
{
    private HttpClient $httpClient;

    /** @var HandlerStack<callable(CommandInterface): PromiseInterface<ResultInterface, mixed>> */
    private HandlerStack $handlerStack;

    /** @var callable(CommandInterface): RequestInterface */
    private $commandToRequestTransformer;

    /** @var callable(ResponseInterface, RequestInterface, CommandInterface): ResultInterface */
    private $responseToResultTransformer;

    /**
     * Instantiates a Guzzle ServiceClient for making requests to a web service.
     *
     * @param HttpClient                                                                              $httpClient                  A fully-configured Guzzle HTTP client that will be used to perform the underlying HTTP requests.
     * @param callable(CommandInterface): RequestInterface                                            $commandToRequestTransformer A callable that transforms a Command into a Request.
     * @param callable(ResponseInterface, RequestInterface, CommandInterface): ResultInterface        $responseToResultTransformer A callable that transforms a Response into a Result.
     * @param HandlerStack<callable(CommandInterface): PromiseInterface<ResultInterface, mixed>>|null $commandHandlerStack         A Guzzle HandlerStack, which can be used to add command-level middleware to the service client.
     */
    public function __construct(
        HttpClient $httpClient,
        callable $commandToRequestTransformer,
        callable $responseToResultTransformer,
        ?HandlerStack $commandHandlerStack = null
    ) {
        $this->httpClient = $httpClient;
        $this->commandToRequestTransformer = $commandToRequestTransformer;
        $this->responseToResultTransformer = $responseToResultTransformer;
        /** @var HandlerStack<callable(CommandInterface): PromiseInterface<ResultInterface, mixed>> $handlerStack */
        $handlerStack = $commandHandlerStack ?: new HandlerStack();
        $this->handlerStack = $handlerStack;
        $this->handlerStack->setHandler($this->createCommandHandler());
    }

    public function getHttpClient(): HttpClient
    {
        return $this->httpClient;
    }

    /**
     * @return HandlerStack<callable(CommandInterface): PromiseInterface<ResultInterface, mixed>>
     */
    public function getHandlerStack(): HandlerStack
    {
        return $this->handlerStack;
    }

    public function getCommand(string $name, array $params = []): CommandInterface
    {
        return new Command($name, $params, clone $this->handlerStack);
    }

    public function execute(CommandInterface $command): ResultInterface
    {
        return $this->executeAsync($command)->wait();
    }

    /**
     * @return PromiseInterface<ResultInterface, mixed>
     */
    public function executeAsync(CommandInterface $command): PromiseInterface
    {
        $stack = $command->getHandlerStack() ?: $this->handlerStack;

        /** @var callable(CommandInterface): PromiseInterface<ResultInterface, mixed> $handler */
        $handler = $stack->resolve();

        return $handler($command);
    }

    /**
     * Executes multiple commands synchronously.
     *
     * Numeric-string command keys may become integer keys before callbacks receive them. Null keys are stored as an empty string in the returned result array.
     *
     * @param array{
     *     concurrency?: int|(callable(int): int),
     *     fulfilled?: callable(ResultInterface, int|string|null): mixed,
     *     rejected?: callable(mixed, int|string|null): mixed
     * } $options
     *
     * @return array<array-key, mixed>
     */
    public function executeAll(iterable $commands, array $options = []): array
    {
        $fulfilled = $options['fulfilled'] ?? null;
        $rejected = $options['rejected'] ?? null;

        // Modify provided callbacks to track results.
        $results = [];
        $options['fulfilled'] = function ($v, $k) use (&$results, $fulfilled): void {
            if ($fulfilled !== null) {
                /** @var ResultInterface $v */
                $fulfilled($v, $k);
            }
            $resultKey = $k === null ? '' : $k;
            $results[$resultKey] = $v;
        };
        $options['rejected'] = function ($v, $k) use (&$results, $rejected): void {
            if ($rejected !== null) {
                $rejected($v, $k);
            }
            $resultKey = $k === null ? '' : $k;
            $results[$resultKey] = $v;
        };

        // Execute multiple commands synchronously, then sort and return the results.
        return $this->executeAllAsync($commands, $options)
            ->then(function () use (&$results): array {
                ksort($results);

                return $results;
            })
            ->wait();
    }

    /**
     * @param array{
     *     concurrency?: int|(callable(int): int),
     *     fulfilled?: callable(ResultInterface, int|string|null, PromiseInterface<mixed, mixed>): mixed,
     *     rejected?: callable(mixed, int|string|null, PromiseInterface<mixed, mixed>): mixed
     * } $options
     *
     * @return PromiseInterface<mixed, mixed>
     */
    public function executeAllAsync(iterable $commands, array $options = []): PromiseInterface
    {
        // Apply default concurrency.
        if (!isset($options['concurrency'])) {
            $options['concurrency'] = 25;
        }

        if (!\is_iterable($commands)) {
            \trigger_deprecation(
                'guzzlehttp/command',
                '1.5',
                'Passing a non-iterable command collection to %s::executeAll() or %s::executeAllAsync() is deprecated; guzzlehttp/command 2.0 will require an iterable.',
                __CLASS__,
                __CLASS__
            );

            $commands = [$commands];
        }

        // Convert the iterator of commands to a generator of promises.
        $commands = Promise\Create::iterFor($commands);
        $promises = function () use ($commands): \Generator {
            foreach ($commands as $key => $command) {
                if (!$command instanceof CommandInterface) {
                    throw new \InvalidArgumentException('The iterator must '
                        .'yield instances of '.CommandInterface::class);
                }
                yield $key => $this->executeAsync($command);
            }
        };

        // Execute the commands using a pool.
        return (new Promise\EachPromise($promises(), $options))->promise();
    }

    /**
     * Creates and executes a command for an operation by name.
     *
     * @param string $name Name of the command to execute.
     * @param array  $args Arguments to pass to the getCommand method.
     *
     * @return ResultInterface|PromiseInterface<ResultInterface, mixed>
     *
     * @see ServiceClientInterface::getCommand
     */
    public function __call(string $name, array $args)
    {
        $args = isset($args[0]) ? $args[0] : [];
        if (substr($name, -5) === 'Async') {
            $command = $this->getCommand(substr($name, 0, -5), $args);

            return $this->executeAsync($command);
        }

        return $this->execute($this->getCommand($name, $args));
    }

    /**
     * Defines the main handler for commands that uses the HTTP client.
     *
     * @return callable(CommandInterface): PromiseInterface<ResultInterface, mixed>
     */
    private function createCommandHandler(): callable
    {
        return function (CommandInterface $command): PromiseInterface {
            return Promise\Coroutine::of(function () use ($command): \Generator {
                // Prepare the HTTP options.
                $opts = $command['@http'] ?: [];
                unset($command['@http']);

                try {
                    // Prepare the request from the command and send it.
                    $request = $this->transformCommandToRequest($command);
                    $promise = $this->httpClient->sendAsync($request, $opts);

                    // Create a result from the response.
                    $response = (yield $promise);
                    yield $this->transformResponseToResult($response, $request, $command);
                } catch (\Exception $e) {
                    throw CommandException::fromPrevious($command, $e);
                }
            });
        };
    }

    /**
     * Transforms a Command object into a Request object.
     */
    private function transformCommandToRequest(CommandInterface $command): RequestInterface
    {
        $transform = $this->commandToRequestTransformer;

        return $transform($command);
    }

    /**
     * Transforms a Response object, also using data from the Request object,
     * into a Result object.
     */
    private function transformResponseToResult(
        ResponseInterface $response,
        RequestInterface $request,
        CommandInterface $command
    ): ResultInterface {
        $transform = $this->responseToResultTransformer;

        return $transform($response, $request, $command);
    }
}
