<?php
namespace GuzzleHttp\Command\Event;

use GuzzleHttp\Command\CommandTransaction;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\HasDataTrait;
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
    /** @var CommandException */
    private $commandException;

    /**
     * @param CommandTransaction $trans Command transfer context
     * @param CommandException   $e     Command exception encountered
     */
    public function __construct(CommandTransaction $trans, CommandException $e)
    {
        $this->trans = $trans;
        $this->commandException = $e;
    }

    /**
     * Returns the exception that was encountered.
     *
     * @return CommandException
     */
    public function getCommandException()
    {
        return $this->commandException;
    }

    /**
     * Retrieves the HTTP response that was received for the command
     * (if available).
     *
     * @return ResponseInterface|null
     */
    public function getResponse()
    {
        return $this->trans->getResponse();
    }

    /**
     * Intercept the error and inject a result
     *
     * @param mixed $result Result to associate with the command
     */
    public function setResult($result)
    {
        $this->trans->setResult($result);
        $this->stopPropagation();
    }
}
