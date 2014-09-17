<?php
namespace GuzzleHttp\Command;

use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Command\Event\CommandEvents;
use GuzzleHttp\Event\ListenerAttacherTrait;
use GuzzleHttp\Ring\Core;

/**
 * Iterator used for easily creating request objects from an iterator or array
 * that contains commands.
 *
 * This iterator is useful when implementing the
 * {@see ServiceClientInterface::executeAll()} method.
 */
class CommandToRequestIterator implements \Iterator
{
    use ListenerAttacherTrait;

    /** @var \Iterator */
    private $commands;

    /** @var ServiceClientInterface */
    private $client;

    /** @var RequestInterface|null Current request */
    private $currentRequest;

    /** @var array Listeners to attach to each command */
    private $eventListeners = [];

    /**
     * @param ServiceClientInterface $client   Associated service client
     * @param array|\Iterator        $commands Collection of command objects
     * @param array                  $options  Hash of options:
     *     - prepare: Callable to invoke when the "prepare" event of a command
     *       is emitted. This callable is invoked near the end of the event
     *       chain.
     *     - process: Callable to invoke when the "process" event of a command
     *       is emitted. This callable is triggered at or near the end of the
     *       event chain.
     *     - error: Callable to invoke when the "error" event of a command is
     *       emitted. This callable is invoked near the end of the event chain.
     *
     * @throws \InvalidArgumentException If the source is invalid
     */
    public function __construct(
        ServiceClientInterface $client,
        $commands,
        array $options = []
    ) {
        $this->client = $client;
        $this->eventListeners = $this->prepareListeners(
            $options,
            ['prepare', 'process', 'error']
        );

        if ($commands instanceof \Iterator) {
            $this->commands = $commands;
        } elseif (is_array($commands)) {
            $this->commands = new \ArrayIterator($commands);
        } else {
            throw new \InvalidArgumentException('Command iterators must be '
                . 'created using an \\Iterator or array or commands');
        }
    }

    public function current()
    {
        return $this->currentRequest;
    }

    public function next()
    {
        $this->currentRequest = null;
        $this->commands->next();
    }

    public function key()
    {
        return $this->commands->key();
    }

    public function valid()
    {
        // Return true if this function has already been called for iteration.
        if ($this->currentRequest) {
            return true;
        }

        // Return false if we are at the end of the provided commands iterator.
        if (!$this->commands->valid()) {
            return false;
        }

        $command = $this->commands->current();

        if (!($command instanceof CommandInterface)) {
            throw new \RuntimeException('All commands provided to the ' . __CLASS__
                . ' must implement GuzzleHttp\\Command\\CommandInterface.'
                . ' Encountered a ' . Core::describeType($command) . ' value.');
        }

        $command->setFuture('lazy');
        $this->attachListeners($command, $this->eventListeners);
        $trans = CommandEvents::prepareTransaction($this->client, $command);

        // Handle the command being intercepted with a result or failing by
        // not generating a request by going to the next command and returning
        // it's validity
        if ($trans->result !== null || !$trans->request) {
            $this->commands->next();
            return $this->valid();
        }

        $this->currentRequest = $trans->request;

        return true;
    }

    public function rewind()
    {
        $this->currentRequest = null;

        if (!($this->commands instanceof \Generator)) {
            $this->commands->rewind();
        }
    }
}
