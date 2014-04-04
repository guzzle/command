<?php

namespace GuzzleHttp\Command\Event;

use GuzzleHttp\Command\CanceledResponse;
use GuzzleHttp\Event\CompleteEvent;
use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Event\HasEmitterTrait;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\ServiceClientInterface;

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
        $event = new PrepareEvent($command, $client);
        $command->getEmitter()->emit('prepare', $event);
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
        // Handle the edge case where a result is set and the process event
        // wrapper method is called twice (once to intercept and once in the
        // AbstractClient).
        if ($response instanceof CanceledResponse) {
            $config = $request->getConfig();
            $result = $config['command_result'];
            unset($config['command_result']);
            return $result;
        }

        $result = $command->getEmitter()->emit(
            'process',
            new ProcessEvent($command, $client, $request, $response, $result)
        )->getResult();

        // If there's no response, then the request errored out before it
        // was actually sent and a result was injected. Add the result to the
        // request's config so that the client can return it as the result.
        if (!$response && $request) {
            $request->getConfig()['command_result'] = $result;
        }

        return $result;
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
        // Add a listener that triggers at or near the end of the request's
        // error event chain that emits a command error event and allows the
        // request error to be stopped.
        $request->getEmitter()->on(
            'error',
            function (ErrorEvent $e) use ($command, $client) {
                $e->stopPropagation();
                $event = new CommandErrorEvent($command, $client, $e);
                $command->getEmitter()->emit('error', $event);

                // If the event was intercepted with a result cancel the request
                // event and emit the process event for the command.
                if ($event->isPropagationStopped()) {
                    self::addCanceledResponse($event);
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
            },
            -9999
        );
    }

    /**
     * If a command error event was intercepted, then the request event
     * lifecycle needs to be intercepted as well with a CanceledResponse
     * object. In addition to intercepting the request, the complete event of
     * the request must be intercepted as well by stopping the event
     * propagation as early as possible.
     *
     * This is necessary, for example, when mocking requests with exceptions
     * and interecepting the corresponding error event of a command.
     */
    private static function addCanceledResponse(CommandErrorEvent $event)
    {
        // If the error response had no response, then set a cancelled response
        // to prevent adapters from sending the request.
        if (!$event->getRequestErrorEvent()->getResponse()) {
            // Stop events from firing for the request's complete event
            $event->getRequest()->getEmitter()->once(
                'complete',
                function (CompleteEvent $event) {
                    $event->stopPropagation();
                },
                'first'
            );
            $event->getRequestErrorEvent()->intercept(new CanceledResponse());
        }
    }
}
