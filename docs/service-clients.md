# Service Clients

Service clients are web service clients that implement `GuzzleHttp\Command\ServiceClientInterface` and use an underlying Guzzle HTTP client to communicate with a service.

A service client turns commands into PSR-7 requests, sends them through Guzzle, and turns responses into result objects.

## Concepts

- A command is a key-value object representing one service operation.
- A result is a key-value object representing the processed response from an operation.
- Command middleware wraps commands before they are converted into HTTP requests.
- HTTP middleware belongs on the underlying Guzzle HTTP client.

## Creating a Service Client

`GuzzleHttp\Command\ServiceClient` accepts:

- a configured `GuzzleHttp\ClientInterface`
- a callable that converts a command into a PSR-7 request
- a callable that converts a response into a result
- optionally, a command handler stack

```php
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Result;
use GuzzleHttp\Command\ResultInterface;
use GuzzleHttp\Command\ServiceClient;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Utils;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

$client = new ServiceClient(
    new HttpClient(['base_uri' => 'https://api.example.com']),
    function (CommandInterface $command): RequestInterface {
        return new Request(
            'POST',
            '/' . rawurlencode($command->getName()),
            ['Content-Type' => 'application/json'],
            Utils::jsonEncode($command->toArray())
        );
    },
    function (
        ResponseInterface $response,
        RequestInterface $request,
        CommandInterface $command
    ): ResultInterface {
        return new Result(Utils::jsonDecode((string) $response->getBody(), true));
    }
);
```
