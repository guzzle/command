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
 * Wraps HTTP lifecycle events with command lifecycle events.
 *
 * This class uses __request and __exception command config options to manage
 * the state of a command.
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
     * Handles the processing workflow of a command after it has been sent.
     *
     * @param CommandInterface       $command  Command that was executed
     * @param ServiceClientInterface $client   Client that sent the command
     * @param RequestInterface       $request  Request that was sent
     * @param ResponseInterface      $response Response that was received
     * @param mixed                  $result   Specify the result if available
     *
     * @return mixed|null Returns the result of the command
     * @throws \GuzzleHttp\Command\Exception\CommandException
     */
    public static function process(
        CommandInterface $command,
        ServiceClientInterface $client,
        RequestInterface $request = null,
        ResponseInterface $response = null,
        $result = null
    ) {
        $config = $command->getConfig();

        // Handle when an error event is intercepted before sending a request.
        if (isset($config['__result'])) {
            $result = $config['__result'];
            unset($config['__result']);
        } elseif (isset($config['__exception'])) {
            // Throw if an exception occurred while transferring the command.
            $e = $config['__exception'];
            unset($config['__exception']);
            throw $e;
        }

        $e = new ProcessEvent($command, $client, $request, $response, $result);
        $command->getEmitter()->emit('process', $e);

        return $e->getResult();
    }

    /**
     * Wrap HTTP level errors with command level errors.
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
                    self::addCommandException($command, $client, $ce, $re);
                } else {
                    $command->getConfig()['__result'] = $ce->getResult();
                    // Add a canceled response to prevent an adapter from
                    // sending a request if no response was received.
                    if (!$re->getResponse()) {
                        self::stopRequestError($re);
                    }
                }
            },
            RequestEvents::LATE
        );
    }

    /**
     * Associate an exception with a command so that it can be thrown later.
     */
    private static function addCommandException(
        CommandInterface $command,
        ServiceClientInterface $client,
        CommandErrorEvent $ce,
        ErrorEvent $re
    ) {
        $className = 'GuzzleHttp\\Command\\Exception\\CommandException';

        // Throw a specific exception for client and server errors.
        $response = $re->getResponse();
        if (!$response) {
            self::stopRequestError($re);
        } else {
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
        $command->getConfig()['__exception'] = new $className(
            "Error executing command: " . $previous->getMessage(),
            $client,
            $command,
            $re->getRequest(),
            $response,
            $previous,
            $ce->toArray()
        );
    }

    /**
     * Prevent a request from sending an intercept it's complete event. This
     * method is required when a request fails before sending.
     */
    private static function stopRequestError(ErrorEvent $e)
    {
        $fn = function ($ev) { $ev->stopPropagation(); };
        $e->getRequest()->getEmitter()->once('complete', $fn, 'first');
        $e->intercept(new CanceledResponse());
    }
}
