<?php
namespace GuzzleHttp\Command;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Collection;
use GuzzleHttp\Event\HasEmitterTrait;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Command\Event\CommandEvents;

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
        $this->config = new Collection($config);
    }

    public function __call($name, array $arguments)
    {
        return $this->execute(
            $this->getCommand($name, isset($arguments[0]) ? $arguments[0] : [])
        );
    }

    public function execute(CommandInterface $command)
    {
        try {
            $event = CommandEvents::prepare($command, $this);
            // Listeners can intercept the event and inject a result. If that
            // happened, then we must not emit further events and just
            // return the result.
            if (null !== ($result = $event->getResult())) {
                return $result;
            }
            $request = $event->getRequest();
            // Send the request and get the response that is used in the
            // complete event.
            $response = $this->client->send($request);
            // Emit the process event for the command and return the result
            return CommandEvents::process($command, $this, $request, $response);
        } catch (CommandException $e) {
            // Let command exceptions pass through untouched
            throw $e;
        } catch (\Exception $e) {
            // Wrap any other exception in a CommandException so that exceptions
            // thrown from the client are consistent and predictable.
            $msg = 'Error executing command: ' . $e->getMessage();
            throw new CommandException($msg, $this, $command, null, null, $e);
        }
    }

    public function executeAll($commands, array $options = [])
    {
        $requestOptions = [];
        // Move all of the options over that affect the request transfer
        if (isset($options['parallel'])) {
            $requestOptions['parallel'] = $options['parallel'];
        }

        // Create an iterator that yields requests from commands and send all
        $this->client->sendAll(
            new CommandToRequestIterator($commands, $this, $options),
            $requestOptions
        );
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
}
