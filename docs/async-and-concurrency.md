# Async and Concurrency

## Asynchronous Commands

Commands can be executed asynchronously using `executeAsync()`. This method
returns a `GuzzleHttp\Promise\PromiseInterface<GuzzleHttp\Command\ResultInterface, mixed>`.

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
operation name. For example, `fooAsync()` creates a `foo` command and executes it
asynchronously:

```php
$promise = $client->fooAsync(['baz' => 'bar']);
$result = $promise->wait();
```

If built-in execution fails, the promise is typically rejected with a
`GuzzleHttp\Command\Exception\CommandException`. When HTTP errors are enabled,
4xx and 5xx responses are represented by `CommandClientException` and
`CommandServerException`, respectively, when the underlying Guzzle exception
contains a response. Custom middleware and handlers may reject with other values.

## Concurrent Requests

Use `executeAll()` or `executeAllAsync()` to execute multiple commands with a
fixed concurrency limit. Both methods accept an array or iterator that yields
`CommandInterface` objects.

`executeAll()` waits for the pool to finish and returns an array keyed like the
input commands. Successful entries contain results. Failed entries contain the
rejection reason, typically a `CommandException`. Callback keys may be integers,
strings, or `null`. Returned array keys follow PHP array-key normalization;
numeric-string keys may become integers, and `null` keys are stored as an empty
string.

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

`executeAllAsync()` returns a promise for the command pool instead of waiting for
it immediately. Fulfilled and rejected callbacks may also declare the aggregate
promise as a third argument:

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

* `concurrency`: Maximum number of commands to execute at the same time. The
  default is `25`.
* `fulfilled`: Callable invoked as `fulfilled($result, $key)` by `executeAll()`
  when an individual command succeeds. `executeAllAsync()` also passes the
  aggregate promise as a third argument.
* `rejected`: Callable invoked as `rejected($reason, $key)` by `executeAll()`
  when an individual command fails. `executeAllAsync()` also passes the aggregate
  promise as a third argument.

Choose a concurrency value that is appropriate for the remote service and your
application. Very large command lists should generally be streamed with an
iterator rather than built eagerly as a large array.
