<?php

declare(strict_types=1);

namespace GuzzleHttp\Command\Exception;

use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Exception encountered while executing a command.
 */
class CommandException extends \RuntimeException implements GuzzleException
{
    private CommandInterface $command;

    private ?RequestInterface $request;

    private ?ResponseInterface $response;

    public static function fromPrevious(CommandInterface $command, \Exception $prev): self
    {
        // If the exception is already a command exception, return it.
        if ($prev instanceof self && $command === $prev->getCommand()) {
            return $prev;
        }

        // If the exception is request-aware, preserve the Request.
        $request = $response = null;
        if ($prev instanceof RequestExceptionInterface || $prev instanceof NetworkExceptionInterface) {
            $request = $prev->getRequest();
        }

        // Guzzle RequestException also exposes the optional Response.
        if ($prev instanceof RequestException) {
            $response = $prev->getResponse();
        }

        // Throw a more specific exception for 4XX or 5XX responses.
        $class = self::class;
        $statusCode = $response ? $response->getStatusCode() : 0;
        if ($statusCode >= 400 && $statusCode < 500) {
            $class = CommandClientException::class;
        } elseif ($statusCode >= 500 && $statusCode < 600) {
            $class = CommandServerException::class;
        }

        // Prepare the message.
        $message = 'There was an error executing the '.$command->getName()
            .' command: '.$prev->getMessage();

        // Create the exception.
        return new $class($message, $command, $prev, $request, $response);
    }

    /**
     * @param string          $message  Exception message
     * @param \Throwable|null $previous Previous exception (if any)
     */
    public function __construct(
        string $message,
        CommandInterface $command,
        ?\Throwable $previous = null,
        ?RequestInterface $request = null,
        ?ResponseInterface $response = null
    ) {
        $this->command = $command;
        $this->request = $request;
        $this->response = $response;
        parent::__construct($message, 0, $previous);
    }

    /**
     * Gets the command that failed.
     */
    public function getCommand(): CommandInterface
    {
        return $this->command;
    }

    /**
     * Gets the request that caused the exception
     */
    public function getRequest(): ?RequestInterface
    {
        return $this->request;
    }

    /**
     * Gets the associated response
     */
    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }
}
