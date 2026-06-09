Guzzle Command Upgrade Guide
============================

2.0 from 1.x
------------

Guzzle Command 2.0 is a major release that enables strict types, raises the
minimum PHP version, and updates the Guzzle dependency stack. Applications that
only use the `ServiceClientInterface` API should usually need small changes.
Applications that pass request options through commands, use promises directly,
or provide custom callbacks need closer review.

#### PHP Version and Dependencies

Guzzle Command 2.0 requires PHP `^7.4 || ^8.0`. Guzzle Command 1.x supported
PHP `^7.2.5 || ^8.0`.

Guzzle Command 2.0 also requires
[Guzzle 8.x](https://github.com/guzzle/guzzle/blob/8.0/UPGRADING.md),
[Guzzle Promises 3.x](https://github.com/guzzle/promises/blob/3.0/UPGRADING.md),
and [Guzzle PSR-7 3.x](https://github.com/guzzle/psr7/blob/3.0/UPGRADING.md).
Guzzle Command 1.x supported Guzzle `^7.11`, Guzzle Promises `^2.5`, and Guzzle
PSR-7 `^2.11`.

Guzzle Command 2.0 now requires `psr/http-client:^1.0` directly.

If your application still supports PHP 7.2 or 7.3, or still uses the Guzzle 7
dependency stack, continue using Guzzle Command 1.x until your minimum PHP and
dependency versions are raised.

#### Strict Types and Extension Points

Guzzle Command source and test files now declare strict types. This mostly
affects calls made by Guzzle Command into extension points, including command
middleware, command-to-request transformers, response-to-result transformers,
and `executeAll()` callbacks. Custom code does not become strict unless it also
declares strict types, but scalar arguments passed from strict Guzzle Command
files are no longer weakly coerced for typed callback parameters.

Review custom callbacks that declare scalar parameter types. In particular,
`executeAll()` callback keys can be integers, strings, or `null` depending on the
keys yielded by the command iterable.

#### Native Signatures

Guzzle Command 2.0 adds native parameter and return types to public interfaces and
classes. Custom implementations of `CommandInterface`, `ServiceClientInterface`,
or `ToArrayInterface` must update method signatures to remain compatible.

Classes extending package classes should also update overridden method signatures.

#### Per-command HTTP Options

`GuzzleHttp\Command\ServiceClient` still reserves the `@http` command parameter
for per-command Guzzle request options. These options are passed to the
underlying Guzzle client when the command is executed.

Guzzle 8 changes some request option behavior, especially stricter `proxy`
option validation and the extra request argument passed to `on_headers`
callbacks.

#### Asynchronous Commands

`executeAsync()` and `executeAllAsync()` now return promises from Guzzle Promises
3.x. Code using Guzzle Promises directly should account for its 3.0 behavior and
signature changes.

#### Generic Promise And Structured PHPDoc Types

Guzzle Command's async service client APIs and command handler stack annotations
now use generic `PromiseInterface<ResultInterface, mixed>` PHPDoc types. This is
a static-analysis-only change and does not alter runtime behavior, but projects
with stricter static analysis may see new or different diagnostics.

Code using unparameterized promise types continues to work. If your project
implements `ServiceClientInterface`, provides custom command middleware, or
documents reusable command handlers, you may need to update your PHPDoc
annotations to include promise fulfillment and rejection types.

Transformer, `executeAll()`, and `executeAllAsync()` option PHPDoc now uses
structured array and callable shapes. This does not change runtime behavior, but
stricter static analysis may now report invalid option keys, invalid option value
types, or callback annotations that were previously hidden behind loose `array`
or `callable` PHPDoc.

`executeAll()` callback annotations include the result or rejection reason and
the command key. `executeAllAsync()` callback annotations include the same first
two arguments plus the aggregate promise as a third argument. Lower-arity
userland callbacks continue to work at runtime when PHP accepts them.

1.0 from 0.8
------------

Guzzle Command 1.0 completed the migration that started in 0.9 from the Guzzle 5
event model to Guzzle 6, PSR-7 messages, promises, and command middleware. If
you are upgrading directly from 0.8, review the 0.9 changes as part of the 1.0
upgrade.

#### PHP Version and Dependencies

Guzzle Command 1.0 requires PHP 5.5 or higher and Guzzle 6.2 or higher. Guzzle
Command 0.8 supported PHP 5.4 and Guzzle 5.

Guzzle Command 0.9 introduced the Guzzle 6 dependency stack with
`guzzlehttp/promises` and `guzzlehttp/psr7`. Version 1.0 keeps that stack and
updates the supported dependency versions.

If your application still uses Guzzle 5, continue using Guzzle Command 0.8.

#### Events, Subscribers, and Middleware

The Guzzle 5 command event system was removed in 0.9. Code that listened for
`init`, `prepared`, or `process` events, or that used the built-in subscribers,
must move that behavior to command middleware.

Command middleware is added to a `GuzzleHttp\HandlerStack` owned by the service
client. Middleware wraps `GuzzleHttp\Command\CommandInterface` objects and
resolves to `GuzzleHttp\Command\ResultInterface` objects.

HTTP middleware should be configured separately on the underlying Guzzle HTTP
client.

#### Service Client Construction

The old `AbstractClient`, `CommandTransaction`, and command event classes were
removed in 0.9. Use `GuzzleHttp\Command\ServiceClient` directly or adapt custom
clients to `GuzzleHttp\Command\ServiceClientInterface`.

`ServiceClient` is constructed with:

- A Guzzle HTTP client.
- A callable that converts a command to a PSR-7 request.
- A callable that converts a PSR-7 response to a command result.
- An optional command `HandlerStack` for command middleware.

#### PSR-7 Requests and Responses

Command-to-request transformers must return `Psr\Http\Message\RequestInterface`
instances. Response-to-result transformers receive PSR-7 responses instead of
Guzzle 5 response objects.

In 1.0, response-to-result transformers receive the command as a third argument:

```php
use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\ResultInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

$transform = function (
    ResponseInterface $response,
    RequestInterface $request,
    CommandInterface $command
): ResultInterface {
    // Build and return a ResultInterface implementation.
};
```

#### Results and Async Execution

Result data is represented by `GuzzleHttp\Command\ResultInterface`. The package
provides `GuzzleHttp\Command\Result` as a basic array-like implementation.

Asynchronous execution uses `guzzlehttp/promises` promises. Future-style results
from the Guzzle 5/RingPHP stack are no longer used.

Use `executeAsync()` or the `Async` magic method suffix to execute a command
asynchronously:

```php
$promise = $client->executeAsync($command);
$result = $promise->wait();

$result = $client->getUserAsync(['id' => '123'])->wait();
```

#### Concurrent Commands

The old command iterator and transaction helpers were removed in 0.9. Use
`executeAll()` or `executeAllAsync()` with an array or iterator of
`CommandInterface` objects when executing multiple commands.
