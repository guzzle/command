<?php

declare(strict_types=1);

namespace GuzzleHttp\Command\Exception;

use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ResponseException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\DiagnosticValue;
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

    public static function fromPrevious(
        #[\SensitiveParameter]
        CommandInterface $command,
        #[\SensitiveParameter]
        \Exception $prev
    ): self {
        // If the exception is already a command exception, return it.
        if ($prev instanceof self && $command === $prev->getCommand()) {
            return $prev;
        }

        // If the exception is request-aware, preserve the Request.
        $request = $response = null;
        if ($prev instanceof TransferException) {
            $request = $prev->getRequest();
        } elseif ($prev instanceof RequestExceptionInterface || $prev instanceof NetworkExceptionInterface) {
            $request = $prev->getRequest();
        }

        // Guzzle response-aware exceptions also expose the Response.
        if ($prev instanceof ResponseException) {
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
        $message = \sprintf('There was an error executing the %s command: %s', DiagnosticValue::escape($command->getName()), DiagnosticValue::escape($prev->getMessage()));

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
