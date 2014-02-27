<?php

namespace GuzzleHttp\Command\Guzzle;

use GuzzleHttp\Collection;
use GuzzleHttp\Event\EmitterInterface;
use GuzzleHttp\HasDataTrait;
use GuzzleHttp\Event\HasEmitterTrait;
use GuzzleHttp\Command\Guzzle\Description\Operation;

/**
 * Default Guzzle command implementation.
 */
class Command implements GuzzleCommandInterface
{
    use HasDataTrait, HasEmitterTrait;

    /** @var Operation */
    private $operation;

    /** @var Collection */
    private $config;

    /**
     * @param Operation        $operation Operation associated with the command
     * @param array            $args      Arguments to pass to the command
     * @param EmitterInterface $emitter   Emitter used by the command
     */
    public function __construct(
        Operation $operation,
        array $args,
        EmitterInterface $emitter = null
    ) {
        $this->operation = $operation;
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

    public function getName()
    {
        return $this->operation->getName();
    }

    public function getOperation()
    {
        return $this->operation;
    }

    public function hasParam($name)
    {
        return array_key_exists($name, $this->data);
    }

    public function getConfig()
    {
        if (!$this->config) {
            $this->config = new Collection();
        }

        return $this->config;
    }
}
