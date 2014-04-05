<?php
namespace GuzzleHttp\Command\Exception;

use GuzzleHttp\Command\ServiceClientInterface;
use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Message\ResponseInterface;

/**
 * Exception encountered while transferring a command.
 */
class CommandException extends \RuntimeException
{
    /** @var ServiceClientInterface */
    private $client;

    /** @var CommandInterface */
    private $command;

    /** @var array */
    private $context;

    /**
     * @param string                 $message  Exception message
     * @param ServiceClientInterface $client   Client that sent the command
     * @param CommandInterface       $command  Command that failed
     * @param RequestInterface       $request  Request that was sent
     * @param ResponseInterface      $response Response that was received
     * @param array                  $context  Contextual exception data
     * @param \Exception             $previous Previous exception (if any)
     */
    public function __construct(
        $message,
        ServiceClientInterface $client,
        CommandInterface $command,
        RequestInterface $request = null,
        ResponseInterface $response = null,
        \Exception $previous = null,
        array $context = []
    ) {
        $this->client = $client;
        $this->command = $command;
        $this->request = $request;
        $this->response = $response;
        $this->context = $context;
        parent::__construct($message, 0, $previous);
    }

    /**
     * Get the client associated with the command exception
     *
     * @return ServiceClientInterface
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Get the command that was transferred.
     *
     * @return CommandInterface
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * Get the request associated with the command or null if one was not sent.
     *
     * @return RequestInterface
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Get the response associated with the command or null if one was not
     * received.
     *
     * @return ResponseInterface|null
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Get contextual error information about the exception.
     *
     * This contextual data may contain important data that was populated
     * during the command's event lifecycle such as parsed error data from a
     * web service response.
     *
     * @param string $keyOrPath Specify a path expression to retrieve nested
     *                          data, or leave null to retrieve all data.
     *
     * @return mixed|array
     */
    public function getContext($keyOrPath = null)
    {
        return $keyOrPath
            ? \GuzzleHttp\get_path($this->context, $keyOrPath)
            : $this->context;
    }
}
