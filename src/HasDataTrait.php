<?php

declare(strict_types=1);

namespace GuzzleHttp\Command;

/**
 * Basic collection behavior for Command and Result objects.
 *
 * The methods in the class are primarily for implementing the ArrayAccess,
 * Countable, and IteratorAggregate interfaces.
 */
trait HasDataTrait
{
    /** @var array Data stored in the collection. */
    protected array $data = [];

    public function __toString(): string
    {
        return print_r($this, true);
    }

    public function __debugInfo(): array
    {
        return $this->data;
    }

    public function offsetExists($offset): bool
    {
        if ($offset === null) {
            $offset = '';
        }

        return array_key_exists($offset, $this->data);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        if ($offset === null) {
            $offset = '';
        }

        return isset($this->data[$offset]) ? $this->data[$offset] : null;
    }

    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            $offset = '';
        }

        $this->data[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        if ($offset === null) {
            $offset = '';
        }

        unset($this->data[$offset]);
    }

    public function count(): int
    {
        return count($this->data);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->data);
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
