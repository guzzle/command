<?php
namespace GuzzleHttp\Command\Event;

use GuzzleHttp\Message\ResponseInterface;

/**
 * Event emitted when an error occurs while transferring a request for a
 * command.
 *
 * Event listeners can inject a result onto the event to intercept the
 * exception with a successful result.
 */
class CommandErrorEvent extends AbstractCommandEvent
{
    /**
     * Returns the exception that was encountered.
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

    /**
     * Rescue the error by setting a result.
     *
     * Subsequent listeners ARE NOT emitted even when a result is set in the
     * error event.
     *
     * @param mixed $result Result to associate with the command
     */
    public function intercept($result)
    {
        $this->trans->exception = null;
        $this->trans->result = $result;
        $this->stopPropagation();
    }
}
