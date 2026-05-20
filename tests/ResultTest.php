<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Command;

use GuzzleHttp\Command\Result;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GuzzleHttp\Command\Result
 * @covers \GuzzleHttp\Command\HasDataTrait
 */
class ResultTest extends TestCase
{
    public function testHasData()
    {
        $c = new Result(['baz' => 'bar']);
        $this->assertSame('bar', $c['baz']);
        $this->assertSame(['baz' => 'bar'], $c->toArray());
        $this->assertTrue(isset($c['baz']));
        $c['fizz'] = 'buzz';
        $this->assertCount(2, $c);
        unset($c['fizz']);
        $this->assertCount(1, $c);
        $this->assertInstanceOf('Traversable', $c->getIterator());
        $this->assertStringContainsString('bar', (string) $c);
    }

    public function testNullOffsetUsesEmptyStringKey()
    {
        $c = new Result(['' => 'bar']);
        $this->assertTrue(isset($c[null]));
        $this->assertSame('bar', $c[null]);

        $c[null] = 'baz';
        $this->assertSame(['' => 'baz'], $c->toArray());

        unset($c[null]);
        $this->assertSame([], $c->toArray());
    }

    public function testAppendSyntaxUsesEmptyStringKey()
    {
        $c = new Result();
        $c[] = 'bar';

        $this->assertSame(['' => 'bar'], $c->toArray());
    }
}
