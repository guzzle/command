<?php

namespace GuzzleHttp\Command;

use GuzzleHttp\Collection;
use GuzzleHttp\Event\EmitterInterface;
use GuzzleHttp\HasDataTrait;
use GuzzleHttp\Event\HasEmitterTrait;

/**
 * Default command implementation.
 */
class Command implements CommandInterface
{
    use HasDataTrait, HasEmitterTrait;

    /** @var Collection */
    private $config;

    /** @var string */
    private $name;

    /**
     * @param string           $name    Name of the command
     * @param array            $args    Arguments to pass to the command
     * @param EmitterInterface $emitter Emitter used by the command
     */
    public function __construct(
        $name,
        array $args,
        EmitterInterface $emitter = null
    ) {
        $this->name = $name;
        $this->data = $args;
        $this->emitter = $emitter;
    }

    public function getName()
    {
        return $this->name;
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
