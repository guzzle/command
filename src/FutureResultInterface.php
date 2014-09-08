<?php
namespace GuzzleHttp\Command;

/**
 * Represents a result to a command that may not have finished sending.
 */
interface FutureResultInterface extends ModelInterface
{
    /**
     * Block until the result has finished sending and return the result.
     *
     * @return mixed
     */
    public function getResult();
};
