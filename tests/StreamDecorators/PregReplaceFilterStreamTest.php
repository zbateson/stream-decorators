<?php
namespace ZBateson\StreamDecorators;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Psr7;

/**
 * Description of PregReplaceFilterStreamTest
 *
 * @group PregReplaceFilterStream
 * @covers ZBateson\StreamDecorators\PregReplaceFilterStream
 * @author Zaahid Bateson
 */
class PregReplaceFilterStreamTest extends TestCase
{
    public function testRead()
    {
        $stream = Psr7\stream_for('a-ll t-h-e k-ing\'s me-n');
        $test = new PregReplaceFilterStream($stream, '/\-/', '');
        $this->assertEquals('all the king\'s men', $test->getContents());
    }

    public function testReadBuffered()
    {
        $str = str_repeat('All the King\'s Men ', 8000);
        $filter = str_repeat('A-l-l t-h-e K-in-g\'s M-en ', 8000);
        $stream = Psr7\stream_for($filter);

        $test = new PregReplaceFilterStream($stream, '/\-/', '');
        for ($i = 0; $i < strlen($str); $i += 10) {
            $this->assertEquals(substr($str, $i, 10), $test->read(10));
        }
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testSeekUnsopported()
    {
        $stream = Psr7\stream_for('a-ll t-h-e k-ing\'s me-n');
        $test = new PregReplaceFilterStream($stream, '/\-/', '');
        $this->assertFalse($test->isSeekable());
        $test->seek(0);
    }
}
