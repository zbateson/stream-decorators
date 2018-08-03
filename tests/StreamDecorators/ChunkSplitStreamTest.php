<?php
namespace ZBateson\StreamDecorators;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Psr7;

/**
 * Description of ChunkSplitStreamTest
 *
 * @group ChunkSplitStream
 * @covers ZBateson\StreamDecorators\ChunkSplitStream
 * @author Zaahid Bateson
 */
class ChunkSplitStreamTest extends TestCase
{
    public function testWrite()
    {
        $stream = Psr7\stream_for('');

        $out = new ChunkSplitStream(new NonClosingStream($stream), 10, '|');
        $out->write(str_repeat('a', 5));
        $out->write(str_repeat('a', 10));
        $out->write(str_repeat('a', 4));
        for ($i = 0; $i < 16; ++$i) {
            $out->write('a');
        }
        $out->close();

        $stream->rewind();
        $str = $stream->getContents();
        $arr = explode('|', $str);

        $this->assertStringEndsWith('|', $str);
        $this->assertCount(5, $arr);
        $this->assertEquals(10, strlen($arr[0]));
        $this->assertEquals(10, strlen($arr[1]));
        $this->assertEquals(10, strlen($arr[2]));
        $this->assertEquals(5, strlen($arr[3]));
        $this->assertEmpty($arr[4]);
    }

    public function testWriteLineEndingAtBoundary()
    {
        $stream = Psr7\stream_for('');

        $out = new ChunkSplitStream(new NonClosingStream($stream), 10, '|');
        for ($i = 0; $i < 20; ++$i) {
            $out->write('a');
        }
        $out->close();

        $stream->rewind();
        $str = $stream->getContents();
        $arr = explode('|', $str);

        $this->assertStringEndsWith('|', $str);
        $this->assertCount(3, $arr);
        $this->assertEquals(10, strlen($arr[0]));
        $this->assertEquals(10, strlen($arr[1]));
        $this->assertEmpty($arr[2]);
    }
}
