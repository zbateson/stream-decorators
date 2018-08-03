<?php
namespace ZBateson\StreamDecorators;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Psr7;

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
        $org = Psr7\stream_for($str);
        $stream = new NonClosingStream($org);
        $stream->close();
        $this->assertSame($org->getContents(), 'Testacular');
    }

    public function testDetach()
    {
        $str = 'Testacular';
        $org = Psr7\stream_for($str);
        $stream = new NonClosingStream($org);
        $stream->detach();
        $this->assertSame($org->getContents(), 'Testacular');
    }
}
