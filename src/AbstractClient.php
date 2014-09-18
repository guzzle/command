<?php
namespace GuzzleHttp\Command;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Collection;
use GuzzleHttp\Event\HasEmitterTrait;
use GuzzleHttp\Command\Event\CommandEvents;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Ring\Core;
use GuzzleHttp\Ring\FutureInterface;

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
     *
     * Concrete implementations may choose to support additional configuration
     * settings as needed.
     *
     * @param ClientInterface $client Client used to send HTTP requests
     * @param array           $config Client configuration options
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
        $t = CommandEvents::prepareTransaction($this, $command);

        // Note: listeners can intercept the before event and inject a result.
        if ($t->result !== null) {
            return $t->result;
        }

        $t->response = $this->client->send($t->request);

        // Return results immediately when they are not a future.
        return $t->response instanceof FutureInterface
            ? $this->createFutureResult($t)
            : $t->result;
    }

    public function executeAll($commands, array $options = [])
    {
        Utils::createPool($this, $commands, $options)->deref();
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
        RequestException $previous
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
}
