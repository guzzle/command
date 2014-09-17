<?php
namespace GuzzleHttp\Command;

use GuzzleHttp\Ring\BaseFutureTrait;
use GuzzleHttp\Ring\Core;
use GuzzleHttp\Ring\Exception\CancelledFutureAccessException;
use GuzzleHttp\HasDataTrait;

/**
 * Future model result that may not have finished.
 */
class FutureModel implements FutureModelInterface
{
    use BaseFutureTrait;
    use HasDataTrait;

    public function __construct(callable $deref, callable $cancel = null)
    {
        $this->dereffn = $deref;
        $this->cancelfn = $cancel;
        // Unset $data so that we deref when accessed the first time.
        unset($this->data);
    }

    public function deref()
    {
        return $this->data;
    }

    public function hasKey($name)
    {
        return isset($this->data[$name]);
    }

    public function __get($name)
    {
        if ($name !== 'data') {
            throw new \RuntimeException("Result has no property $name");
        } elseif ($this->isCancelled) {
            throw new CancelledFutureAccessException('You are attempting '
                . 'to access a future that has been cancelled.');
        }

        $deref = $this->dereffn;
        $this->dereffn = $this->cancelfn = null;
        $data = $deref();

        if (!is_array($data)) {
            throw new \RuntimeException('Future result must be an array. '
                . 'Found ' . Core::describeType($data));
        }

        return $this->data = $data;
    }
}
