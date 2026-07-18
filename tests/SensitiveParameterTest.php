<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Command;

use GuzzleHttp\ClientInterface as HttpClientInterface;
use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Command\Result;
use GuzzleHttp\Command\ResultInterface;
use GuzzleHttp\Command\ServiceClient;
use GuzzleHttp\Command\ServiceClientInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @covers \GuzzleHttp\Command\ServiceClient
 * @covers \GuzzleHttp\Command\Exception\CommandException
 */
class SensitiveParameterTest extends TestCase
{
    public function testSourceContainsExactSensitiveParameterCount(): void
    {
        self::assertSame(13, self::countSourceAttributes(__DIR__.'/../src'));
    }

    public function testSensitiveParameterInventory(): void
    {
        $attributeCount = 0;
        foreach (self::inventory() as $entry) {
            list($class, $method, $sensitiveParameters) = $entry;
            $reflection = new \ReflectionMethod($class, $method);

            if (\PHP_VERSION_ID < 80000) {
                $parameterNames = array_map(static function (\ReflectionParameter $parameter): string {
                    return $parameter->getName();
                }, $reflection->getParameters());
                foreach ($sensitiveParameters as $sensitiveParameter) {
                    self::assertContains($sensitiveParameter, $parameterNames);
                }
                $attributeCount += count($sensitiveParameters);

                continue;
            }

            foreach ($reflection->getParameters() as $parameter) {
                $attributes = $parameter->getAttributes(\SensitiveParameter::class);
                $expected = in_array($parameter->getName(), $sensitiveParameters, true)
                    ? 1
                    : 0;

                self::assertCount($expected, $attributes, $class.'::'.$method.'::$'.$parameter->getName());
                foreach ($attributes as $attribute) {
                    self::assertInstanceOf(\SensitiveParameter::class, $attribute->newInstance());
                }
                $attributeCount += count($attributes);
            }
        }

        self::assertSame(12, $attributeCount);
    }

    public function testCommandHandlerClosureParameterIsSensitive(): void
    {
        $client = new ServiceClient(
            $this->createMock(HttpClientInterface::class),
            static function (CommandInterface $command): RequestInterface {
                return new Request('GET', '/');
            },
            static function (
                ResponseInterface $response,
                RequestInterface $request,
                CommandInterface $command
            ): ResultInterface {
                return new Result();
            }
        );
        $factory = new \ReflectionMethod(ServiceClient::class, 'createCommandHandler');
        $factory->setAccessible(true);
        $handler = $factory->invoke($client);
        self::assertInstanceOf(\Closure::class, $handler);

        $parameters = (new \ReflectionFunction($handler))->getParameters();
        self::assertCount(1, $parameters);
        self::assertSame('command', $parameters[0]->getName());
        if (\PHP_VERSION_ID < 80000) {
            return;
        }
        self::assertCount(1, $parameters[0]->getAttributes(\SensitiveParameter::class));
    }

    public function testInterfacesAndAssignmentOnlyConstructorAreNotAnnotated(): void
    {
        if (\PHP_VERSION_ID < 80000) {
            self::markTestSkipped('Attributes are not reflected before PHP 8.0.');
        }

        foreach ((new \ReflectionClass(ServiceClientInterface::class))->getMethods() as $method) {
            foreach ($method->getParameters() as $parameter) {
                self::assertCount(0, $parameter->getAttributes(\SensitiveParameter::class));
            }
        }

        $constructor = new \ReflectionMethod(Command::class, '__construct');
        self::assertCount(0, $constructor->getParameters()[1]->getAttributes(\SensitiveParameter::class));
    }

    public function testResponseTransformerArgumentsAreRedactedFromTrace(): void
    {
        $this->requireNativeTraceRedaction();

        $client = new ServiceClient(
            $this->createMock(HttpClientInterface::class),
            static function (CommandInterface $command): RequestInterface {
                return new Request('GET', '/');
            },
            static function (
                ResponseInterface $response,
                RequestInterface $request,
                CommandInterface $command
            ): ResultInterface {
                throw new \RuntimeException('transform failed');
            }
        );
        $method = new \ReflectionMethod(ServiceClient::class, 'transformResponseToResult');
        $method->setAccessible(true);

        $previous = ini_get('zend.exception_ignore_args');
        try {
            self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
            $method->invoke(
                $client,
                new Response(500),
                new Request('GET', '/?api_key=request-secret'),
                new Command('GetSecret', ['api_key' => 'command-secret'])
            );
            self::fail('Expected the response transformer to throw.');
        } catch (\RuntimeException $exception) {
            $frame = self::findFrame($exception, ServiceClient::class, 'transformResponseToResult');
            self::assertCount(3, $frame['args']);
            foreach ($frame['args'] as $argument) {
                self::assertInstanceOf(\SensitiveParameterValue::class, $argument);
            }
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
    }

    public function testCommandExceptionFactoryArgumentsAreRedactedFromTrace(): void
    {
        $this->requireNativeTraceRedaction();

        $command = $this->createMock(CommandInterface::class);
        $previousException = new class($command) extends CommandException {
            public function __construct(CommandInterface $command)
            {
                parent::__construct('previous-secret', $command);
            }

            public function getCommand(): CommandInterface
            {
                throw new \RuntimeException('command access failed');
            }
        };

        $previous = ini_get('zend.exception_ignore_args');
        try {
            self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
            CommandException::fromPrevious($command, $previousException);
            self::fail('Expected command name access to throw.');
        } catch (\RuntimeException $exception) {
            $frame = self::findFrame($exception, CommandException::class, 'fromPrevious');
            self::assertCount(2, $frame['args']);
            self::assertInstanceOf(\SensitiveParameterValue::class, $frame['args'][0]);
            self::assertInstanceOf(\SensitiveParameterValue::class, $frame['args'][1]);
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
    }

    /**
     * @return array<int, array{class-string, string, string[]}>
     */
    private static function inventory(): array
    {
        return [
            [ServiceClient::class, 'getCommand', ['params']],
            [ServiceClient::class, 'execute', ['command']],
            [ServiceClient::class, 'executeAsync', ['command']],
            [ServiceClient::class, 'executeAll', ['commands']],
            [ServiceClient::class, 'executeAllAsync', ['commands']],
            [ServiceClient::class, '__call', ['args']],
            [ServiceClient::class, 'transformCommandToRequest', ['command']],
            [ServiceClient::class, 'transformResponseToResult', ['response', 'request', 'command']],
            [CommandException::class, 'fromPrevious', ['command', 'prev']],
        ];
    }

    private function requireNativeTraceRedaction(): void
    {
        if (\PHP_VERSION_ID < 80200) {
            self::markTestSkipped('Native trace redaction requires PHP 8.2.');
        }

        $previous = ini_get('zend.exception_ignore_args');
        if (ini_set('zend.exception_ignore_args', '0') === false) {
            self::markTestSkipped('Trace arguments cannot be enabled.');
        }
        if ($previous !== false) {
            ini_set('zend.exception_ignore_args', $previous);
        }
    }

    private static function countSourceAttributes(string $directory): int
    {
        $count = 0;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            self::assertNotFalse($contents);
            $count += substr_count($contents, '#[\\SensitiveParameter]');
        }

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    private static function findFrame(\Throwable $exception, string $class, string $function): array
    {
        foreach ($exception->getTrace() as $frame) {
            if (($frame['class'] ?? null) === $class && ($frame['function'] ?? null) === $function) {
                return $frame;
            }
        }

        self::fail('Unable to find '.$class.'::'.$function.' in the exception trace.');
    }
}
