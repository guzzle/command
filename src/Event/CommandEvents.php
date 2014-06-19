<?php
namespace GuzzleHttp\Command\Event;

use GuzzleHttp\Command\CanceledResponse;
use GuzzleHttp\Command\CommandTransaction;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Event\HasEmitterTrait;
use GuzzleHttp\Event\RequestEvents;

/**
 * Wraps HTTP lifecycle events with command lifecycle events.
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
     * @param CommandTransaction $trans Command execution context
     * @throws \RuntimeException
     */
    public static function prepare(CommandTransaction $trans)
    {
        try {
            $ev = new PrepareEvent($trans);
            $trans->getCommand()->getEmitter()->emit('prepare', $ev);
        } catch (CommandException $e) {
            self::emitError($trans, $e);
            return;
        }

        $req = $trans->getRequest();
        $stopped = $ev->isPropagationStopped();

        if (!$req && !$stopped) {
            throw new \RuntimeException('No request was prepared for the'
                . ' command and no result was added to intercept the event.'
                . ' One of the listeners must set a request in the prepare'
                . ' event.');
        }

        if ($stopped) {
            // Event was intercepted with a result, so emit process
            self::process($trans);
        } elseif ($req) {
            self::injectErrorHandler($trans);
        }
    }

    /**
     * Handles the processing workflow of a command after it has been sent.
     *
     * @param CommandTransaction $trans Command execution context
     * @throws \GuzzleHttp\Command\Exception\CommandException
     */
    public static function process(CommandTransaction $trans)
    {
        // Throw if an exception occurred while sending the request
        if ($e = $trans->getException()) {
            $trans->setException(null);
            throw $e;
        }

        try {
            $trans->getCommand()->getEmitter()->emit(
                'process',
                new ProcessEvent($trans)
            );
        } catch (CommandException $e) {
            self::emitError($trans, $e);
        }
    }

    /**
     * Emits an error event for the command.
     *
     * @param CommandTransaction $trans Command execution context
     * @param CommandException   $e     Exception encountered
     * @throws CommandException
     */
    public static function emitError(
        CommandTransaction $trans,
        CommandException $e
    ) {
        // If this exception has already emitted, then throw it now.
        if ($e->emittedError()) {
            throw $e;
        }

        $e->emittedError(true);
        $event = new CommandErrorEvent($trans, $e);
        $trans->getCommand()->getEmitter()->emit('error', $event);

        if (!$event->isPropagationStopped()) {
            throw $e;
        }
    }

    /**
     * Wrap HTTP level errors with command level errors.
     */
    private static function injectErrorHandler(CommandTransaction $trans)
    {
        $trans->getRequest()->getEmitter()->on(
            'error',
            function (ErrorEvent $re) use ($trans) {
                $re->stopPropagation();
                $cex = self::exceptionFromError($trans, $re);
                $cev = new CommandErrorEvent($trans, $cex);
                $trans->getCommand()->getEmitter()->emit('error', $cev);

                if (!$cev->isPropagationStopped()) {
                    $trans->setException($cex);
                } else {
                    // Add a canceled response to prevent an adapter from
                    // sending a request if no response was received.
                    $trans->setException(null);
                    if (!$re->getResponse()) {
                        self::stopRequestError($re);
                    }
                }
            },
            RequestEvents::LATE
        );
    }

    /**
     * Create a CommandException from a request error event.
     */
    private static function exceptionFromError(
        CommandTransaction $trans,
        ErrorEvent $re
    ) {
        $className = 'GuzzleHttp\\Command\\Exception\\CommandException';
        // Throw a specific exception for client and server errors.
        $response = $re->getResponse();

        if (!$response) {
            self::stopRequestError($re);
        } else {
            $trans->setResponse($response);
            $statusCode = (string) $response->getStatusCode();
            if ($statusCode[0] == '4') {
                $className = 'GuzzleHttp\\Command\\Exception\\CommandClientException';
            } elseif ($statusCode[0] == '5') {
                $className = 'GuzzleHttp\\Command\\Exception\\CommandServerException';
            }
        }

        // Add the exception to the command and allow the request lifecycle to
        // complete successfully.
        $previous = $re->getException();

        return new $className(
            "Error executing command: " . $previous->getMessage(),
            $trans,
            $previous
        );
    }

    /**
     * Prevent a request from sending and intercept it's complete event.
     *
     * This method is required when a request fails before sending to prevent
     * adapters from still transferring the request over the wire.
     */
    private static function stopRequestError(ErrorEvent $e)
    {
        $fn = function ($ev) { $ev->stopPropagation(); };
        $e->getRequest()->getEmitter()->once('complete', $fn, 'first');
        $e->intercept(new CanceledResponse());
    }
}
