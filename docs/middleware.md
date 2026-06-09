# Middleware

Middleware can be added to the service client or underlying HTTP client to customize different parts of the lifecycle.

Command middleware wraps commands before they are transformed into HTTP requests. HTTP middleware should be configured on the underlying Guzzle HTTP client instead.

Command handlers use this shape:

```php
callable(GuzzleHttp\Command\CommandInterface): GuzzleHttp\Promise\PromiseInterface
```

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

Use command middleware for behavior tied to service operations. Use Guzzle HTTP middleware for behavior tied to PSR-7 requests and responses.
