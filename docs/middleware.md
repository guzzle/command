# Middleware: Extending the Client

Middleware can be added to the service client or underlying HTTP client to
implement additional behavior and customize the ``Command``-to-``Result`` and
``Request``-to-``Response`` lifecycles, respectively.

Command middleware is added to the service client's handler stack and wraps
commands before they are transformed into HTTP requests. Command handlers use the
shape `callable(GuzzleHttp\Command\CommandInterface): GuzzleHttp\Promise\PromiseInterface<GuzzleHttp\Command\ResultInterface, mixed>`. HTTP middleware should be configured on the underlying Guzzle HTTP client instead.

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
