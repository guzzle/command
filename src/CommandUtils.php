<?php
namespace GuzzleHttp\Command;

use GuzzleHttp\Command\Event\CommandEndEvent;
use GuzzleHttp\Event\RequestEvents;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Command\Event\ProcessEvent;
use GuzzleHttp\Command\Event\CommandErrorEvent;
use GuzzleHttp\BatchResults;

/**
 * Provides useful functions for interacting with web service clients.
 */
class CommandUtils
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
     * @return BatchResults
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
            ['end'],
            [
                'priority' => RequestEvents::LATE,
                'fn'       => function (CommandEndEvent $e) use ($hash) {
                    $hash[$e->getCommand()] = $e->getException()
                        ? $e->getException()
                        : $e->getResult();
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
                    $trans->exception
                );
            }
        }

        return new BatchResults($hash);
    }
}
