<?php
namespace GuzzleHttp\Command;

use GuzzleHttp\HasDataTrait;

/**
 * Default model implementation.
 */
class Model implements ModelInterface
{
    use HasDataTrait;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function hasKey($name)
    {
        return isset($this->data[$name]);
    }
}
