<?php
namespace GuzzleHttp\Command\Subscriber;

use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Event\CommandErrorEvent;
use GuzzleHttp\Command\Event\PrepareEvent;
use GuzzleHttp\Command\Event\ProcessEvent;
use GuzzleHttp\Command\ModelInterface;
use GuzzleHttp\Command\ServiceClientInterface;
use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Event\EventInterface;
use GuzzleHttp\Event\RequestEvents;
use GuzzleHttp\Event\SubscriberInterface;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Message\ResponseInterface;

/**
 * Provides debug information about operations, including HTTP wire traces.
 *
 * This subscriber is useful for debugging the command and request event
 * system and seeing what data was sent and received over the wire.
 */
class Debug implements SubscriberInterface
{
    private $timers = [];
    private $http;

    /**
     * The constructor accepts a hash of debug options.
     *
     * - output: Where debug data is written
     * - http: Set to false to not display debug HTTP event data
     *
     * @param array $options Hash of debug options
     */
    public function __construct(array $options = [])
    {
        $this->output = isset($options['output'])
            ? $options['output']
            : fopen('php://output', 'w');

        $this->http = isset($options['http']) ? $options['http'] : true;
    }

    public function getEvents()
    {
        return [
            'prepare' => [
                ['beforePrepare', 'first'],
                ['afterPrepare', 'last']
            ],
            'process' => [
                ['beforeProcess', 'first'],
                ['afterProcess', 'last']
            ],
            'error' => [
                ['beforeError', 'first'],
                ['afterError', 'last']
            ]
        ];
    }

    private function write($text)
    {
        fwrite($this->output, date('c') . ': ' . $text . PHP_EOL);
    }

    private function startTimer($hash)
    {
        $this->timers[$hash] = microtime(true);
    }

    private function stopTimer($hash)
    {
        if (isset($this->timers[$hash])) {
            $result = microtime(true) - $this->timers[$hash];
            unset($this->timers[$hash]);
            return $result;
        }

        return -1;
    }

    private function cmdDsc(
        CommandInterface $command,
        ServiceClientInterface $client
    ) {
        return spl_object_hash($command)
            . ': ' . get_class($client) . '::'
            . $command->getName() . "\nParams: "
            . json_encode($command->toArray(), JSON_PRETTY_PRINT);
    }

    private function startEvent($name, $event, $extra = null)
    {
        $hash = $name . spl_object_hash($event);
        $this->startTimer($hash);

        if ($extra) {
            $extra = PHP_EOL . $extra;
        }

        $this->write(sprintf(
            "Starting the %s event: %s%s",
            $name,
            $this->cmdDsc($event->getCommand(), $event->getClient()),
            $extra
        ));
    }

    private function endEvent($name, $event, $extra = null)
    {
        $hash = $name . spl_object_hash($event);
        if ($extra) {
            $extra = PHP_EOL . $extra;
        }

        $this->write(sprintf(
            "Done with the %s event (took %f seconds): %s%s",
            $name,
            $this->stopTimer($hash),
            $this->cmdDsc($event->getCommand(), $event->getClient()),
            $extra
        ));

        return $this->stopTimer($hash);
    }

    public function beforePrepare(PrepareEvent $e)
    {
        $this->startTimer(spl_object_hash($e->getCommand()));
        $this->write('Starting command ' . str_repeat('>', 50));
        $this->startEvent('command:before', $e);
    }

    public function afterPrepare(PrepareEvent $e)
    {
        $this->endEvent('command:before', $e);
        $request = $e->getRequest();

        if (!$this->http || !$request) {
            return;
        }

        $request->getConfig()->set('debug', true);

        $before = function ($before) use ($e) {
            $this->startEvent(
                $this->getEventName($before),
                $e,
                $this->getRequestExtraText($before)
            );
        };

        $after = function ($after) use ($e) {
            $this->endEvent(
                $this->getEventName($after),
                $e,
                $this->getRequestExtraText($after)
            );
        };

        foreach (['before', 'complete', 'error'] as $event) {
            $request->getEmitter()->on($event, $before, RequestEvents::EARLY);
            $request->getEmitter()->on($event, $after, RequestEvents::LATE);
        }
    }

    public function beforeProcess(ProcessEvent $e)
    {
        $this->startEvent('command:process', $e);
    }

    public function afterProcess(ProcessEvent $e)
    {
        $result = $e->getResult();
        $extra = $result instanceof ModelInterface
            ? "\nResult: " . json_encode($result->toArray(), JSON_PRETTY_PRINT)
            : '' ;

        $time = $this->stopTimer(spl_object_hash($e->getCommand()));
        $this->endEvent('command:process', $e, $extra);

        if ($time != -1) {
            $this->write(sprintf(
                "Sending the following command took %f seconds: %s",
                $time,
                $this->cmdDsc($e->getCommand(), $e->getClient())
            ));
        }

        $this->write('End command      ' . str_repeat('<', 50));
    }

    public function beforeError(CommandErrorEvent $e)
    {
        $this->startEvent('command:error', $e, 'Error: ' . $e->getException());
    }

    public function afterError(CommandErrorEvent $e)
    {
        $this->endEvent(
            'command:error',
            $e,
            $e->isPropagationStopped()
                ? 'The error propagation was stopped'
                : 'The error propagation was not stopped'
        );

        $this->write('End error' . str_repeat('!', 50));
    }

    private function getEventName($event)
    {
        $cl = get_class($event);
        $name = strtolower(substr($cl, strrpos($cl, '\\') + 1));

        return 'request:' . substr($name, 0, -5);
    }

    private function getRequestExtraText(EventInterface $e)
    {
        $extra = ($e instanceof ErrorEvent)
            ? 'Error: ' . $e->getException()
            : '';

        if ($e->getRequest()) {
            $extra .= PHP_EOL
                . 'Request: ' . PHP_EOL
                . $this->getRequestHeaders($e->getRequest())
                . PHP_EOL;
        }

        if (method_exists($e, 'getResponse') && $e->getResponse()) {
            $extra .= PHP_EOL
                . 'Response: ' . PHP_EOL
                . $this->getResponseHeaders($e->getResponse())
                . PHP_EOL;
        }

        return $extra;
    }

    private function getRequestHeaders(RequestInterface $request)
    {
        return $request->getMethod() . ' ' . $request->getResource()
            . ' HTTP/' . $request->getProtocolVersion() . "\r\n"
            . $this->getHeaderString($request->getHeaders());
    }

    private function getResponseHeaders(ResponseInterface $response)
    {
        return 'HTTP/' . $response->getProtocolVersion() . ' '
            . $response->getStatusCode()
            . ' ' . $response->getReasonPhrase() . "\r\n"
            . $this->getHeaderString($response->getHeaders());
    }

    private function getHeaderString(array $headers)
    {
        $result = '';

        foreach ($headers as $name => $values) {
            $result .= $name . ': ' . implode(', ', $values) . "\r\n";
        }

        return $result;
    }
}
