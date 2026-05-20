<?php

declare(strict_types=1);

namespace GuzzleHttp\Command;

/**
 * An object that can be represented as an array
 */
interface ToArrayInterface
{
    /**
     * Get the array representation of an object
     */
    public function toArray(): array;
}
