<?php
namespace ZBateson\StreamDecorators;

use PHPUnit_Framework_TestCase;
use GuzzleHttp\Psr7;
use ZBateson\StreamDecorators\Util\CharsetConverter;

/**
 * Description of NonClosingStreamDecoratorTest
 *
 * @group NonClosingStreamDecorator
 * @covers ZBateson\StreamDecorators\NonClosingStreamDecorator
 * @author Zaahid Bateson
 */
class NonClosingStreamDecoratorTest extends PHPUnit_Framework_TestCase
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
