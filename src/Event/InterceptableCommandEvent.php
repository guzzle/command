<?php
namespace GuzzleHttp\Command\Event;

/**
 * An event in which the result can be injected using the setResult function.
 */
abstract class InterceptableCommandEvent extends AbstractCommandEvent
{
    /**
     * Intercept the event and inject a result. Stop further listeners from
     * being triggered.
     *
     * @param mixed $result Result to associate with the command
     */
    public function setResult($result)
    {
        $this->trans->exception = null;
        $this->trans->result = $result;
        $this->stopPropagation();
    }
}
