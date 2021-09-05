# Guzzle Commands

This library uses Guzzle (``guzzlehttp/guzzle``, version 7.x) and provides the
foundations to create fully-featured web service clients by abstracting Guzzle
HTTP **requests** and **responses** into higher-level **commands** and
**results**. A **middleware** system, analogous to — but separate from — the one
in the HTTP layer may be used to customize client behavior when preparing
commands into requests and processing responses into results.

### Commands

Key-value pair objects representing an operation of a web service. Commands have a name and a set of parameters.

### Results

Key-value pair objects representing the processed result of executing an operation of a web service.

## Installing

This project can be installed using Composer:

``composer require guzzlehttp/command``

For **Guzzle 5**, use ``composer require guzzlehttp/command:0.8.*``. The source
code for the Guzzle 5 version is available on the
`0.8 branch <https://github.com/guzzle/command/tree/0.8>`_.

**Note:** If Composer is not installed [globally](https://getcomposer.org/doc/00-intro.md#globally) then you may need to run the preceding Composer commands using ``php composer.phar`` (where ``composer.phar`` is the path to your copy of Composer), instead of just ``composer``.

## Service Clients

Service Clients are web service clients that implement the
``GuzzleHttp\Command\ServiceClientInterface`` and use an underlying Guzzle HTTP
client (``GuzzleHttp\Client``) to communicate with the service. Service clients
create and execute **commands** (``GuzzleHttp\Command\CommandInterface``),
which encapsulate operations within the web service, including the operation
name and parameters. This library provides a generic implementation of a service
client: the ``GuzzleHttp\Command\ServiceClient`` class.

## Instantiating a Service Client

@TODO Add documentation

* ``ServiceClient``'s constructor
* Transformer functions (``$commandToRequestTransformer`` and ``$responseToResultTransformer``)
* The ``HandlerStack``

## Executing Commands

Service clients create command objects using the ``getCommand()`` method.

```php
$commandName = 'foo';
$arguments = ['baz' => 'bar'];
$command = $client->getCommand($commandName, $arguments);

```

After creating a command, you may execute the command using the ``execute()`` method of the client.

```php
$result = $client->execute($command);
```

The result of executing a command will be a ``GuzzleHttp\Command\ResultInterface``
object. Result objects are ``ArrayAccess``-ible and contain the data parsed from
HTTP response.

Service clients have magic methods that act as shortcuts to executing commands
by name without having to create the ``Command`` object in a separate step
before executing it.

```php
$result = $client->foo(['baz' => 'bar']);
```

## Asynchronous Commands

@TODO Add documentation

* ``-Async`` suffix for client methods
* Promises

```php
// Create and execute an asynchronous command.
$command = $command = $client->getCommand('foo', ['baz' => 'bar']);
$promise = $client->executeAsync($command);

// Use asynchronous commands with magic methods.
$promise = $client->fooAsync(['baz' => 'bar']);
```

@TODO Add documentation

* ``wait()``-ing on promises.

```php
$result = $promise->wait();

echo $result['fizz']; //> 'buzz'
```

## Concurrent Requests

@TODO Add documentation

* ``executeAll()``
* ``executeAllAsync()``.
* Options (``fulfilled``, ``rejected``, ``concurrency``)

## Middleware: Extending the Client

Middleware can be added to the service client or underlying HTTP client to
implement additional behavior and customize the ``Command``-to-``Result`` and
``Request``-to-``Response`` lifecycles, respectively.

## Security

If you discover a security vulnerability within this package, please send an email to security@tidelift.com. All security vulnerabilities will be promptly addressed. Please do not disclose security-related issues publicly until a fix has been announced. Please see [Security Policy](https://github.com/guzzle/command/security/policy) for more information.

## License

Guzzle is made available under the MIT License (MIT). Please see [License File](LICENSE) for more information.

## For Enterprise

Available as part of the Tidelift Subscription

The maintainers of Guzzle and thousands of other packages are working with Tidelift to deliver commercial support and maintenance for the open source dependencies you use to build your applications. Save time, reduce risk, and improve code health, while paying the maintainers of the exact dependencies you use. [Learn more.](https://tidelift.com/subscription/pkg/packagist-guzzlehttp-command?utm_source=packagist-guzzlehttp-command&utm_medium=referral&utm_campaign=enterprise&utm_term=repo)
