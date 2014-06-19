<?php

namespace GuzzleHttp\Command\Subscriber;

use GuzzleHttp\Command\Event\PrepareEvent;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Event\SubscriberInterface;

/**
 * Queues mock results and/or exceptions and delivers them in a FIFO order.
 */
class ResultMock implements SubscriberInterface, \Countable
{
    /** @var array Array of mock results and exceptions */
    private $queue = [];

    /**
     * @param array $results Array of results and exceptions to queue
     */
    public function __construct(array $results = [])
    {
        $this->addMultiple($results);
    }

    public function getEvents()
    {
        // Fire the event during command preparation, so request or response
        // ever needs to be created.
        return ['prepare' => ['onPrepare', 'first']];
    }

    /**
     * @throws CommandException if one has been queued.
     * @throws \OutOfBoundsException if the queue is empty.
     */
    public function onPrepare(PrepareEvent $event)
    {
        if (!$result = array_shift($this->queue)) {
            throw new \OutOfBoundsException('Result mock queue is empty');
        } elseif ($result instanceof CommandException) {
            throw $result;
        } elseif ($result instanceof \Exception) {
            // Use the message and event data to create a CommandException
            throw new CommandException(
                $result->getMessage(),
                $event->getClient(),
                $event->getCommand()
            );
        } else {
            $event->setResult($result);
        }
    }

    public function count()
    {
        return count($this->queue);
    }

    /**
     * Add a result to the end of the queue.
     *
     * @param mixed $result The result of the command.
     *
     * @return self
     */
    public function addResult($result)
    {
        $this->queue[] = $result;

        return $this;
    }

    /**
     * Add an exception to the end of the queue.
     *
     * @param CommandException $exception Thrown when the command is executed.
     *
     * @return self
     */
    public function addException(CommandException $exception)
    {
        $this->queue[] = $exception;

        return $this;
    }

    /**
     * Add an exception to the end of the queue, by specifying the message only.
     *
     * @param string $message Message used in the exception thrown when the command is executed.
     *
     * @throws \InvalidArgumentException if something other than a string is provided.
     * @return self
     */
    public function addExceptionMessage($message)
    {
        if (!is_string($message)) {
            throw new \InvalidArgumentException('You must provide a string.');
        }

        // A vanilla exception is used to transport the message, so that when it
        // is taken off the queue, we will know that it was not meant to be a
        // result, since results can be anything. The value will be used to
        // create a CommandException, which is what will actually be thrown.
        $this->queue[] = new \Exception($message);

        return $this;
    }

    /**
     * Add multiple results/exceptions to the queue
     *
     * @param array $results Results to add
     *
     * @return self
     */
    public function addMultiple(array $results)
    {
        foreach ($results as $result) {
            if ($result instanceof \Exception) {
                $this->addException($result);
            } else {
                $this->addResult($result);
            }
        }

        return $this;
    }

    /**
     * Clear the queue.
     *
     * @return self
     */
    public function clearQueue()
    {
        $this->queue = [];

        return $this;
    }
}
