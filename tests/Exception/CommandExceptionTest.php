<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Command\CommandException;

use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Exception\CommandClientException;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Command\Exception\CommandServerException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\HandlerClosedException;
use GuzzleHttp\Exception\ResponseException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @covers \GuzzleHttp\Command\Exception\CommandException
 */
class CommandExceptionTest extends TestCase
{
    public function testCanGetDataFromException(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $exception = new CommandException('error', $command, null, $request, $response);
        $this->assertSame($command, $exception->getCommand());
        $this->assertSame($request, $exception->getRequest());
        $this->assertSame($response, $exception->getResponse());
    }

    public function testAcceptsThrowablePreviousException(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $previous = new \Error('previous');

        $exception = new CommandException('error', $command, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testFactoryReturnsExceptionIfAlreadyCommandException(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $previous = CommandException::fromPrevious($command, new \Exception());

        $exception = CommandException::fromPrevious($command, $previous);
        $this->assertSame($previous, $exception);
    }

    public function testFactoryEscapesUnsafeCommandAndPreviousMessages(): void
    {
        $commandName = "command\xC2\x80";
        $previousMessage = "failure\xFF";
        $command = $this->createMock(CommandInterface::class);
        $command->method('getName')->willReturn($commandName);
        $previous = new \RuntimeException($previousMessage);

        $exception = CommandException::fromPrevious($command, $previous);

        $this->assertSame('There was an error executing the command\\x80 command: failure\\xFF', $exception->getMessage());
        $this->assertSame($command, $exception->getCommand());
        $this->assertSame($commandName, $command->getName());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame($previousMessage, $previous->getMessage());
    }

    public function testFactoryReturnsClientExceptionFor400LevelStatusCode(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(404);
        $previous = new ResponseException('error', $request, $response);

        $exception = CommandException::fromPrevious($command, $previous);
        $this->assertInstanceOf(CommandClientException::class, $exception);
        $this->assertSame($request, $exception->getRequest());
        $this->assertSame($response, $exception->getResponse());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testFactoryReturnsServerExceptionFor500LevelStatusCode(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(500);
        $previous = new ResponseException('error', $request, $response);

        $exception = CommandException::fromPrevious($command, $previous);
        $this->assertInstanceOf(CommandServerException::class, $exception);
        $this->assertSame($request, $exception->getRequest());
        $this->assertSame($response, $exception->getResponse());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testFactoryCopiesRequestFromNetworkException(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $previous = new ConnectException('error', $request);

        $exception = CommandException::fromPrevious($command, $previous);
        $this->assertSame($request, $exception->getRequest());
        $this->assertNull($exception->getResponse());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testFactoryCopiesRequestFromGuzzleTransferException(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $previous = new HandlerClosedException('error', $request);

        $exception = CommandException::fromPrevious($command, $previous);
        $this->assertSame($request, $exception->getRequest());
        $this->assertNull($exception->getResponse());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testFactoryCopiesRequestFromPsrRequestException(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $previous = new class('error', $request) extends \RuntimeException implements RequestExceptionInterface {
            private RequestInterface $request;

            public function __construct(string $message, RequestInterface $request)
            {
                parent::__construct($message);
                $this->request = $request;
            }

            public function getRequest(): RequestInterface
            {
                return $this->request;
            }
        };

        $exception = CommandException::fromPrevious($command, $previous);
        $this->assertSame($request, $exception->getRequest());
        $this->assertNull($exception->getResponse());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
