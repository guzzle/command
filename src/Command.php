<?php
namespace GuzzleHttp\Command;

use GuzzleHttp\Event\EmitterInterface;
use GuzzleHttp\HasDataTrait;
use GuzzleHttp\Event\HasEmitterTrait;
use GuzzleHttp\Command\Event\CommandEvents;

/**
 * Default command implementation.
 */
class Command implements CommandInterface
{
    use HasDataTrait, HasEmitterTrait;

    /** @var string */
    private $name;

    /**
     * @param string           $name    Name of the command
     * @param array            $args    Arguments to pass to the command
     * @param EmitterInterface $emitter Emitter used by the command
     */
    public function __construct(
        $name,
        array $args = [],
        EmitterInterface $emitter = null
    ) {
        $this->name = $name;
        $this->data = $args;
        $this->emitter = $emitter;
    }

    /**
     * Ensure that the emitter is cloned.
     */
    public function __clone()
    {
        if ($this->emitter) {
            $this->emitter = clone $this->emitter;
        }
    }

    /**
     * Creates and prepares an HTTP request for a command but does not execute
     * the command.
     *
     * When the request is created, it is no longer associated with the command
     * and the event system of the command should no longer be depended upon.
     *
     * @param ServiceClientInterface $client  Client used to create requests
     * @param CommandInterface       $command Command to convert into a request
     *
     * @return \GuzzleHttp\Message\RequestInterface
     */
    public static function createRequest(
        ServiceClientInterface $client,
        CommandInterface $command
    ) {
        $trans = new CommandTransaction($client, $command);
        CommandEvents::prepare($trans);

        return $trans->getRequest();
    }

    public function getName()
    {
        return $this->name;
    }

    public function hasParam($name)
    {
        return array_key_exists($name, $this->data);
    }
}
