<?php

namespace ZBateson\StreamDecorators;

use GuzzleHttp\Psr7\BufferStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(TellZeroStream::class)]
#[Group('TellZeroStream')]
class TellZeroStreamTest extends TestCase
{
    public function testTell() : void
    {
        $stream = new TellZeroStream(new BufferStream());
        $stream->write('blah');
        $this->assertSame(0, $stream->tell());
        $this->assertSame('blah', $stream->getContents());
        $this->assertSame(0, $stream->tell());
    }
}
