<?php
namespace GuzzleHttp\Tests\Command;

use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\CommandTransaction;

class CommandTransactionTest extends \PHPUnit_Framework_TestCase
{
    public function testHasData()
    {
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        $command = new Command('foo', []);
        $trans = new CommandTransaction($client, $command, ['foo' => 'bar']);
        $this->assertSame($client, $trans->client);
        $this->assertSame($command, $trans->command);
        $this->assertSame('bar', $trans->context->get('foo'));
        $this->assertNull($trans->result);
        $this->assertNull($trans->response);
        $this->assertNull($trans->request);
        $this->assertNull($trans->commandException);
    }
}
