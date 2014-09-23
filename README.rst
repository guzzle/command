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
            "guzzlehttp/command": "0.7.*"
        }
    }

Service Clients
===============

Guzzle Service Clients are HTTP web service clients that use
``GuzzleHttp\Client`` objects, commands, and the command event system. Event
listeners are attached to the client to handle creating HTTP requests for a
command, processing HTTP responses into a result (typically a
``GuzzleHttp\Command\ModelInterface``), and extends
``GuzzleHttp\Exception\RequestException`` objects with a higher-level
``GuzzleHttp\Command\Exception\CommandException``.

Service clients create commands using the ``getCommand()`` method.

.. code-block:: php

    $commandName = 'foo';
    $arguments = ['baz' => 'bar'];
    $command = $client->getCommand($commandName, $arguments);

After creating a command, you execute the command using the ``execute()``
method of the client.

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

Future Results
--------------

Service clients can create future results that return immediately and block
when they are used (or dereferenced). When creating a command, you can provide
the ``@future`` command parameter to control whether or not a future result is
created. Implementations should take this special setting into account when
creating commands.

.. code-block:: php

    // Create a command that's configured to get a future
    $command = $client->getCommand('name', ['@future' => true]);
    assert($command->getFuture() == true);

    // Create and execute a future command
    $result = $client->name(['@future' => true]);

    // Using a future result will block if necessary until the future has
    // completed (or been "realized").
    echo $result['foo'];
    assert($result->realized() == true);

    // You can also explicitly block until the command has finished using deref
    $result->deref();

Event System
============

Commands emit three events. These events are emitted immediately when an
underyling response has completed (even if it is a future response).

prepare
    Emitted immediately after a request has been prepared for a command. This
    event is fired only once per command execution. Use this event to hook into
    the request lifecycle events.

    .. code-block:: php

        use GuzzleHttp\Command\Event\PrepareEvent;

        $command->getEmitter()->on('prepare', function(PrepareEvent $event) {
            echo $event->getRequest();
        });

    Any exceptions thrown while emitting the "prepare" event will fail
    immediately. The command will not transition to the error state.

before
    Emitted before executing a prepared command. You can intercept this event
    and set a result for a command to prevent the request from being sent at
    the HTTP layer. This event MAY be emitted multiple times for a single
    command execution.

    .. code-block:: php

        use GuzzleHttp\Command\Event\CommandBeforeEvent;

        $command->getEmitter()->on(
            'before',
            function(CommandBeforeEvent $event) {
                // Prevent the request from being sent.
                $event->setResult(['foo' => 'bar']);
            }
        );

process
    The process event is emitted when processing an HTTP response or processing
    a previously set command result. It is important to note that a previously
    executed listener may have already set a result. Take this into account
    when writing process event listeners. It is also important to understand
    that an HTTP response may not be available in the process event.

    Event listeners MAY modify the result of the command using the
    ``setResult()`` method of the emitted ``GuzzleHttp\Command\Event\ProcessEvent``.

    This event MAY be called multiple times during the execution of a command.

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
    Emitted when an error occurs while executing a command.  You MAY inject a
    result onto the ``GuzzleHttp\Command\Event\CommandErrorEvent``, which will
    prevent an exception from being thrown. When a result is injected, the
    "process" event is triggered. When the CommandErrorEvent is not intercepted
    with a result, then a ``GuzzleHttp\Command\Exception\CommandException`` is
    thrown.

    Event listeners can add custom metadata to the CommandErrorEvent using the
    `getContext()` method.

    This event MAY be called multiple times during the execution of a command.

    .. code-block:: php

        $command->getEmitter()->on('error', function(CommandErrorEvent $e) {
            $e->getContext()->set('custom', 'data');
        });

end
    Emitted when a command completes, whether for a success or failure. This
    event will be invoked once, and only once, for a command execution.

    .. code-block:: php

        $command->getEmitter()->on('end', function(CommandEndEvent $e) {
            if ($e->getException()) {
                echo 'Oh no!';
            } else {
                var_dump($e->getResult());
            }
        });
