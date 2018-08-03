<?php
namespace ZBateson\StreamDecorators;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Psr7;

/**
 * Description of SeekingLimitStreamTest
 *
 * @group SeekingLimitStream
 * @covers ZBateson\StreamDecorators\SeekingLimitStream
 * @author Zaahid Bateson
 */
class SeekingLimitStreamTest extends TestCase
{
    public function testReadLimits()
    {
        $stream = Psr7\stream_for('This is a test');
        $res = new SeekingLimitStream($stream, 3, 1);
        $str = $res->getContents();
        $this->assertEquals('his', $str);
    }

    public function testReadLimitsToEnd()
    {
        $stream = Psr7\stream_for('test');
        $res = new SeekingLimitStream($stream, 4, 0);
        $str = $res->getContents();
        $this->assertEquals('test', $str);
    }

    public function testPosition()
    {
        $stream = Psr7\stream_for('This is a test');
        $res = new SeekingLimitStream($stream, 3, 1);
        $this->assertNotNull($res);
        $this->assertEquals(0, $res->tell());
        $res->getContents();
        $this->assertEquals(3, $res->tell());
    }

    public function testGetSize()
    {
        $stream = Psr7\stream_for('This is a test');
        $res = new SeekingLimitStream($stream);
        $this->assertEquals($stream->getSize(), $res->getSize());
    }

    public function testGetSizeWithLimit()
    {
        $stream = Psr7\stream_for('This is a test');
        $res = new SeekingLimitStream($stream, 5);
        $this->assertEquals(5, $res->getSize());
    }

    public function testGetSizeWithLimitAndOffset()
    {
        $stream = Psr7\stream_for('This is a test');
        $res = new SeekingLimitStream($stream, 5, 1);
        $this->assertEquals(5, $res->getSize());
    }

    public function testGetSizeWithLimitBeyondSize()
    {
        $stream = Psr7\stream_for('This is a test');
        $res = new SeekingLimitStream($stream, 5, 10);
        $this->assertEquals(4, $res->getSize());
    }

    public function testEof()
    {
        $stream = Psr7\stream_for('This is a test');
        $res = new SeekingLimitStream($stream, 3, 1);
        $this->assertNotNull($res);
        $this->assertFalse($res->eof());
        $res->getContents();
        $this->assertTrue($res->eof());
    }

    public function testEofWithStreamAtEnd()
    {
        $stream = Psr7\stream_for('This is a test');
        $stream->getContents();
        $this->assertTrue($stream->eof());
        $res = new SeekingLimitStream($stream, 3, 1);
        $this->assertNotNull($res);
        $this->assertFalse($res->eof());
        $this->assertEquals('his', $res->getContents());
        $this->assertTrue($res->eof());
    }

    public function testEofWithNoLimit()
    {
        $stream = Psr7\stream_for('This is a test');
        $stream->getContents();
        $this->assertTrue($stream->eof());
        $res = new SeekingLimitStream($stream, -1, 5);
        $this->assertNotNull($res);
        $this->assertFalse($res->eof());
        $this->assertEquals('is a test', $res->getContents());
        $this->assertTrue($res->eof());
    }

    public function testEofWithNoLimitAndOffset()
    {
        $stream = Psr7\stream_for('This is a test');
        $stream->getContents();
        $this->assertTrue($stream->eof());
        $res = new SeekingLimitStream($stream);
        $this->assertNotNull($res);
        $this->assertFalse($res->eof());
        $this->assertEquals('This is a test', $res->getContents());
        $this->assertTrue($res->eof());
    }

    public function testSeek()
    {
        $stream = Psr7\stream_for('This is a test');
        $res = new SeekingLimitStream($stream, 3, 1);

        $res->seek(-1, SEEK_SET);
        $this->assertEquals(0, $res->tell());
        $res->seek(4, SEEK_SET);
        $this->assertEquals(3, $res->tell());
        $res->seek(1, SEEK_END);
        $this->assertEquals(3, $res->tell());
        $res->seek(-1, SEEK_CUR);
        $this->assertEquals(2, $res->tell());

        $res->seek(2, SEEK_SET);
        $str = $res->getContents();
        $this->assertEquals('s', $str);
        $this->assertEquals(3, $res->tell());

        $res->seek(-2, SEEK_CUR);
        $this->assertEquals(1, $res->tell());
        $str = $res->getContents();
        $this->assertEquals('is', $str);

        $res->seek(-1, SEEK_END);
        $str = $res->getContents();
        $this->assertEquals('s', $str);
    }
}
