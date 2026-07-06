# Middleware: Extending the Client

Middleware can be added to the service client or underlying HTTP client to
implement additional behavior and customize the `Command`-to-`Result` and
`Request`-to-`Response` lifecycles, respectively.

Command middleware is added to the service client's handler stack and wraps
commands before they are transformed into HTTP requests. Command handlers use the
shape `callable(GuzzleHttp\Command\CommandInterface): GuzzleHttp\Promise\PromiseInterface<GuzzleHttp\Command\ResultInterface, mixed>`. HTTP middleware should be configured on the underlying Guzzle HTTP client instead.

The service client's command stack is separate from the underlying Guzzle HTTP
client stack:

- Command middleware receives commands and resolves to results.
- HTTP middleware receives PSR-7 requests and resolves to PSR-7 responses.
- Use [Guzzle HTTP middleware](https://github.com/guzzle/guzzle/blob/8.0/docs/middleware.md) for transport behavior such as retries, signing, logging, and request/response inspection.

## Adding Command Middleware

```php
use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\RequestOptions;

$client->getHandlerStack()->push(function (callable $handler) {
    return function (CommandInterface $command) use ($handler) {
        $http = $command['@http'] ?: [];
        $http[RequestOptions::TIMEOUT] = 2.0;
        $command['@http'] = $http;

        return $handler($command);
    };
});
```

## Command Stack Lifecycle

`ServiceClient::getCommand()` clones the service client's current command
handler stack into the returned command. Middleware added to the service client
after a command has been created does not affect that existing command.

```php
$first = $client->getCommand('foo');

$client->getHandlerStack()->push($middleware);

$second = $client->getCommand('foo');

// $first uses the stack captured before $middleware was added.
// $second uses the stack that includes $middleware.
```

When `executeAsync()` runs, it resolves the command's handler stack. If a custom
`CommandInterface` returns `null` from `getHandlerStack()`, `executeAsync()`
falls back to the service client's current command handler stack.

## Related

- [Service Clients](service-clients.md)
- [Executing Commands](executing-commands.md)
- [Async and Concurrency](async-and-concurrency.md)
