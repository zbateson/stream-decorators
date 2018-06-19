<?php
namespace ZBateson\StreamDecorators;

use PHPUnit_Framework_TestCase;
use GuzzleHttp\Psr7;

/**
 * Description of NonClosingLimitStreamTest
 *
 * @group NonClosingLimitStream
 * @covers ZBateson\StreamDecorators\NonClosingLimitStream
 * @author Zaahid Bateson
 */
class NonClosingLimitStreamTest extends PHPUnit_Framework_TestCase
{
    public function testClose()
    {
        $str = 'Testacular';
        $org = Psr7\stream_for($str);
        $stream = new NonClosingLimitStream($org);

        $stream->close();
        $this->assertSame($org->getContents(), 'Testacular');
    }

    public function testDetach()
    {
        $str = 'Testacular';
        $org = Psr7\stream_for($str);
        $stream = new NonClosingLimitStream($org);

        $stream->detach();
        $this->assertSame($org->getContents(), 'Testacular');
    }
}
