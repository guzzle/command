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
        $trans = new CommandTransaction($client, $command);
        $trans->context['foo'] = 'bar';
        $trans->request = new Request('GET', 'http://www.foo.bar');
        $previous = new \Exception('bar');
        $e = new CommandException('foo', $trans, $previous);
        $this->assertSame($trans, $e->getTransaction());
        $this->assertSame($command, $e->getCommand());
        $this->assertSame($trans->request, $e->getRequest());
        $this->assertEquals('bar', $e->getContext()->get('foo'));
        $this->assertSame($previous, $e->getPrevious());
    }
}
