<?php

namespace ZBateson\StreamDecorators;

use GuzzleHttp\Psr7;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChunkSplitStream::class)]
#[Group('ChunkSplitStream')]
class ChunkSplitStreamTest extends TestCase
{
    public function testWrite() : void
    {
        $stream = Psr7\Utils::streamFor('');

        $out = new ChunkSplitStream(new NonClosingStream($stream), 10, '|');
        $out->write(\str_repeat('a', 5));
        $out->write(\str_repeat('a', 10));
        $out->write(\str_repeat('a', 4));
        for ($i = 0; $i < 16; ++$i) {
            $out->write('a');
        }
        $out->close();

        $stream->rewind();
        $str = $stream->getContents();
        $arr = \explode('|', $str);

        $this->assertStringEndsWith('|', $str);
        $this->assertCount(5, $arr);
        $this->assertSame(10, \strlen($arr[0]));
        $this->assertSame(10, \strlen($arr[1]));
        $this->assertSame(10, \strlen($arr[2]));
        $this->assertSame(5, \strlen($arr[3]));
        $this->assertEmpty($arr[4]);
    }

    public function testWriteLineEndingAtBoundary() : void
    {
        $stream = Psr7\Utils::streamFor('');

        $out = new ChunkSplitStream(new NonClosingStream($stream), 10, '|');
        for ($i = 0; $i < 20; ++$i) {
            $out->write('a');
        }
        $out->close();

        $stream->rewind();
        $str = $stream->getContents();
        $arr = \explode('|', $str);

        $this->assertStringEndsWith('|', $str);
        $this->assertCount(3, $arr);
        $this->assertSame(10, \strlen($arr[0]));
        $this->assertSame(10, \strlen($arr[1]));
        $this->assertEmpty($arr[2]);
    }
}
