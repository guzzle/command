<?php
namespace GuzzleHttp\Tests\Command;

use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\CommandTransaction;
use GuzzleHttp\Message\Request;

class CommandTransactionTest extends \PHPUnit_Framework_TestCase
{
    public function testHasData()
    {
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        $command = new Command('foo', []);
        $req = new Request('GET', 'http://foo.com');
        $trans = new CommandTransaction($client, $command, ['foo' => 'bar']);
        $trans->request = $req;
        $this->assertSame($req, $trans->request);
        $this->assertSame($client, $trans->serviceClient);
        $this->assertSame($command, $trans->command);
        $this->assertSame('bar', $trans->context->get('foo'));
        $this->assertNull($trans->result);
        $this->assertNull($trans->response);
        $this->assertSame($req, $trans->request);
        $this->assertNull($trans->exception);
    }
}
