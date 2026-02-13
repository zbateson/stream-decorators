<?php

namespace ZBateson\StreamDecorators;

use GuzzleHttp\Psr7\BufferStream;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(DecoratedCachingStream::class)]
#[Group('DecoratedCachingStream')]
class DecoratedCachingStreamTest extends TestCase
{
    public function testReadRewind() : void
    {
        $stream = Utils::streamFor('test');
        $dec = new DecoratedCachingStream($stream, function ($str) {
            return $str;
        });
        $this->assertSame('test', $dec->read(10));
        $dec->rewind();
        $this->assertSame('test', $dec->read(10));
        $dec->close();
    }

    public function testReadAndEofWithMultipleCaching() : void
    {
        $stream = Utils::streamFor('test test test test test');
        $dec = new DecoratedCachingStream($stream, function ($str) {
            return $str;
        }, 5);
        
        $this->assertSame('test', $dec->read(4));
        $this->assertSame(' ', $dec->read(1));
        $this->assertFalse($dec->eof());
        $this->assertSame('test test ', $dec->read(10));
        $this->assertSame('tes', $dec->read(3));
        $this->assertSame('t ', $dec->read(2));
        $this->assertFalse($dec->eof());
        $this->assertSame('test', $dec->read(20));
        $this->assertTrue($dec->eof());
    }

    public function testReadSeekAndEof() : void
    {
        $stream = Utils::streamFor('test test blah blah mwah');
        $dec = new DecoratedCachingStream($stream, function ($str) {
            return $str;
        }, 5);

        $this->assertSame('test', $dec->read(4));
        $this->assertSame(' ', $dec->read(1));
        $this->assertFalse($dec->eof());
        $dec->rewind();
        $this->assertFalse($dec->eof());
        $this->assertSame('test ', $dec->read(5));
        $dec->seek(-2, SEEK_CUR);
        $this->assertFalse($dec->eof());
        $this->assertSame('t ', $dec->read(2));
        $dec->seek(10, SEEK_SET);
        $this->assertSame('blah', $dec->read(4));
        $dec->seek(-4, SEEK_END);
        $this->assertFalse($dec->eof());
        $this->assertSame('mwah', $dec->read(4));
        $this->assertFalse($dec->eof());
        $this->assertSame('', $dec->read(1));
        $this->assertTrue($dec->eof());
    }

    public function testGetSize() : void
    {
        $str = 'test test blah blah mwah';
        $stream = Utils::streamFor($str);
        $dec = new DecoratedCachingStream($stream, function ($str) {
            return $str;
        }, 5);

        $this->assertSame(strlen($str), $dec->getSize());
    }

    public function testNotWritable() : void
    {
        $stream = Utils::streamFor('test test blah blah mwah');
        $dec = new DecoratedCachingStream($stream, function ($str) {
            return $str;
        });
        $this->assertFalse($dec->isWritable());
        $this->expectException(\RuntimeException::class);
        $dec->write('test');
    }

    public function testCloseAfterReading() : void
    {
        $stream = Utils::streamFor('test test');
        $dec = new DecoratedCachingStream($stream, function ($str) {
            return $str;
        }, 5);
        $str = $dec->getContents();
        $dec->close();
        $this->assertSame('test test', $str);
    }

    public function testBase64AllWritten() : void
    {
        $str = 'testing';
        $encoded = base64_encode($str);
        $intbfail = new BufferStream();
        $b64onbuffered = new Base64Stream($intbfail);
        $b64onbuffered->write($str);

        // remaining empty bytes not written because stream isn't closed
        // this is just a sanity check for the DecoratedCachingStream test
        // to make sure the test is correctly written
        $this->assertNotSame($encoded, $intbfail->getContents());
        
        $stream = Utils::streamFor($str);
        $dec = new DecoratedCachingStream($stream, function ($str) {
            return new Base64Stream($str);
        }, 3);
        $this->assertSame($encoded, $dec->getContents());
    }
}
