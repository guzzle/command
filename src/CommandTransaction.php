<?php
namespace GuzzleHttp\Command;

use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Transaction;
use GuzzleHttp\Collection;
use GuzzleHttp\Command\Exception\CommandException;

/**
 * Represents a command transaction as it is sent over the wire and inspected
 * by event listeners.
 */
class CommandTransaction extends Transaction
{
    /**
     * Web service client used in the transaction
     *
     * @var ServiceClientInterface
     */
    public $serviceClient;

    /**
     * The command being executed.
     *
     * @var CommandInterface
     */
    public $command;

    /**
     * The result of the command (if available)
     *
     * @var mixed|null
     */
    public $result;

    /**
     * The exception that was received while transferring (if any).
     *
     * @var CommandException
     */
    public $exception;

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
     * @param ServiceClientInterface $client  Client that executes commands.
     * @param CommandInterface       $command Command being executed.
     * @param RequestInterface       $request Request to send.
     * @param array                  $context Command context array of data.
     */
    public function __construct(
        ServiceClientInterface $client,
        CommandInterface $command,
        RequestInterface $request,
        array $context = []
    ) {
        $this->serviceClient = $client;
        $this->command = $command;
        $this->request = $request;
        $this->context = new Collection($context);
        $this->client = $client->getHttpClient();
    }
}
