<?php
namespace GuzzleHttp\Command;

use GuzzleHttp\Event\CompleteEvent;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Command\Event\CommandEvents;
use GuzzleHttp\Event\ListenerAttacherTrait;

/**
 * Iterator used for easily creating request objects from an iterator or array
 * that contains commands.
 *
 * This iterator is useful when implementing the
 * ``ServiceClientInterface::executeAll()`` method.
 */
class CommandToRequestIterator implements \Iterator
{
    use ListenerAttacherTrait;

    /** @var \Iterator */
    private $commands;

    /** @var array */
    private $options;

    /** @var ServiceClientInterface */
    private $client;

    /** @var RequestInterface|null Current request */
    private $currentRequest;

    /** @var array Listeners to attach to each command */
    private $eventListeners = [];

    /**
     * @param array|\Iterator        $commands Collection of command objects
     * @param ServiceClientInterface $client   Associated service client
     * @param array                  $options  Hash of options:
     *     - prepare: Callable to invoke when the "prepare" event of a command
     *       is emitted. This callable is invoked near the end of the event
     *       chain.
     *     - process: Callable to invoke when the "process" event of a command
     *       is emitted. This callable is triggered at or near the end of the
     *       event chain.
     *     - error: Callable to invoke when the "error" event of a command is
     *       emitted. This callable is invoked near the end of the event chain.
     *     - parallel: Integer representing the maximum allowed number of
     *       requests to send in parallel. Defaults to 50.
     *
     * @throws \InvalidArgumentException If the source is invalid
     */
    public function __construct(
        $commands,
        ServiceClientInterface $client,
        array $options = []
    ) {
        $this->client = $client;
        $this->options = $options;

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
        if ($this->currentRequest) {
            return true;
        }

        if (!$this->commands->valid()) {
            return false;
        }

        $command = $this->commands->current();
        if (!($command instanceof CommandInterface)) {
            throw new \RuntimeException('All commands provided to the ' . __CLASS__
                . ' must implement GuzzleHttp\\Command\\CommandInterface.'
                . ' Encountered a ' . gettype($command) . ' value.');
        }

        $trans = new CommandTransaction($this->client, $command);
        $this->prepare($trans);

        // Handle the command being intercepted with a result or failing by
        // not generating a request by going to the next command and returning
        // it's validity
        if ($trans->getResult() !== null || !$trans->getRequest()) {
            $this->commands->next();
            return $this->valid();
        }

        $this->processCurrentRequest($trans);

        return true;
    }

    public function rewind()
    {
        $this->currentRequest = null;

        if (!($this->commands instanceof \Generator)) {
            $this->commands->rewind();
        }
    }

    /**
     * Prepare a command using the provided options.
     */
    private function prepare(CommandTransaction $trans)
    {
        $this->attachListeners($trans->getCommand(), $this->eventListeners);
        CommandEvents::prepare($trans);
    }

    /**
     * Set the current request of the iterator and hook the request's event
     * system up to the command's event system.
     */
    private function processCurrentRequest(CommandTransaction $trans)
    {
        $this->currentRequest = $trans->getRequest();

        if ($this->currentRequest) {
            // Emit the command's process event when the request completes
            $this->currentRequest->getEmitter()->on(
                'complete',
                function (CompleteEvent $event) use ($trans) {
                    $trans->setResponse($event->getResponse());
                    CommandEvents::process($trans);
                }
            );
        }
    }
}
