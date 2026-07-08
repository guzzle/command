# Async and Concurrency

This page explains asynchronous command execution and concurrent command pools
for Guzzle Command service clients. For promise chaining, waiting, cancellation,
and rejection behavior, see the
[Guzzle Promises quick start](https://github.com/guzzle/promises/blob/3.0/docs/promise-quick-start.md).

## Asynchronous Commands

Commands can be executed asynchronously using `executeAsync()`. This method
returns a `GuzzleHttp\Promise\PromiseInterface<GuzzleHttp\Command\ResultInterface, mixed>`.
See the [Guzzle Promises API](https://github.com/guzzle/promises/blob/3.0/docs/promise-api.md) for
promise helper details.

```php
use GuzzleHttp\Command\ResultInterface;

// Create and execute an asynchronous command.
$command = $client->getCommand('foo', ['baz' => 'bar']);
$promise = $client->executeAsync($command);

$promise->then(function (ResultInterface $result) {
    echo $result['fizz']; //> 'buzz'
})->wait();
```

Synchronous execution is equivalent to waiting on the asynchronous operation:

```php
$result = $promise->wait();

echo $result['fizz']; //> 'buzz'
```

Magic methods may also be used asynchronously by appending `Async` to the
operation name. For example, `fooAsync()` creates a `foo` command and executes
it asynchronously:

```php
$promise = $client->fooAsync(['baz' => 'bar']);
$result = $promise->wait();
```

If built-in execution fails, the promise is typically rejected with a
`GuzzleHttp\Command\Exception\CommandException`. When HTTP errors are enabled,
4xx and 5xx responses are represented by `CommandClientException` and
`CommandServerException`, respectively, when the underlying Guzzle exception
contains a response. Custom middleware and handlers may reject with other
values.

## Concurrent Commands

Use `executeAll()` or `executeAllAsync()` to execute multiple commands with a
concurrency limit. Both methods accept an array or iterator that yields
`CommandInterface` objects. If no concurrency option is provided, the default is
`25` commands at a time.

`executeAll()` waits for the pool to finish and returns an array keyed like the
input commands. Successful entries contain `ResultInterface` objects. Failed
entries contain the rejection reason, typically a `CommandException`. The method
does not throw merely because one command failed; each failure reason is stored
in the returned array unless the pool itself cannot be created or waited on.
Callback keys may be integers, strings, or `null`. Returned array keys follow
PHP array-key normalization; numeric-string keys may become integers, and `null`
keys are stored as an empty string.

```php
use GuzzleHttp\Command\ResultInterface;

$commands = [
    'first' => $client->getCommand('foo', ['baz' => 'bar']),
    'second' => $client->getCommand('foo', ['baz' => 'qux']),
];

$results = $client->executeAll($commands, [
    'concurrency' => 10,
    'fulfilled' => function (ResultInterface $result, $key) {
        // Called when one command succeeds.
    },
    'rejected' => function ($reason, $key) {
        // Called when one command fails.
    },
]);
```

`executeAllAsync()` returns a promise for the command pool instead of waiting
for it immediately. It resolves with `null` after all commands have settled; it
does not build a result array. Individual command results are delivered to the
`fulfilled` callback, and individual rejection reasons are delivered to the
`rejected` callback. Fulfilled and rejected callbacks may also declare the
aggregate promise as a third argument:

```php
use GuzzleHttp\Command\ResultInterface;
use GuzzleHttp\Promise\PromiseInterface;

$promise = $client->executeAllAsync($commands, [
    'concurrency' => 10,
    'fulfilled' => function (ResultInterface $result, $key, PromiseInterface $aggregate) {
        // Called when one command succeeds.
    },
    'rejected' => function ($reason, $key, PromiseInterface $aggregate) {
        // Called when one command fails.
    },
]);

$promise->wait();
```

The supported options are:

- `concurrency`: Maximum number of commands to execute at the same time. The
  default is `25`. This may be an integer or a callable. A callable receives the
  current number of pending commands and returns the current concurrency limit,
  allowing the limit to change while the pool is running.
- `fulfilled`: Callable invoked as `fulfilled($result, $key)` by `executeAll()`
  when an individual command succeeds. `executeAllAsync()` also passes the
  aggregate promise as a third argument.
- `rejected`: Callable invoked as `rejected($reason, $key)` by `executeAll()`
  when an individual command fails. `executeAllAsync()` also passes the
  aggregate promise as a third argument.

Choose a concurrency value that is appropriate for the remote service and your
application. Very large command lists should generally be streamed with an
iterator rather than built eagerly as a large array.

## Related

- [Service Clients](service-clients.md)
- [Executing Commands](executing-commands.md)
- [Middleware: Extending the Client](middleware-extending-the-client.md)
