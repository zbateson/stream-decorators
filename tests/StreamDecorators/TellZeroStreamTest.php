<?php

namespace ZBateson\StreamDecorators;

use GuzzleHttp\Psr7\BufferStream;
use PHPUnit\Framework\TestCase;

/**
 * Description of TellZeroStreamTest
 *
 * @group TellZeroStream
 * @covers ZBateson\StreamDecorators\TellZeroStream
 * @author Zaahid Bateson
 */
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
