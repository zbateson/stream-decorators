<?php

namespace ZBateson\StreamDecorators;

use GuzzleHttp\Psr7;
use LegacyPHPUnit\TestCase;

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
        $stream = Psr7\Utils::streamFor('This is a test');
        $res = new SeekingLimitStream($stream, 3, 1);
        $str = $res->getContents();
        $this->assertSame('his', $str);
    }

    public function testReadLimitsToEnd()
    {
        $stream = Psr7\Utils::streamFor('test');
        $res = new SeekingLimitStream($stream, 4, 0);
        $str = $res->getContents();
        $this->assertSame('test', $str);
    }

    public function testPosition()
    {
        $stream = Psr7\Utils::streamFor('This is a test');
        $res = new SeekingLimitStream($stream, 3, 1);
        $this->assertNotNull($res);
        $this->assertSame(0, $res->tell());
        $res->getContents();
        $this->assertSame(3, $res->tell());
    }

    public function testGetSize()
    {
        $stream = Psr7\Utils::streamFor('This is a test');
        $res = new SeekingLimitStream($stream);
        $this->assertSame($stream->getSize(), $res->getSize());
    }

    public function testGetSizeWithLimit()
    {
        $stream = Psr7\Utils::streamFor('This is a test');
        $res = new SeekingLimitStream($stream, 5);
        $this->assertSame(5, $res->getSize());
    }

    public function testGetSizeWithLimitAndOffset()
    {
        $stream = Psr7\Utils::streamFor('This is a test');
        $res = new SeekingLimitStream($stream, 5, 1);
        $this->assertSame(5, $res->getSize());
    }

    public function testGetSizeWithLimitBeyondSize()
    {
        $stream = Psr7\Utils::streamFor('This is a test');
        $res = new SeekingLimitStream($stream, 5, 10);
        $this->assertSame(4, $res->getSize());
    }

    public function testEof()
    {
        $stream = Psr7\Utils::streamFor('This is a test');
        $res = new SeekingLimitStream($stream, 3, 1);
        $this->assertNotNull($res);
        $this->assertFalse($res->eof());
        $res->getContents();
        $this->assertTrue($res->eof());
    }

    public function testEofWithStreamAtEnd()
    {
        $stream = Psr7\Utils::streamFor('This is a test');
        $stream->getContents();
        $this->assertTrue($stream->eof());
        $res = new SeekingLimitStream($stream, 3, 1);
        $this->assertNotNull($res);
        $this->assertFalse($res->eof());
        $this->assertSame('his', $res->getContents());
        $this->assertTrue($res->eof());
    }

    public function testEofWithNoLimit()
    {
        $stream = Psr7\Utils::streamFor('This is a test');
        $stream->getContents();
        $this->assertTrue($stream->eof());
        $res = new SeekingLimitStream($stream, -1, 5);
        $this->assertNotNull($res);
        $this->assertFalse($res->eof());
        $this->assertSame('is a test', $res->getContents());
        $this->assertTrue($res->eof());
    }

    public function testEofWithNoLimitAndOffset()
    {
        $stream = Psr7\Utils::streamFor('This is a test');
        $stream->getContents();
        $this->assertTrue($stream->eof());
        $res = new SeekingLimitStream($stream);
        $this->assertNotNull($res);
        $this->assertFalse($res->eof());
        $this->assertSame('This is a test', $res->getContents());
        $this->assertTrue($res->eof());
    }

    public function testSeek()
    {
        $stream = Psr7\Utils::streamFor('This is a test');
        $res = new SeekingLimitStream($stream, 3, 1);

        $res->seek(-1, SEEK_SET);
        $this->assertSame(0, $res->tell());
        $res->seek(4, SEEK_SET);
        $this->assertSame(3, $res->tell());
        $res->seek(1, SEEK_END);
        $this->assertSame(3, $res->tell());
        $res->seek(-1, SEEK_CUR);
        $this->assertSame(2, $res->tell());

        $res->seek(2, SEEK_SET);
        $str = $res->getContents();
        $this->assertSame('s', $str);
        $this->assertSame(3, $res->tell());

        $res->seek(-2, SEEK_CUR);
        $this->assertSame(1, $res->tell());
        $str = $res->getContents();
        $this->assertSame('is', $str);

        $res->seek(-1, SEEK_END);
        $str = $res->getContents();
        $this->assertSame('s', $str);
    }
}
