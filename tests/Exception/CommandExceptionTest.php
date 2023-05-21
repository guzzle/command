<?php

namespace GuzzleHttp\Tests\Command\CommandException;

use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Exception\CommandClientException;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Command\Exception\CommandServerException;
use GuzzleHttp\Exception\RequestException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @covers \GuzzleHttp\Command\Exception\CommandException
 */
class CommandExceptionTest extends TestCase
{
    public function testCanGetDataFromException()
    {
        $command = $this->getMockForAbstractClass(CommandInterface::class);
        $request = $this->getMockForAbstractClass(RequestInterface::class);
        $response = $this->getMockForAbstractClass(ResponseInterface::class);

        $exception = new CommandException('error', $command, null, $request, $response);
        $this->assertSame($command, $exception->getCommand());
        $this->assertSame($request, $exception->getRequest());
        $this->assertSame($response, $exception->getResponse());
    }

    public function testFactoryReturnsExceptionIfAlreadyCommandException()
    {
        $command = $this->getMockForAbstractClass(CommandInterface::class);
        $previous = CommandException::fromPrevious($command, new \Exception());

        $exception = CommandException::fromPrevious($command, $previous);
        $this->assertSame($previous, $exception);
    }

    public function testFactoryReturnsClientExceptionFor400LevelStatusCode()
    {
        $command = $this->getMockForAbstractClass(CommandInterface::class);
        $request = $this->getMockForAbstractClass(RequestInterface::class);
        $response = $this->getMockForAbstractClass(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(404);
        $previous = new RequestException('error', $request, $response);

        $exception = CommandException::fromPrevious($command, $previous);
        $this->assertInstanceOf(CommandClientException::class, $exception);
    }

    public function testFactoryReturnsServerExceptionFor500LevelStatusCode()
    {
        $command = $this->getMockForAbstractClass(CommandInterface::class);
        $request = $this->getMockForAbstractClass(RequestInterface::class);
        $response = $this->getMockForAbstractClass(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(500);
        $previous = new RequestException('error', $request, $response);

        $exception = CommandException::fromPrevious($command, $previous);
        $this->assertInstanceOf(CommandServerException::class, $exception);
    }
}
