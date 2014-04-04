<?php

namespace GuzzleHttp\Command\Event;

use GuzzleHttp\Command\CanceledResponse;
use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Event\HasEmitterTrait;
use GuzzleHttp\Event\RequestEvents;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\ServiceClientInterface;

/**
 * Utility class used to wrap HTTP events with client events.
 */
class CommandEvents
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
    public static function prepare(
        CommandInterface $command,
        ServiceClientInterface $client
    ) {
        $ev = new PrepareEvent($command, $client);
        $command->getEmitter()->emit('prepare', $ev);
        $req = $ev->getRequest();
        $stopped = $ev->isPropagationStopped();

        if (!$req && !$stopped) {
            throw new \RuntimeException('No request was prepared for the'
                . ' command and no result was added to intercept the event. One'
                . ' of the listeners must set a request in the prepare event.');
        }

        if ($stopped) {
            // Event was intercepted with a result, so emit the process event.
            self::process($command, $client, $req, null, $ev->getResult());
        } elseif ($req) {
            self::injectErrorHandler($command, $client, $req);
        }

        return $ev;
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
    public static function process(
        CommandInterface $command,
        ServiceClientInterface $client,
        RequestInterface $request = null,
        ResponseInterface $response = null,
        $result = null
    ) {
        // Handle when an error event is intercepted before sending a request.
        if ($response instanceof CanceledResponse) {
            $config = $command->getConfig();
            $result = $config['__result'];
            unset($config['__result']);
            return $result;
        }

        $e = new ProcessEvent($command, $client, $request, $response, $result);
        $command->getEmitter()->emit('process', $e);

        return $e->getResult();
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
            function (ErrorEvent $re) use ($command, $client) {
                $re->stopPropagation();
                $ce = new CommandErrorEvent($command, $client, $re);
                $command->getEmitter()->emit('error', $ce);
                if (!$ce->isPropagationStopped()) {
                    self::throwErrorException($command, $client, $ce, $re);
                } else {
                    self::interceptRequestError($ce, $re->getRequest());
                }
            },
            RequestEvents::LATE
        );
    }

    private static function throwErrorException(
        CommandInterface $command,
        ServiceClientInterface $client,
        CommandErrorEvent $ce,
        ErrorEvent $re
    ) {
        $className = 'GuzzleHttp\\Command\\Exception\\CommandException';

        // If a response was received, then throw a specific exception.
        if ($response = $re->getResponse()) {
            $statusCode = (string) $response->getStatusCode();
            if ($statusCode[0] == '4') {
                $className = 'GuzzleHttp\\Command\\Exception\\CommandClientException';
            } elseif ($statusCode[0] == '5') {
                $className = 'GuzzleHttp\\Command\\Exception\\CommandServerException';
            }
        }

        $previous = $re->getException();
        throw new $className(
            "Error executing command: " . $previous->getMessage(),
            $client,
            $command,
            $re->getRequest(),
            $response,
            $previous,
            $ce->toArray()
        );
    }

    private static function interceptRequestError(
        CommandErrorEvent $e,
        RequestInterface $request = null
    ) {
        // Add a canceled response to prevent an adapter from sending a request.
        if (!$e->getRequestErrorEvent()->getResponse()) {
            $e->getCommand()->getConfig()->set('__result', $e->getResult());
            $fn = function ($ev) { $ev->stopPropagation(); };
            $e->getRequest()->getEmitter()->once('complete', $fn, 'first');
            $e->getRequestErrorEvent()->intercept(new CanceledResponse());
        }

        self::process($e->getCommand(), $e->getClient(), $request, null, $e->getResult());
    }
}
