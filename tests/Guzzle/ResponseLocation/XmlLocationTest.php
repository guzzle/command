<?php

namespace GuzzleHttp\Tests\Command\Guzzle\ResponseLocation;

use GuzzleHttp\Command\Guzzle\Command;
use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Command\Guzzle\Operation;
use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\Guzzle\ResponseLocation\XmlLocation;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;

/**
 * @covers \GuzzleHttp\Command\Guzzle\ResponseLocation\XmlLocation
 */
class XmlLocationTest extends \PHPUnit_Framework_TestCase
{
    public function testVisitsLocation()
    {
        $l = new XmlLocation('xml');
        $operation = new Operation([], new Description([]));
        $command = new Command($operation, []);
        $parameter = new Parameter([
            'name'    => 'val',
            'sentAs'  => 'vim',
            'filters' => ['strtoupper']
        ]);
        $model = new Parameter();
        $response = new Response(200, [], Stream::factory('<w><vim>bar</vim></w>'));
        $result = [];
        $l->before($command, $response, $model, $result);
        $l->visit($command, $response, $parameter, $result);
        $l->after($command, $response, $model, $result);
        $this->assertEquals('BAR', $result['val']);
    }

    public function testVisitsAdditionalProperties()
    {
        $l = new XmlLocation('xml');
        $operation = new Operation([], new Description([]));
        $command = new Command($operation, []);
        $parameter = new Parameter();
        $model = new Parameter(['additionalProperties' => ['location' => 'xml']]);
        $response = new Response(200, [], Stream::factory('<w><vim>bar</vim></w>'));
        $result = [];
        $l->before($command, $response, $parameter, $result);
        $l->visit($command, $response, $parameter, $result);
        $l->after($command, $response, $model, $result);
        $this->assertEquals('bar', $result['vim']);
    }
}
