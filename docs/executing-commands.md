# Executing Commands

Service clients create command objects with `getCommand()` and execute them with `execute()`.

```php
$command = $client->getCommand('createUser', ['name' => 'Ada']);
$result = $client->execute($command);
```

Result objects implement `GuzzleHttp\Command\ResultInterface`, are `ArrayAccess`-ible, and contain data parsed from the HTTP response.

## Magic Methods

Service clients have magic methods that create and execute commands by name.

```php
$result = $client->createUser(['name' => 'Ada']);
```

## Per-Command HTTP Options

`GuzzleHttp\Command\ServiceClient` reserves the `@http` command parameter for per-command Guzzle request options. During execution, the service client removes `@http` from the command and passes the array to the underlying Guzzle HTTP client.

```php
use GuzzleHttp\RequestOptions;

$command = $client->getCommand('createUser', [
    'name' => 'Ada',
    '@http' => [
        RequestOptions::TIMEOUT => 2.0,
    ],
]);

$result = $client->execute($command);
```

Treat `@http` as a reserved control key, not as an operation parameter. Do not pass untrusted input directly into command arguments without filtering reserved keys first.

```php
if (array_key_exists('@http', $input)) {
    throw new InvalidArgumentException('"@http" is reserved.');
}
```

Be especially careful with request options that affect the target URI, proxy, TLS verification, headers, body, response sink, redirects, or timeouts.
