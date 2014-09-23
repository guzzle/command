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
     * Mark the command as needing a retry and stop event propagation.
     */
    public function retry()
    {
        $this->trans->result = $this->trans->exception = null;
        $this->trans->state = 'before';
        $this->stopPropagation();
    }
}
