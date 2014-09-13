<?php
namespace GuzzleHttp\Command;

use GuzzleHttp\Ring\FutureInterface;

/**
 * Represents a result to a command that may not have finished sending.
 */
interface FutureModelInterface extends ModelInterface, FutureInterface {}
