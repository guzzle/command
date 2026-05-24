<?php

declare(strict_types=1);

namespace GuzzleHttp\Command;

use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\PromiseInterface;

/**
 * Default command implementation.
 */
class Command implements CommandInterface
{
    use HasDataTrait;

    private string $name;

    /** @var HandlerStack<callable(CommandInterface): PromiseInterface<ResultInterface, mixed>>|null */
    private ?HandlerStack $handlerStack;

    /**
     * @param string                                                                                  $name         Name of the command
     * @param array                                                                                   $args         Arguments to pass to the command
     * @param HandlerStack<callable(CommandInterface): PromiseInterface<ResultInterface, mixed>>|null $handlerStack Stack of middleware for the command
     */
    public function __construct(
        string $name,
        array $args = [],
        ?HandlerStack $handlerStack = null
    ) {
        $this->name = $name;
        $this->data = $args;
        $this->handlerStack = $handlerStack;
    }

    /**
     * @return HandlerStack<callable(CommandInterface): PromiseInterface<ResultInterface, mixed>>|null
     */
    public function getHandlerStack(): ?HandlerStack
    {
        return $this->handlerStack;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function hasParam(?string $name): bool
    {
        if ($name === null) {
            $name = '';
        }

        return array_key_exists($name, $this->data);
    }

    public function __clone()
    {
        if ($this->handlerStack) {
            $this->handlerStack = clone $this->handlerStack;
        }
    }
}
