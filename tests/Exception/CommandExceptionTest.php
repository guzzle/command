<?php
namespace GuzzleHttp\Tests\Command\CommandException;

use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\CommandTransaction;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Message\Request;

/**
 * @covers \GuzzleHttp\Command\Exception\CommandException
 */
class CommandExceptionTest extends \PHPUnit_Framework_TestCase
{
    public function testHasTransaction()
    {
        $client = $this->getMockForAbstractClass('GuzzleHttp\Command\ServiceClientInterface');
        $command = new Command('foo');
        $trans = new CommandTransaction($client, $command, new Request('GET', 'http://www.foo.bar'));
        $trans->context['foo'] = 'bar';
        $previous = new \Exception('bar');
        $e = new CommandException('foo', $trans, $previous);
        $this->assertSame($trans, $e->getTransaction());
        $this->assertSame($client, $e->getClient());
        $this->assertSame($command, $e->getCommand());
        $this->assertSame($trans->request, $e->getRequest());
        $this->assertEquals('bar', $e->getContext()->get('foo'));
        $this->assertSame($previous, $e->getPrevious());

        $this->assertNull($e->getResult());
        $trans->result = 'foo';
        $this->assertSame('foo', $e->getResult());

        // Ensure the request and response are the original values that
        // caused the exception.
        $trans->request = clone $trans->request;
        $this->assertNotSame($trans->request, $e->getRequest());
    }
}
