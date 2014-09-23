<?php
namespace GuzzleHttp\Command;

use GuzzleHttp\Exception\StateException;
use GuzzleHttp\Ring\FutureInterface;
use GuzzleHttp\Fsm;
use GuzzleHttp\Command\Event\ProcessEvent;
use GuzzleHttp\Command\Event\CommandErrorEvent;
use GuzzleHttp\Command\Event\CommandEndEvent;

/**
 * Defines the state transitions of a command and its state transitions.
 */
class CommandFsm extends Fsm
{
    public function __construct()
    {
        parent::__construct('process', [
            'process' => [
                'success'    => 'end',
                'error'      => 'error',
                'transition' => [$this, 'processTransition']
            ],
            'error' => [
                'success'    => 'process',
                'error'      => 'end',
                'transition' => [$this, 'ErrorTransition']
            ],
            'end' => ['transition' => [$this, 'endTransition']]
        ]);
    }

    protected function errorTransition(CommandTransaction $trans)
    {
        if (!$trans->exception) {
            throw new StateException('Invalid error state: no exception');
        }

        // Convert exceptions as dictated by the service client.
        $trans->exception =
            $trans->serviceClient->createCommandException($trans, $trans->exception);

        $event = new CommandErrorEvent($trans);
        $trans->command->getEmitter()->emit('error', $event);

        if (!$event->isPropagationStopped()) {
            throw $trans->exception;
        }
    }

    protected function processTransition(CommandTransaction $trans)
    {
        // Futures will have their own end events emitted when dereferenced.
        if ($trans->result instanceof FutureInterface) {
            return;
        }

        $trans->command->getEmitter()->emit('process', new ProcessEvent($trans));
    }

    /**
     * Emits the terminal "end" event. This is the absolute last opportunity to
     * intercept the command transaction with a result. If an exception is
     * still present on the transaction after emitting the "end" event, then
     * the exception is thrown.
     */
    protected function endTransition(CommandTransaction $trans)
    {
        // Futures will have their own "end" events emitted when dereferenced.
        if ($trans->result instanceof FutureInterface) {
            return;
        }

        $trans->command->getEmitter()->emit('end', new CommandEndEvent($trans));

        if ($trans->exception) {
            throw $trans->exception;
        }
    }
}
