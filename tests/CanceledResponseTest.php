<?php
namespace GuzzleHttp\Tests\Command;

use GuzzleHttp\Command\CanceledResponse;
use GuzzleHttp\Stream;

/**
 * @covers \GuzzleHttp\Command\CanceledResponse
 */
class CanceledResponseTest extends \PHPUnit_Framework_TestCase
{
    public function testCanSetEffectiveUrl()
    {
        $c = new CanceledResponse();
        $c->setEffectiveUrl('foo');
        $this->assertEquals('foo', $c->getEffectiveUrl());
    }

    public function testReturnsDummyData()
    {
        $c = new CanceledResponse();
        $this->assertSame('000', $c->getStatusCode());
        $this->assertSame('CANCELED', $c->getReasonPhrase());
        $this->assertSame('', (string) $c);
        $this->assertSame(null, $c->getProtocolVersion());
        $this->assertSame(null, $c->getBody());
        $this->assertSame([], $c->getHeaders());
        $this->assertSame('', $c->getHeader('foo'));
        $this->assertSame([], $c->getHeader('foo', true));
        $this->assertSame(false, $c->hasHeader('foo'));
    }

    public function immutableProvider()
    {
        return [
            ['setBody', Stream\create('foo')],
            ['removeHeader', 'abc'],
            ['addHeader', 'abc', '123'],
            ['addHeaders', []],
            ['setHeader', 'foo', 'bar'],
            ['setHeaders', []],
            ['json'],
            ['xml']
        ];
    }

    /**
     * @expectedException \RuntimeException
     * @dataProvider immutableProvider
     */
    public function testIsImmutable($meth)
    {
        $args = func_get_args();
        array_shift($args);
        $c = new CanceledResponse();
        call_user_func_array([$c, $meth], $args);
    }
}
