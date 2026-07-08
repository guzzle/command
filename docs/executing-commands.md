# Executing Commands

This page covers creating command objects, executing them synchronously, using
magic operation methods, working with command and result collections, and
passing per-command HTTP options to the underlying Guzzle client.

Service clients create command objects using the `getCommand()` method.

```php
$commandName = 'foo';
$arguments = ['baz' => 'bar'];
$command = $client->getCommand($commandName, $arguments);
```

After creating a command, you may execute the command using the `execute()`
method of the client.

```php
$result = $client->execute($command);
```

The result of executing a command will be an instance of an object implementing
`GuzzleHttp\Command\ResultInterface`. Results are array-like objects that
contain the data parsed from the HTTP response by the service client's
response-to-result transformer.

Service clients have magic methods that act as shortcuts to executing commands
by name without having to create the `Command` object in a separate step before
executing it.

```php
$result = $client->foo(['baz' => 'bar']);
```

## Command and Result Data

Commands and results implement `ArrayAccess`, `Countable`, `IteratorAggregate`,
and `GuzzleHttp\Command\ToArrayInterface`.

Use array access to read, write, and remove values:

```php
$command = $client->getCommand('foo', ['baz' => 'bar']);

$command['baz'] = 'qux';
unset($command['unused']);

$result = $client->execute($command);
echo $result['fizz'];
```

Reading a missing key returns `null`. For commands, use `hasParam()` when you
need to distinguish a missing parameter from a parameter whose value is `null`.

Use `count()` to count stored values, iterate with `foreach`, and call
`toArray()` to retrieve the underlying array:

```php
foreach ($result as $name => $value) {
    // Inspect result values.
}

$data = $result->toArray();
$total = count($result);
```

Commands also provide `hasParam()` to test for a parameter by key, including
parameters set to `null`:

```php
if ($command->hasParam('baz')) {
    // The command contains the baz parameter.
}
```

For both commands and results, a `null` array key is normalized to an empty
string when reading, writing, unsetting, or checking values.

## Per-Command HTTP Options

`GuzzleHttp\Command\ServiceClient` reserves the `@http` command parameter for
per-command Guzzle request options. When a command is executed, the service
client reads `$command['@http']`, removes it from the command, transforms the
remaining command data into a PSR-7 request, and passes the `@http` array to the
underlying Guzzle HTTP client.

This is intended for trusted application code that needs to adjust transport
behavior for a single command, such as setting a shorter timeout. Treat `@http`
as a reserved control key, not as an operation parameter. Do not pass untrusted
input directly into command arguments without filtering it first. If external
input can include `@http`, that input may be able to influence the underlying
HTTP request or transfer depending on the configured Guzzle client and handler.
The `@http` value must be an array of [Guzzle request
options](https://github.com/guzzle/guzzle/blob/8.0/docs/request-options.md). Be
especially careful with options that affect the target URI, proxy, TLS
verification, headers, body, response sink, redirects, or timeouts.

Build command arguments from an allowlist of expected operation parameters, or
explicitly reject reserved keys such as `@http` before creating commands:

```php
if (array_key_exists('@http', $input)) {
    throw new InvalidArgumentException('"@http" is reserved.');
}

$command = $client->getCommand('foo', [
    'baz' => (string) $input['baz'],
]);
```

When setting per-command HTTP options intentionally, only expose and validate
the specific options your application needs:

```php
use GuzzleHttp\RequestOptions;

$command = $client->getCommand('foo', [
    'baz' => 'bar',
    '@http' => [
        RequestOptions::CONNECT_TIMEOUT => 1.0,
        RequestOptions::TIMEOUT => 2.0,
    ],
]);

$result = $client->execute($command);
```

Because `@http` is removed during execution, create a new command if you need to
execute the same operation again with the same per-command HTTP options.

## Related

- [Service Clients](service-clients.md)
- [Async and Concurrency](async-and-concurrency.md)
- [Middleware: Extending the Client](middleware-extending-the-client.md)
