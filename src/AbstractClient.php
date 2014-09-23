<?php
namespace GuzzleHttp\Command;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Collection;
use GuzzleHttp\Command\Event\PrepareEvent;
use GuzzleHttp\Event\EndEvent;
use GuzzleHttp\Event\HasEmitterTrait;
use GuzzleHttp\Ring\Core;
use GuzzleHttp\Ring\FutureInterface;
use GuzzleHttp\Fsm;
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

    /** @var Fsm */
    private $fsm;

    /**
     * The default client constructor is responsible for setting private
     * properties on the client and accepts an associative array of
     * configuration parameters:
     *
     * - defaults: Associative array of default command parameters to add to
     *   each command created by the client.
     * - fsm: (internal only) The state machine used to transition commands.
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

        if (!isset($config['fsm'])) {
            $this->fsm = new CommandFsm();
        } else {
            $this->fsm = $config['fsm'];
            unset($config['fsm']);
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

    public function buildRequest(CommandInterface $command)
    {
        $request = $this->serializeRequest($command);
        $trans = new CommandTransaction($this, $command, $request);
        $this->bridgeHttp($trans);

        return $request;
    }

    public function execute(CommandInterface $command)
    {
        $request = $this->serializeRequest($command);
        $trans = new CommandTransaction($this, $command, $request);
        $this->bridgeHttp($trans);
        $this->fsm->run($trans);

        return $trans->result;
    }

    public function executeAll($commands, array $options = [])
    {
        CommandUtils::createPool($this, $commands, $options)->deref();
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

    public function createCommandException(
        CommandTransaction $transaction,
        \Exception $previous
    ) {
        $cn = 'GuzzleHttp\\Command\\Exception\\CommandException';

        if ($transaction->response) {
            $statusCode = (string) $transaction->response->getStatusCode();
            if ($statusCode[0] == '4') {
                $cn = 'GuzzleHttp\\Command\\Exception\\CommandClientException';
            } elseif ($statusCode[0] == '5') {
                $cn = 'GuzzleHttp\\Command\\Exception\\CommandServerException';
            }
        }

        return new $cn(
            "Error executing command: " . $previous->getMessage(),
            $transaction,
            $previous
        );
    }

    /**
     * Prepares a request for the command.
     *
     * @param CommandInterface $command Command to serialize.
     *
     * @return RequestInterface
     */
    abstract protected function serializeRequest(CommandInterface $command);

    /**
     * Creates a future result for a given command transaction.
     *
     * This method may be overridden in subclasses to implement custom
     * future response results.
     *
     * @param CommandTransaction $transaction
     *
     * @return FutureInterface
     * @throws \RuntimeException if the response associated with the command
     *                           transaction is not a FutureInterface.
     */
    protected function createFutureResult(CommandTransaction $transaction)
    {
        if (!($transaction->response instanceof FutureInterface)) {
            throw new \RuntimeException('Must be a FutureInterface. Found '
                . Core::describeType($transaction->response));
        }

        return new FutureModel(
            // Deref function derefs the response which populates the result.
            function () use ($transaction) {
                $transaction->response = $transaction->response->deref();
                return $transaction->result;
            },
            // Cancel function just proxies to the response's cancel function.
            function () use ($transaction) {
                return $transaction->response->cancel();
            }
        );
    }

    /**
     * Bridges the HTTP event loop with the command event loop.
     *
     * @param CommandTransaction $trans
     */
    private function bridgeHttp(CommandTransaction $trans)
    {
        if ($future = $trans->command->getFuture()) {
            $trans->request->getConfig()->set('future', $future);
        }

        $trans->command->getEmitter()->emit('prepare', new PrepareEvent($trans));
        $trans->state = 'before';
        $trans->request->getEmitter()->on(
            'end',
            function (EndEvent $e) use ($trans) {
                $trans->request = $e->getRequest();
                $trans->response = $e->getResponse();

                // Transition based on if an exception was encountered.
                if ($ex = $e->getException()) {
                    $trans->exception = $ex;
                    $trans->state = 'error';
                    $needsCleanup = true;
                } else {
                    $trans->state = 'process';
                    $needsCleanup = false;
                }

                // Finish the command FSM in the request end event. If an
                // exception is thrown for the command, then that is thrown
                // in the request layer as-is because CommandException
                // extends from RequestException.
                $this->fsm->run($trans);

                // If no exception was thrown while finishing the command,
                // then the command completed successfully. Cleanup the
                // request FSM if the request had an exception.
                if ($needsCleanup) {
                    // Prevent request state exception by intercepting the
                    // request "end" event with a future response.
                    RequestEvents::stopException($e);
                }
            }
        );
    }
}
