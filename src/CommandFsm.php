<?php
namespace GuzzleHttp\Command;

use GuzzleHttp\Command\Event\CommandBeforeEvent;
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
        parent::__construct('before', [
            // Can intercept with mocked result.
            'before'   => [
                'success'    => 'execute',
                'intercept'  => 'process',
                'error'      => 'error',
                'transition' => [$this, 'beforeTransition']
            ],
            // Transition to exit on success because the process and error
            // events are handled using the event system of the created request
            'execute' => [
                'success'    => 'exit',
                'error'      => 'error',
                'transition' => [$this, 'executeTransition']
            ],
            // Can retry in process which transitions to the "execute" intercept
            'process' => [
                'success'    => 'end',
                'intercept'  => 'before',
                'error'      => 'error',
                'transition' => [$this, 'processTransition']
            ],
            // Can retry in error which transitions to the "execute" intercept
            'error' => [
                'success'    => 'process',
                'intercept'  => 'before',
                'error'      => 'end',
                'transition' => [$this, 'ErrorTransition']
            ],
            'end' => [
                'success'    => 'exit',
                'transition' => [$this, 'endTransition']
            ],
            // The exit state is used to bail from the FSM.
            'exit' => []
        ]);
    }

    /**
     * Note that the before transition can be intercepted with a result.
     */
    protected function beforeTransition(CommandTransaction $trans)
    {
        $event = new CommandBeforeEvent($trans);
        $trans->command->getEmitter()->emit('before', $event);

        return $trans->result !== null;
    }

    /**
     * Sends the prepared HTTP request.
     */
    protected function executeTransition(CommandTransaction $trans)
    {
        $trans->response = $trans->client->send($trans->request);
    }

    /**
     * Emits an error event. If the event propagation is stopped, then
     * transition to the "process" state. If the event is marked for a retry,
     * then transition to the "execute" state. If the exception is not handled,
     * then transition to the "end" state.
     */
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

        // It was intercepted, so remove it from the transaction.
        $trans->exception = null;

        // Manually transition to retry a command if needed. Otherwise, use the
        // transition state described in the constructor. The state is modified
        // using the retry() function of the command "error" event.
        return $trans->state === 'execute';
    }

    /**
     * Emits an event that is used to process the HTTP response associated with
     * the transaction, or just to process a result that was previously
     * associated with the command. The emitted event may be retried, and when
     * it is, transition to the "execute" state. On success transition to "end".
     */
    protected function processTransition(CommandTransaction $trans)
    {
        // Futures will have their own end events emitted when dereferenced.
        if ($trans->result instanceof FutureInterface) {
            return false;
        }

        $trans->command->getEmitter()->emit('process', new ProcessEvent($trans));

        // Manually transition to retry a command if needed. Otherwise, use the
        // transition state described in the constructor. The state is modified
        // using the retry() function of the command "process" event.
        return $trans->state === 'execute';
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
    }

    /**
     * Throws any exceptions when terminating the command execution.
     */
    protected function exitTransition(CommandTransaction $trans)
    {
        if ($trans->exception) {
            throw $trans->exception;
        }
    }
}
