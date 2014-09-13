<?php
namespace GuzzleHttp\Command;

use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Collection;

/**
 * Represents a command transaction as it is sent over the wire and inspected
 * by event listeners.
 */
class CommandTransaction
{
    /** @var ServiceClientInterface Client used in the transaction */
    public $client;

    /** @var CommandInterface The command being transferred */
    public $command;

    /**
     * Request of the the transaction (if available)
     *
     * @var RequestInterface|null
     */
    public $request;

    /** @var ResponseInterface Response that was received (if any) */
    public $response;

    /** @var mixed|null The result of the command (if available) */
    public $result;

    /**
     * The exception that was received while transferring (if any).
     *
     * @var \Exception|null
     */
    public $commandException;

    /**
     * Contains contextual information about the transaction.
     *
     * The information added to this collection can be anything required to
     * implement a command abstraction.
     *
     * @var Collection
     */
    public $context;

    /**
     * @param ServiceClientInterface $client  Client that executes commands
     * @param CommandInterface       $command Command being executed
     * @param array                  $context Command context array of data
     */
    public function __construct(
        ServiceClientInterface $client,
        CommandInterface $command,
        array $context = []
    ) {
        $this->client = $client;
        $this->command = $command;
        $this->context = new Collection($context);
    }
}
