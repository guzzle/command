===============
Guzzle Commands
===============

This library provides an event-based abstraction over Guzzle HTTP requests and
responses using **commands** and **models**. Events are emitted for preparing
commands, processing commands, and handling errors encountered while executing
a command.

Commands
    Key value pair objects representing an action to take on a web service.
    Commands have a name and a set of parameters.

Models
    Models are key value pair objects representing the result of an API
    operation.

Installing
==========

This project can be installed using Composer. Add the following to your
composer.json:

.. code-block:: javascript

    {
        "require": {
            "guzzlehttp/command": "0.6.*"
        }
    }

Service Clients
===============

Guzzle Service Clients are HTTP web service clients that use
``GuzzleHttp\Client`` objects, commands, and the command event system. Event
listeners are attached to the client to handle creating HTTP requests for a
command, processing HTTP responses into a result (typically a
``GuzzleHttp\Command\ModelInterface``), and wraps
``GuzzleHttp\Exception\RequestException`` objects using a higher-level
``GuzzleHttp\Command\Exception\CommandException``.

Service clients create commands using the ``getCommand()`` method.

.. code-block:: php

    $commandName = 'foo';
    $arguments = ['baz' => 'bar'];
    $command = $client->getCommand($commandName, $arguments);

After creating a command, you execute the command using the ``execute()``
method.

.. code-block:: php

    $result = $client->execute($command);

The result of executing a command can be anything. However, implementations
should clearly specify what the result of a command will be. A good result to
return for executing commands is an object that implements
``GuzzleHttp\Command\ModelInterface``.

Service clients have a magic method for calling commands by name without having
to create the command then execute it.

.. code-block:: php

    $result = $client->foo(['baz' => 'bar']);

Service clients have configuration options that can be accessed in event
listeners.

.. code-block:: php

    $value = $client->getConfig('name');

    // You can also use a path notation where sub-array keys are separated
    // using a "/".
    $value = $client->getConfig('foo/baz/bar');

Values can be set using a similar notation.

.. code-block:: php

    $client->setConfig('name', 'value');
    // Set by nested path, creating sub-arrays as needed
    $value = $client->setConfig('foo/baz/bar', 'value');

Event System
============

Commands emit three events:

prepare
    Emitted before executing a command. One of the event listeners
    MUST inject a ``GuzzleHttp\Message\RequestInterface`` object onto the
    emitted ``GuzzleHttp\Command\Event\PrepareEvent`` object.

    An event listener MAY inject a result onto the event using ``setResult()``.
    Injecting a result MUST prevent the command from sending a request, and MUST
    trigger the "process" event so that subsequent listeners can modify the
    result of a command as needed.

    .. code-block:: php

        use GuzzleHttp\Command\Event\PrepareEvent;

        $command->getEmitter()->on('prepare', function(PrepareEvent $event) {
            // Set a request on the command
            $request = $event->getClient()->createRequest(
                'GET',
                'http://httpbin.org/get'
            );
            $event->setRequest($request);
        });

process
    Emitted after a HTTP response has been received for the command
    OR when a result is injected into an emitted "prepare" or "error" event.
    Event listeners MAY modify the result of the command using the
    ``setResult()`` method of the ``GuzzleHttp\Command\Event\ProcessEvent``.
    Because this event is also emitted when a result is injected onto a
    PrepareEvent and CommandErrorEvent, there may not be a request or response
    available to the event.

    .. code-block:: php

        use GuzzleHttp\Command\Event\ProcessEvent;
        use GuzzleHttp\Command\Model;

        $command->getEmitter()->on('process', function(ProcessEvent $event) {
            // Parse the response into something (e.g., a Model object).
            $model = new Model([
                'code' => $event->getResponse()->getStatusCode()
            ]);
            // Set the custom result on the event
            $event->setResult($model);
        });

error
    Emitted when an error occurs after receiving an HTTP response. You
    MAY inject a result onto the ``GuzzleHttp\Command\Event\CommandErrorEvent``,
    which will prevent an exception from being thrown. When a result is injected,
    the "process" event is triggered. When the CommandErrorEvent is not
    intercepted with a result, then a
    ``GuzzleHttp\Command\Exception\CommandException`` is thrown.

    Event listeners can add custom metadata to the CommandErrorEvent by
    treating the event like an associative array. In addition to being able to
    store custom key/value pairs, you can iterate over the custom keys of the
    event using ``foreach()``.

    .. code-block:: php

        $command->getEmitter()->on('error', function(CommandErrorEvent $e) {
            $e['custom'] = 'data';
            echo $e['custom']; // outputs "data"
            // You can iterate over the event like an array
            foreach ($event as $key => $value) {
                echo $key . ' = ' . $value . "\n";
            }
        });

Implementations SHOULD use ``GuzzleHttp\Command\Event\CommandEvents`` to
implement the event system correctly.
