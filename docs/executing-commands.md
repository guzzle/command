# Executing Commands

Service clients create command objects using the ``getCommand()`` method.

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
`GuzzleHttp\Command\ResultInterface`. Result objects are `ArrayAccess`-ible and
contain the data parsed from HTTP response.

Service clients have magic methods that act as shortcuts to executing commands
by name without having to create the ``Command`` object in a separate step
before executing it.

```php
$result = $client->foo(['baz' => 'bar']);
```

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
The `@http` value must be an array of Guzzle request options. Be especially
careful with options that affect the target URI, proxy, TLS verification,
headers, body, response sink, redirects, or timeouts.

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

When setting per-command HTTP options intentionally, only expose and validate the
specific options your application needs:

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
