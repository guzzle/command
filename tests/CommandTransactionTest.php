<?php
namespace GuzzleHttp\Tests\Command;

use GuzzleHttp\Adapter\Transaction;
use GuzzleHttp\Client;
use GuzzleHttp\Command\CommandToRequestIterator;
use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\CommandTransaction;
use GuzzleHttp\Command\Event\CommandErrorEvent;
use GuzzleHttp\Command\Event\PrepareEvent;
use GuzzleHttp\Event\CompleteEvent;
use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Message\Response;

class CommandTransactionTest extends \PHPUnit_Framework_TestCase
{
    public function testHasData()
    {
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        $command = new Command('foo', []);
        $trans = new CommandTransaction($client, $command, ['foo' => 'bar']);
        $this->assertSame($client, $trans->getClient());
        $this->assertSame($command, $trans->getCommand());
        $this->assertSame('bar', $trans->getContext()->get('foo'));
        $this->assertNull($trans->getResult());
        $this->assertNull($trans->getResponse());
        $this->assertNull($trans->getRequest());
        $this->assertNull($trans->getException());
    }

    public function testCanMutateData()
    {
        $client = $this->getMockForAbstractClass('GuzzleHttp\\Command\\ServiceClientInterface');
        $command = new Command('foo', []);
        $trans = new CommandTransaction($client, $command, ['foo' => 'bar']);

        $request = new Request('GET', 'http://foo.com');
        $trans->setRequest($request);
        $this->assertSame($request, $trans->getRequest());

        $response = new Response(200);
        $trans->setResponse($response);
        $this->assertSame($response, $trans->getResponse());

        $trans->setResult('foo');
        $this->assertSame('foo', $trans->getResult());

        $e = new \Exception('foo');
        $trans->setException($e);
        $this->assertSame($e, $trans->getException());
    }
}
