<?php
namespace GuzzleHttp\Command\Event;

/**
 * Event emitted when a command is being prepared.
 *
 * Event listeners can use this event to modify the request that was created
 * by the client, and to intercept the event to prevent HTTP requests from
 * being sent over the wire.
 *
 * This event provides a good way for a listener to hook into the HTTP level
 * event system.
 */
class PrepareEvent extends AbstractCommandEvent
{
    /**
     * Set a result on the command transaction to prevent the command from
     * actually sending an HTTP request.
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
