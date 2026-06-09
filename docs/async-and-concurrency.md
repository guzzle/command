# Async and Concurrency

Commands can be executed asynchronously and in batches with a fixed concurrency limit.

## Asynchronous Commands

Use `executeAsync()` to execute a command asynchronously. It returns a `GuzzleHttp\Promise\PromiseInterface<GuzzleHttp\Command\ResultInterface, mixed>`.

```php
$command = $client->getCommand('createUser', ['name' => 'Ada']);
$promise = $client->executeAsync($command);

$result = $promise->wait();
```

Magic methods may also be used asynchronously by appending `Async` to the operation name.

```php
$promise = $client->createUserAsync(['name' => 'Ada']);
$result = $promise->wait();
```

If built-in execution fails, the promise is typically rejected with a `GuzzleHttp\Command\Exception\CommandException`. Custom middleware and handlers may reject with other values.

## Concurrent Requests

Use `executeAll()` or `executeAllAsync()` to execute multiple commands with a fixed concurrency limit.

```php
$commands = [
    'first' => $client->getCommand('createUser', ['name' => 'Ada']),
    'second' => $client->getCommand('createUser', ['name' => 'Grace']),
];

$results = $client->executeAll($commands, [
    'concurrency' => 10,
]);
```

The supported options are:

- `concurrency`: maximum number of commands to execute at the same time. The default is `25`.
- `fulfilled`: callable invoked when an individual command succeeds.
- `rejected`: callable invoked when an individual command fails.

Choose a concurrency value that is appropriate for the remote service and your application. Very large command lists should generally be streamed with an iterator rather than built eagerly as a large array.
