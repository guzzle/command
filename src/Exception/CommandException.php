<?php
namespace GuzzleHttp\Command\Exception;

use GuzzleHttp\Command\CommandTransaction;
use GuzzleHttp\Command\ServiceClientInterface;
use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Collection;

/**
 * Exception encountered while transferring a command.
 */
class CommandException
    extends \RuntimeException
    implements CommandExceptionInterface
{
    /** @var CommandTransaction */
    private $trans;

    /** @var bool */
    private $emittedErrorEvent = false;

    /**
     * @param string             $message  Exception message
     * @param CommandTransaction $trans    Contextual transfer information
     * @param \Exception         $previous Previous exception (if any)
     */
    public function __construct(
        $message,
        CommandTransaction $trans,
        \Exception $previous = null
    ) {
        $this->trans = $trans;
        parent::__construct($message, 0, $previous);
    }

    /**
     * Get the client associated with the command exception
     *
     * @return ServiceClientInterface
     */
    public function getClient()
    {
        return $this->trans->getClient();
    }

    /**
     * Get the command that was transferred.
     *
     * @return CommandInterface
     */
    public function getCommand()
    {
        return $this->trans->getCommand();
    }

    /**
     * Get the request associated with the command or null if one was not sent.
     *
     * @return RequestInterface
     */
    public function getRequest()
    {
        return $this->trans->getRequest();
    }

    /**
     * Get the response associated with the command or null if one was not
     * received.
     *
     * @return ResponseInterface|null
     */
    public function getResponse()
    {
        return $this->trans->getResponse();
    }

    /**
     * Get the result of the command if one was populated.
     *
     * @return mixed|null Returns the result or null if no result was populated
     */
    public function getResult()
    {
        return $this->trans->getResult();
    }

    /**
     * Get contextual error information about the transaction.
     *
     * This contextual data may contain important data that was populated
     * during the command's event lifecycle such as parsed error data from a
     * web service response.
     *
     * @return Collection
     */
    public function getContext()
    {
        return $this->trans->getContext();
    }

    /**
     * Gets the transaction associated with the exception
     *
     * @return CommandTransaction
     */
    public function getCommandTransaction()
    {
        return $this->trans;
    }

    /**
     * Check or set if the exception was emitted in an error event.
     *
     * This value is used in the CommandEvents::prepare() method to check
     * to see if an exception has already been emitted in an error event.
     *
     * @param bool|null Set to true to set the exception as having emitted an
     *     error. Leave null to retrieve the current setting.
     *
     * @return null|bool
     * @throws \InvalidArgumentException if you attempt to set the value to false
     */
    public function emittedError($value = null)
    {
        if ($value === null) {
            return $this->emittedErrorEvent;
        } elseif ($value === true) {
            return $this->emittedErrorEvent = true;
        } else {
            throw new \InvalidArgumentException('You cannot set the emitted '
                . 'error value to false.');
        }
    }
}
