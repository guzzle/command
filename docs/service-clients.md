# Service Clients

Guzzle Command helps create web service clients by mapping high-level commands
to Guzzle HTTP requests and mapping HTTP responses back to command results. It
is useful when an application should call named service operations instead of
constructing every HTTP request directly.

Command middleware can customize the command-to-result lifecycle. This is
separate from Guzzle HTTP middleware, which customizes the request-to-response
lifecycle on the underlying HTTP client.

## Core Concepts

A *service* is the remote API your client calls. In Guzzle Command, a service is
represented by a service client that knows how to turn operation names and
parameters into HTTP requests.

A *command* is an object that represents one service operation. It has an
operation name, such as `createUser`, and a set of parameters for that
operation.

A *result* is an object that represents the processed response from executing a
command. Results usually contain decoded response data rather than raw PSR-7
responses.

## Commands

Commands are key-value pair objects representing a single operation of a web
service. Commands have a name and a set of parameters.

## Results

Results are key-value pair objects representing the processed result of
executing an operation of a web service.

## Service Clients

Service Clients are web service clients that implement the
`GuzzleHttp\Command\ServiceClientInterface` and use an underlying Guzzle HTTP
client (`GuzzleHttp\ClientInterface`) to communicate with the service. Service
clients create and execute commands (`GuzzleHttp\Command\CommandInterface`),
which encapsulate operations within the web service, including the operation
name and parameters. This library provides a generic implementation of a service
client: the `GuzzleHttp\Command\ServiceClient` class.

## Instantiating a Service Client

The provided service client implementation (`GuzzleHttp\Command\ServiceClient`)
can be instantiated by providing the following arguments:

1. A fully-configured Guzzle HTTP client that will be used to perform the
   underlying HTTP requests. That is, an instance of an object implementing
   `GuzzleHttp\ClientInterface` such as `new GuzzleHttp\Client()`.
1. A callable that transforms a command into a request. The callable is invoked
   as `callable(GuzzleHttp\Command\CommandInterface): Psr\Http\Message\RequestInterface`.
1. A callable that transforms a response into a result. The callable is invoked
   as `callable(Psr\Http\Message\ResponseInterface, Psr\Http\Message\RequestInterface, GuzzleHttp\Command\CommandInterface): GuzzleHttp\Command\ResultInterface`.
1. Optionally, a Guzzle HandlerStack (`GuzzleHttp\HandlerStack`), which can be
   used to add command-level middleware to the service client.

Below is an example configured to send and receive JSON payloads:

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
            ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
            Utils::jsonEncode($command->toArray())
        );
    },
    function (
        ResponseInterface $response,
        RequestInterface $request,
        CommandInterface $command
    ): ResultInterface {
        return new Result(
            Utils::jsonDecode((string) $response->getBody(), true)
        );
    }
);
```

## Transformers

The command-to-request transformer adapts your service operation model to HTTP.
It can choose the HTTP method, URI, headers, and body using the command name and
parameters:

```php
use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Utils;
use Psr\Http\Message\RequestInterface;

$commandToRequest = function (CommandInterface $command): RequestInterface {
    $body = Utils::jsonEncode($command->toArray());
    $path = '/commands/' . rawurlencode($command->getName());

    return new Request(
        'POST',
        $path,
        ['Content-Type' => 'application/json'],
        $body
    );
};
```

The response-to-result transformer adapts the HTTP response to your SDK result
shape. It receives the response, request, and original command:

```php
use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Result;
use GuzzleHttp\Command\ResultInterface;
use GuzzleHttp\Utils;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

$responseToResult = function (
    ResponseInterface $response,
    RequestInterface $request,
    CommandInterface $command
): ResultInterface {
    return new Result([
        'operation' => $command->getName(),
        'statusCode' => $response->getStatusCode(),
        'data' => Utils::jsonDecode((string) $response->getBody(), true),
    ]);
};
```

## Command Middleware and HTTP Middleware

Command middleware is added to the service client's command handler stack. It
receives a command, may inspect or modify command parameters, and returns a
promise that resolves to a `ResultInterface`.

HTTP middleware is added to the underlying Guzzle HTTP client's handler stack.
It receives PSR-7 requests after a command has been transformed and before the
request is sent.

Use command middleware for operation-level concerns, such as adding command
defaults or inspecting results. Use HTTP middleware for transport-level concerns,
such as request signing, retries, or logging raw HTTP messages.

## Related

- [Executing Commands](executing-commands.md)
- [Async and Concurrency](async-and-concurrency.md)
- [Middleware: Extending the Client](middleware-extending-the-client.md)
