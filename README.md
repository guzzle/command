# Guzzle Commands

`guzzlehttp/command` provides the foundation for building command-based web service clients on top of Guzzle. A command represents one service operation, and a result represents the processed response from that operation.

Use this package when you are building an SDK-style client with named operations such as `listUsers()` or `createOrder()`. If you only need to send ordinary HTTP requests, install [`guzzlehttp/guzzle`](https://github.com/guzzle/guzzle) instead.

## Installation

```bash
composer require guzzlehttp/command
```

## Quick Start

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

$result = $client->createUser(['name' => 'Ada']);
```

The service client can also execute commands asynchronously and run many commands with a fixed concurrency limit.

## Documentation

- [Full documentation](docs/index.md)
- [Service clients](docs/index.md#service-clients)
- [Executing commands](docs/index.md#executing-commands)
- [Asynchronous commands](docs/index.md#asynchronous-commands)
- [Concurrent requests](docs/index.md#concurrent-requests)
- [Middleware](docs/index.md#middleware-extending-the-client)
- [Upgrade guide](UPGRADING.md)

## Version Guidance

| Version | Status       | PHP Version  |
|---------|--------------|--------------|
| 2.x     | Experimental | >=7.4,<8.6   |
| 1.x     | Latest       | >=7.2.5,<8.6 |

## Security

If you discover a security vulnerability within this package, please send an email to security@tidelift.com. All security vulnerabilities will be promptly addressed. Please do not disclose security-related issues publicly until a fix has been announced. Please see [Security Policy](https://github.com/guzzle/command/security/policy) for more information.

## License

Guzzle is made available under the MIT License (MIT). Please see [License File](LICENSE) for more information.

## For Enterprise

Available as part of the Tidelift Subscription

The maintainers of Guzzle and thousands of other packages are working with Tidelift to deliver commercial support and maintenance for the open source dependencies you use to build your applications. Save time, reduce risk, and improve code health, while paying the maintainers of the exact dependencies you use. [Learn more.](https://tidelift.com/subscription/pkg/packagist-guzzlehttp-command?utm_source=packagist-guzzlehttp-command&utm_medium=referral&utm_campaign=enterprise&utm_term=repo)
