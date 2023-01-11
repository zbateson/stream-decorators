<?php

namespace ZBateson\StreamDecorators;

use GuzzleHttp\Psr7;
use PHPUnit\Framework\TestCase;

/**
 * Description of NonClosingStreamTest
 *
 * @group NonClosingStream
 * @covers ZBateson\StreamDecorators\NonClosingStream
 * @author Zaahid Bateson
 */
class NonClosingStreamTest extends TestCase
{
    public function testClose() : void
    {
        $str = 'Testacular';
        $org = Psr7\Utils::streamFor($str);
        $stream = new NonClosingStream($org);
        $stream->close();
        $this->assertSame($org->getContents(), 'Testacular');
    }

    public function testDetach() : void
    {
        $str = 'Testacular';
        $org = Psr7\Utils::streamFor($str);
        $stream = new NonClosingStream($org);
        $stream->detach();
        $this->assertSame($org->getContents(), 'Testacular');
    }
}
