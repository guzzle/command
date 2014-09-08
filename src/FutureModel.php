<?php
namespace GuzzleHttp\Command;

use GuzzleHttp\HasDataTrait;

/**
 * Future model result that may not have finished.
 */
class FutureModel implements FutureResultInterface
{
    use HasDataTrait;

    private $deref;

    public function __construct(callable $deref)
    {
        $this->deref = $deref;
        unset($this->data);
    }

    public function getResult()
    {
        return $this->data;
    }

    public function hasKey($name)
    {
        return isset($this->data[$name]);
    }

    public function __get($name)
    {
        if ($name == 'data') {
            $deref = $this->deref;
            $this->data = $deref();
            unset($this->deref);
            if (!is_array($this->data)) {
                throw new \RuntimeException('Future result must be an array');
            }
            return $this->data;
        }

        throw new \RuntimeException("Result has no property $name");
    }
}
