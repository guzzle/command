<?php

namespace GuzzleHttp\Command;

use GuzzleHttp\PathTrait;

/**
 * Default model implementation.
 */
class Model implements ModelInterface
{
    use PathTrait;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function hasKey($name)
    {
        return isset($this->data[$name]);
    }
}
