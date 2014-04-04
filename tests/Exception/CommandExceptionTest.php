<?php

namespace GuzzleHttp\Tests\Command\CommandException;

use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Message\Response;

/**
 * @covers \GuzzleHttp\Command\Exception\CommandException
 */
class CommandExceptionTest extends \PHPUnit_Framework_TestCase
{
    public function testHasData()
    {
        $request = new Request('GET', 'http://httbin.org');
        $response = new Response(200);
        $previous = new \Exception('bar');
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        $command = $this->getMockForAbstractClass('GuzzleHttp\\Command\\CommandInterface');
        $e = new CommandException(
            'foo',
            $client,
            $command,
            $request,
            $response,
            $previous,
            ['test' => '123']
        );
        $this->assertSame($client, $e->getClient());
        $this->assertSame($command, $e->getCommand());
        $this->assertSame($previous, $e->getPrevious());
        $this->assertSame($request, $e->getRequest());
        $this->assertSame($response, $e->getResponse());
        $this->assertEquals('123', $e->getContext()['test']);
    }
}
