<?php
namespace GuzzleHttp\Command;

use GuzzleHttp\Event\RequestEvents;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Command\Event\ProcessEvent;
use GuzzleHttp\Command\Event\CommandErrorEvent;
use GuzzleHttp\Pool;

/**
 * Provides useful functions for interacting with web service clients.
 */
class Utils
{
    /**
     * Sends multiple commands concurrently and returns a hash map of commands
     * mapped to their corresponding result or exception.
     *
     * Note: This method keeps every command and command and result in memory,
     * and as such is NOT recommended when sending a large number or an
     * indeterminable number of commands concurrently. Instead, you should use
     * executeAll() and utilize the event system to work with results.
     *
     * @param ServiceClientInterface $client
     * @param array|\Iterator        $commands Commands to send.
     * @param array                  $options  Passes through the options available
     *                                         in {@see ServiceClientInterface::createPool()}
     *
     * @return \SplObjectStorage Commands are the key and each value is the
     *     result of the command on success or an instance of
     *     {@see GuzzleHttp\Command\Exception\CommandException} if a failure
     *     occurs while executing the command.
     * @throws \InvalidArgumentException if the event format is incorrect.
     */
    public static function batch(
        ServiceClientInterface $client,
        $commands,
        array $options = []
    ) {
        $hash = new \SplObjectStorage();
        foreach ($commands as $command) {
            $hash->attach($command);
        }

        $client->executeAll($commands, RequestEvents::convertEventArray(
            $options,
            ['process', 'error'],
            [
                'priority' => RequestEvents::EARLY,
                'once'     => true,
                'fn'       => function ($e) use ($hash) {
                    $hash[$e->getCommand()] = $e;
                }
            ]
        ));

        // Update the received value for any of the intercepted commands.
        foreach ($hash as $request) {
            if ($hash[$request] instanceof ProcessEvent) {
                $hash[$request] = $hash[$request]->getResult();
            } elseif ($hash[$request] instanceof CommandErrorEvent) {
                $trans = $hash[$request]->getTransaction();
                $hash[$request] = new CommandException(
                    'Error executing command',
                    $trans,
                    $trans->commandException
                );
            }
        }

        return $hash;
    }

    /**
     * Creates a pool from a list of commands that allows the commands to be
     * sent concurrently.
     *
     * @param ServiceClientInterface $client   Client that sends the commands.
     * @param array|\Iterator        $commands Commands to send
     * @param array                  $options  Options specified in
     *                                         {@see ServiceClientInterface::executeAll()}
     *
     * @return Pool
     */
    public static function createPool(
        ServiceClientInterface $client,
        $commands,
        array $options = []
    ) {
        return new Pool(
            $client->getHttpClient(),
            new CommandToRequestIterator(
                $client,
                $commands,
                self::preventCommandExceptions($options)
            ),
            isset($options['pool_size'])
                ? ['pool_size' => $options['pool_size']]
                : []
        );
    }

    private static function preventCommandExceptions(array $options)
    {
        // Prevent CommandExceptions from being thrown
        return RequestEvents::convertEventArray($options, ['error'], [
            'priority' => RequestEvents::LATE,
            'fn' => function (CommandErrorEvent $e) {
                    $e->stopPropagation();
                }
        ]);
    }
}
