<?php

namespace GuzzleHttp\Command\Guzzle\ResponseLocation;

use GuzzleHttp\Command\Guzzle\Description\Parameter;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Command\Guzzle\GuzzleCommandInterface;

/**
 * Extracts headers from the response into a result fields
 */
class HeaderLocation extends AbstractLocation
{
    public function visit(
        GuzzleCommandInterface $command,
        ResponseInterface $response,
        Parameter $param,
        &$result,
        array $context = []
    ) {
        // Retrieving a single header by name
        $name = $param->getName();
        if ($header = $response->getHeader($param->getWireName())) {
            $result[$name] = $param->filter($header);
        }
    }

    public function after(
        GuzzleCommandInterface $command,
        ResponseInterface $response,
        Parameter $model,
        &$result,
        array $context = []
    ) {
        $additional = $model->getAdditionalProperties();
        if ($additional instanceof Parameter &&
            $additional->getLocation() == $this->locationName
        ) {
            foreach ($response->getHeaders() as $key => $header) {
                if (!isset($result[$key])) {
                    $result[$key] = $additional->filter(implode($header, ', '));
                }
            }
        }
    }
}
