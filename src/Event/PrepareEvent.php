<?php
namespace GuzzleHttp\Command\Event;

/**
 * Event emitted when a command is being prepared.
 *
 * Event listeners can inject a {@see GuzzleHttp\Message\RequestInterface}
 * object onto the event to be used as the request sent over the wire.
 */
class PrepareEvent extends AbstractCommandEvent {}
