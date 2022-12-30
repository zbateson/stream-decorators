<?php

namespace ZBateson\StreamDecorators;

use GuzzleHttp\Psr7;
use LegacyPHPUnit\TestCase;

/**
 * Description of NonClosingStreamTest
 *
 * @group NonClosingStream
 * @covers ZBateson\StreamDecorators\NonClosingStream
 * @author Zaahid Bateson
 */
class NonClosingStreamTest extends TestCase
{
    public function testClose()
    {
        $str = 'Testacular';
        $org = Psr7\Utils::streamFor($str);
        $stream = new NonClosingStream($org);
        $stream->close();
        $this->assertSame($org->getContents(), 'Testacular');
    }

    public function testDetach()
    {
        $str = 'Testacular';
        $org = Psr7\Utils::streamFor($str);
        $stream = new NonClosingStream($org);
        $stream->detach();
        $this->assertSame($org->getContents(), 'Testacular');
    }
}
