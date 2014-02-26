<?php

namespace GuzzleHttp\Command\Event;

use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Event\HasEmitterTrait;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\ServiceClientInterface;
use GuzzleHttp\Command\Exception\CommandException;

/**
 * Utility class used to wrap HTTP events with client events.
 */
class EventWrapper
{
    /**
     * Handles the workflow of a command before it is sent.
     *
     * This includes preparing a request for the command, hooking the command
     * event system up to the request's event system, and returning the
     * prepared request.
     *
     * @param CommandInterface       $command Command to prepare
     * @param ServiceClientInterface $client  Client that executes the command
     *
     * @return PrepareEvent returns the PrepareEvent. You can use this to see
     *     if the event was intercepted with a result, or to grab the request
     *     that was prepared for the event.
     *
     * @throws \RuntimeException
     */
    public static function prepareCommand(
        CommandInterface $command,
        ServiceClientInterface $client
    ) {
        $event = self::prepareEvent($command, $client);
        $request = $event->getRequest();

        $stopped = $event->isPropagationStopped();

        if ($request) {
            // A request was created, so hook into the request's error handler
            self::injectErrorHandler($command, $client, $request);
        } elseif (!$stopped) {
            throw new \RuntimeException('No request was prepared for the '
                . 'command and no result was added to intercept the event. One '
                . 'of the listeners must set a request in the prepare event.');
        }

        // The event was intercepted with a result, so emit the process event
        if ($stopped) {
            self::processCommand(
                $command,
                $client,
                $request,
                null,
                $event->getResult()
            );
        }

        return $event;
    }

    /**
     * Handles the processing workflow of a command after it has been sent and
     * a response has been received.
     *
     * @param CommandInterface       $command  Command that was executed
     * @param ServiceClientInterface $client   Client that sent the command
     * @param RequestInterface       $request  Request that was sent
     * @param ResponseInterface      $response Response that was received
     * @param mixed                  $result   Specify the result if available
     *
     * @return mixed|null Returns the result of the command
     */
    public static function processCommand(
        CommandInterface $command,
        ServiceClientInterface $client,
        RequestInterface $request = null,
        ResponseInterface $response = null,
        $result = null
    ) {
        return $command->getEmitter()->emit(
            'process',
            new ProcessEvent($command, $client, $request, $response, $result)
        )->getResult();
    }

    /**
     * Prepares a command for sending and returns the prepare event.
     *
     * @param CommandInterface       $command Command to prepare
     * @param ServiceClientInterface $client  Client associated with the command
     *
     * @return PrepareEvent
     * @throws CommandException on error
     */
    private static function prepareEvent(
        CommandInterface $command,
        ServiceClientInterface $client
    ) {
        try {
            return $command->getEmitter()->emit(
                'prepare',
                new PrepareEvent($command, $client)
            );
        } catch (CommandException $e) {
            // Let these exception flow through, untouched.
            throw $e;
        } catch (\Exception $e) {
            // Wrap lower level exceptions with a consistent CommandException
            $msg = 'Error preparing command: ' . $e->getMessage();
            throw new CommandException($msg, $client, $command, null, null, $e);
        }
    }

    /**
     * Wrap HTTP level errors with command level errors.
     *
     * @param CommandInterface       $command Command to modify
     * @param ServiceClientInterface $client  Client associated with the command
     * @param RequestInterface       $request Prepared request for the command
     */
    private static function injectErrorHandler(
        CommandInterface $command,
        ServiceClientInterface $client,
        RequestInterface $request
    ) {
        $request->getEmitter()->on(
            'error',
            function (ErrorEvent $e) use ($command, $client) {
                $e->stopPropagation();
                $event = new CommandErrorEvent($command, $client, $e);
                $command->getEmitter()->emit('error', $event);

                // The event was intercepted, so cancel the request event and
                // emit the process event for the command.
                if ($event->isPropagationStopped()) {
                    self::processCommand(
                        $command,
                        $client,
                        $event->getRequest(),
                        null,
                        $event->getResult()
                    );
                    return;
                }

                // No result was injected, so throw a higher-level exception.
                // If a response was received, then throw a specific exception.
                $className = 'GuzzleHttp\\Command\\Exception\\CommandException';
                $extra = '';

                if ($response = $e->getResponse()) {
                    $statusCode = (string) $response->getStatusCode();
                    if ($statusCode[0] == '4') {
                        $className = 'GuzzleHttp\\Command\\Exception\\CommandClientException';
                        $extra = ' (client error response)';
                    } elseif ($statusCode[0] == '5') {
                        $className = 'GuzzleHttp\\Command\\Exception\\CommandServerException';
                        $extra = ' (server error response)';
                    }
                }

                throw new $className(
                    "Error executing command{$extra}: " . $e->getException()->getMessage(),
                    $client,
                    $command,
                    $e->getRequest(),
                    $e->getResponse(),
                    $e->getException()
                );
            }
        );
    }
}
