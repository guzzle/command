<?php
namespace GuzzleHttp\Command\Event;

use GuzzleHttp\Message\ResponseInterface;

/**
 * Event emitted when a command completes with either an exception or result.
 *
 * When listening to this event, you must check if an exception was encountered
 * using the getException() method.
 */
class CommandEndEvent extends AbstractCommandEvent
{
    /**
     * Returns the exception that was encountered (if any).
     *
     * @return \Exception
     */
    public function getException()
    {
        return $this->trans->exception;
    }

    /**
     * Retrieves the HTTP response that was received for the command
     * (if available).
     *
     * @return ResponseInterface|null
     */
    public function getResponse()
    {
        return $this->trans->response;
    }
}
