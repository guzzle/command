# Service Clients

This library uses Guzzle and provides the foundations to create fully-featured
web service clients by abstracting Guzzle HTTP *requests* and *responses* into
higher-level *commands* and *results*. A *middleware* system, analogous to, but
separate from, the one in the HTTP layer may be used to customize client
behavior when preparing commands into requests and processing responses into
results.

## Commands

Key-value pair objects representing an operation of a web service. Commands
have a name and a set of parameters.

## Results

Key-value pair objects representing the processed result of executing an
operation of a web service.

## Service Clients

Service Clients are web service clients that implement the
`GuzzleHttp\Command\ServiceClientInterface` and use an underlying Guzzle HTTP
client (`GuzzleHttp\ClientInterface`) to communicate with the service. Service
clients create and execute *commands* (`GuzzleHttp\Command\CommandInterface`),
which encapsulate operations within the web service, including the operation
name and parameters. This library provides a generic implementation of a service
client: the `GuzzleHttp\Command\ServiceClient` class.

## Instantiating a Service Client

The provided service client implementation (`GuzzleHttp\Command\ServiceClient`)
can be instantiated by providing the following arguments:

1. A fully-configured Guzzle HTTP client that will be used to perform the
   underlying HTTP requests. That is, an instance of an object implementing
   `GuzzleHttp\ClientInterface` such as `new GuzzleHttp\Client()`.
1. A callable that transforms a Command into a Request. The callable is invoked
   as `callable(GuzzleHttp\Command\CommandInterface): Psr\Http\Message\RequestInterface`.
1. A callable that transforms a Response into a Result. The callable is invoked
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
use GuzzleHttp\UriTemplate\UriTemplate;
use GuzzleHttp\Utils;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

$client = new ServiceClient(
    new HttpClient(['base_uri' => 'https://api.example.com']),
    function (CommandInterface $command): RequestInterface {
        return new Request(
            'POST',
            UriTemplate::expand('/{command}', ['command' => $command->getName()]),
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
