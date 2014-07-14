<?php
namespace GuzzleHttp\Tests\Command\CommandException;

use GuzzleHttp\Command\Exception\CommandException;

/**
 * @covers \GuzzleHttp\Command\Exception\CommandException
 */
class CommandExceptionTest extends \PHPUnit_Framework_TestCase
{
    public function testHasTransaction()
    {
        $trans = $this->getMockBuilder('GuzzleHttp\Command\CommandTransaction')
            ->disableOriginalConstructor()
            ->getMock();
        $previous = new \Exception('bar');
        $e = new CommandException('foo', $trans, $previous);
        $this->assertSame($trans, $e->getTransaction());
        $this->assertSame($previous, $e->getPrevious());
    }
}
