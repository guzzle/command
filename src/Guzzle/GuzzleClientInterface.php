<?php

namespace GuzzleHttp\Command\Guzzle;

use GuzzleHttp\Command\ServiceClientInterface;
use GuzzleHttp\Command\Guzzle\Description\GuzzleDescription;

/**
 * Guzzle web service client
 */
interface GuzzleClientInterface extends ServiceClientInterface
{
    /**
     * Returns the service description used by the client
     *
     * @return GuzzleDescription
     */
    public function getDescription();
}
