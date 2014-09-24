<?php
namespace GuzzleHttp\Command;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Collection;
use GuzzleHttp\Command\Event\InitEvent;
use GuzzleHttp\Command\Event\PreparedEvent;
use GuzzleHttp\Command\Event\ProcessEvent;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Event\EndEvent;
use GuzzleHttp\Event\HasEmitterTrait;
use GuzzleHttp\Message\FutureResponse;
use GuzzleHttp\Pool;
use GuzzleHttp\Ring\Core;
use GuzzleHttp\Ring\Future;
use GuzzleHttp\Ring\FutureInterface;
use GuzzleHttp\Ring\FutureValue;
use GuzzleHttp\Event\RequestEvents;
use GuzzleHttp\Message\RequestInterface;

/**
 * Abstract client implementation that provides a basic implementation of
 * several methods. Concrete implementations may choose to extend this class
 * or to completely implement all of the methods of ServiceClientInterface.
 */
abstract class AbstractClient implements ServiceClientInterface
{
    use HasEmitterTrait;

    /** @var ClientInterface HTTP client used to send requests */
    private $client;

    /** @var Collection Service client configuration data */
    private $config;

    /**
     * The default client constructor is responsible for setting private
     * properties on the client and accepts an associative array of
     * configuration parameters:
     *
     * - defaults: Associative array of default command parameters to add to
     *   each command created by the client.
     * - emitter: (internal only) A custom event emitter to use with the client.
     *
     * Concrete implementations may choose to support additional configuration
     * settings as needed.
     *
     * @param ClientInterface $client Client used to send HTTP requests
     * @param array           $config Client configuration options
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        ClientInterface $client,
        array $config = []
    ) {
        $this->client = $client;

        // Ensure the defaults key is an array so we can easily merge later.
        if (!isset($config['defaults'])) {
            $config['defaults'] = [];
        }

        if (isset($config['emitter'])) {
            $this->emitter = $config['emitter'];
            unset($config['emitter']);
        }

        $this->config = new Collection($config);
    }

    public function __call($name, array $arguments)
    {
        return $this->execute(
            $this->getCommand(
                $name,
                isset($arguments[0]) ? $arguments[0] : []
            )
        );
    }

    public function execute(CommandInterface $command)
    {
        $trans = $this->initTransaction($command);

        if ($trans->result !== null) {
            return $trans->result;
        }

        $trans->response = $this->client->send($trans->request);

        return $trans->response instanceof FutureResponse
            ? $this->createFutureResult($trans)
            : $trans->result;
    }

    public function executeAll($commands, array $options = [])
    {
        $pool = new Pool(
            $this->client,
            new CommandToRequestIterator(
                function (CommandInterface $command) {
                    $trans = $this->initTransaction($command);
                    return [
                        'request' => $trans->request,
                        'result'  => $trans->result
                    ];
                },
                $commands,
                $options
            ),
            isset($options['pool_size']) ? ['pool_size' => $options['pool_size']] : []
        );

        $pool->deref();
    }

    public function getHttpClient()
    {
        return $this->client;
    }

    public function getConfig($keyOrPath = null)
    {
        if ($keyOrPath === null) {
            return $this->config->toArray();
        }

        if (strpos($keyOrPath, '/') === false) {
            return $this->config[$keyOrPath];
        }

        return $this->config->getPath($keyOrPath);
    }

    public function setConfig($keyOrPath, $value)
    {
        $this->config->setPath($keyOrPath, $value);
    }

    public function createCommandException(CommandTransaction $transaction)
    {
        $cn = 'GuzzleHttp\\Command\\Exception\\CommandException';

        // Don't continuously wrap the same exceptions.
        if ($transaction->exception instanceof CommandException) {
            return $transaction->exception;
        }

        if ($transaction->response) {
            $statusCode = (string) $transaction->response->getStatusCode();
            if ($statusCode[0] == '4') {
                $cn = 'GuzzleHttp\\Command\\Exception\\CommandClientException';
            } elseif ($statusCode[0] == '5') {
                $cn = 'GuzzleHttp\\Command\\Exception\\CommandServerException';
            }
        }

        return new $cn(
            "Error executing command: " . $transaction->exception->getMessage(),
            $transaction,
            $transaction->exception
        );
    }

    /**
     * Prepares a request for the command.
     *
     * @param CommandTransaction $trans Command and context to serialize.
     *
     * @return RequestInterface
     */
    abstract protected function serializeRequest(CommandTransaction $trans);

    /**
     * Creates a future result for a given command transaction.
     *
     * This method really should beoverridden in subclasses to implement custom
     * future response results.
     *
     * @param CommandTransaction $transaction
     *
     * @return FutureInterface
     */
    protected function createFutureResult(CommandTransaction $transaction)
    {
        $response = $transaction->response;
        return new FutureValue(
            // Deref function derefs the response which populates the result.
            function () use ($transaction, $response) {
                $transaction->response = $response->deref();
                return $transaction->result;
            },
            // Cancel function just proxies to the response's cancel function.
            function () use ($transaction) {
                return $transaction->response->cancel();
            }
        );
    }

    /**
     * Initialize a transaction for a command and send the prepare event.
     *
     * @param CommandInterface $command Command to associate with the trans.
     *
     * @return CommandTransaction
     */
    protected function initTransaction(CommandInterface $command)
    {
        $trans = new CommandTransaction($this, $command);
        $command->getEmitter()->emit('init', new InitEvent($trans));
        $request = $this->serializeRequest($trans);
        $trans->request = $request;

        if ($future = $command->getFuture()) {
            $request->getConfig()->set('future', $future);
        }

        $trans->state = 'prepared';
        $prep = new PreparedEvent($trans);
        $command->getEmitter()->emit('prepared', $prep);

        // Finish the command events now if the prepare event was intercepted.
        if ($prep->isPropagationStopped()) {
            $this->emitProcess($trans);
            return $trans;
        }

        $trans->state = 'executing';

        // When a request completes, process the request at the command
        // layer.
        $trans->request->getEmitter()->on(
            'end',
            function (EndEvent $e) use ($trans) {
                $trans->response = $e->getResponse();
                $trans->exception = $e->getException();

                if (!$trans->exception) {
                    $needsCleanup = false;
                } else {
                    $needsCleanup = true;
                    $trans->exception = $this->createCommandException($trans);
                }

                $this->emitProcess($trans);

                if ($needsCleanup) {
                    // If no exception was thrown while finishing the command,
                    // then the command completed successfully. Cleanup the
                    // request FSM if the request had an exception.
                    RequestEvents::stopException($e);
                }

            }, RequestEvents::LATE
        );

        return $trans;
    }

    /**
     * Finishes the process event for the command.
     */
    private function emitProcess(CommandTransaction $trans)
    {
        $trans->state = 'process';

        try {
            // Emit the final "process" event for the command.
            $trans->command->getEmitter()->emit(
                'process', new ProcessEvent($trans)
            );
        } catch (\Exception $ex) {
            // Override any previous exception with the most recent exception.
            $trans->exception = $ex;
        }

        $trans->state = 'end';

        // If the transaction still has the exception, then throw it.
        if ($trans->exception) {
            throw $this->createCommandException($trans);
        }
    }
}
