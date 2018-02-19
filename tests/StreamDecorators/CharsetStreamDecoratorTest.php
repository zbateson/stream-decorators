<?php
namespace ZBateson\StreamDecorators;

use PHPUnit_Framework_TestCase;
use GuzzleHttp\Psr7;
use ZBateson\StreamDecorators\Util\CharsetConverter;

/**
 * Description of CharsetStreamDecoratorTest
 *
 * @group CharsetStreamDecorator
 * @covers ZBateson\StreamDecorators\AbstractMimeTransferStreamDecorator
 * @covers ZBateson\StreamDecorators\CharsetStreamDecorator
 * @author Zaahid Bateson
 */
class CharsetStreamDecoratorTest extends PHPUnit_Framework_TestCase
{
    private $converter;

    public function setUp()
    {
        parent::setUp();
        $this->converter = new CharsetConverter();
    }

    public function testReadAndRewind()
    {
        $str = str_repeat('هلا هلا شخبار بعد؟ شلون تبرمج؟', 50);

        $stream = Psr7\stream_for($this->converter->convert($str, 'UTF-8', 'UTF-32'));
        $qpStream = new CharsetStreamDecorator($stream, 'UTF-32', 'UTF-8');

        for ($i = 1; $i < mb_strlen($str, 'UTF-8'); ++$i) {
            $qpStream->rewind();
            for ($j = 0; $j < mb_strlen($str, 'UTF-8'); $j += $i) {
                $char = $qpStream->read($i);
                $this->assertEquals(mb_substr($str, $j, $i, 'UTF-8'), $char, "Read $j failed at $i step");
            }
            $this->assertEquals(mb_strlen($str, 'UTF-8'), $qpStream->tell(), "Final tell failed with $i step");
        }
    }

    public function testReadContents()
    {
        $str = str_repeat('هلا هلا شخبار بعد؟ شلون تبرمج؟', 50);

        for ($i = 0; $i < mb_strlen($str); ++$i) {
            $substr = mb_substr($str, 0, $i + 1, 'UTF-8');
            $stream = Psr7\stream_for($this->converter->convert($substr, 'UTF-8', 'UTF-16'));
            $qpStream = new CharsetStreamDecorator($stream, 'UTF-16', 'UTF-8');
            $this->assertEquals($substr, $qpStream->getContents());
        }
    }

    public function testReadToEof()
    {
        $str = str_repeat('هلا هلا شخبار بعد؟ شلون تبرمج؟', 10);
        for ($i = 0; $i < mb_strlen($str, 'UTF-8'); ++$i) {
            $substr = mb_substr($str, $i, null, 'UTF-8');
            $stream = Psr7\stream_for($this->converter->convert($substr, 'UTF-8', 'WINDOWS-1256'));
            $qpStream = new CharsetStreamDecorator($stream, 'WINDOWS-1256', 'UTF-8');
            for ($j = 0; !$qpStream->eof(); ++$j) {
                $read = $qpStream->read(1);
                $this->assertEquals(mb_substr($substr, $j, 1, 'UTF-8'), $read, "Failed reading to EOF on substr $i iteration $j");
            }
        }
    }

    public function testReadToEmpty()
    {
        $str = str_repeat('هلا هلا شخبار بعد؟ شلون تبرمج؟', 10);
        $stream = Psr7\stream_for($this->converter->convert($str, 'UTF-8', 'WINDOWS-1256'));
        $qpStream = new CharsetStreamDecorator($stream, 'WINDOWS-1256', 'UTF-8');
        $i = 0;
        while (($chr = $qpStream->read(1)) !== '') {
            $this->assertEquals(mb_substr($str, $i++, 1, 'UTF-8'), $chr, "Failed reading to false on substr $i");
        }
    }

    public function testGetSize()
    {
        $str = 'Wubalubadubduuuuuuuuuuuuuuuuuuuuuuuuuuuuub!';

        $stream = Psr7\stream_for($this->converter->convert($str, 'UTF-8', 'WINDOWS-1256'));
        $qpStream = new CharsetStreamDecorator($stream, 'WINDOWS-1256', 'UTF-8');
        $this->assertNull($qpStream->getSize());
    }

    public function testTell()
    {
        $str = 'é J\'interdis aux marchands de vanter trop leur marchandises. Car '
            . 'ils se font vite pédagogues et t\'enseignent comme but ce qui '
            . 'n\'est par essence qu\'un moyen, et te trompant ainsi sur la '
            . 'route à suivre les voilà bientôt qui te dégradent, car si leur '
            . 'musique est vulgaire ils te fabriquent pour te la vendre une âme '
            . 'vulgaire.é';
        $stream = Psr7\stream_for($str);
        $qpStream = new CharsetStreamDecorator($stream);

        for ($i = 1; $i < strlen($str); ++$i) {
            $qpStream->rewind();
            for ($j = 0; $j < strlen($str); $j += $i) {
                $this->assertEquals($j, $qpStream->tell(), "Tell at $j failed with $i step");
                $qpStream->read($i);
            }
            $this->assertEquals(strlen($str), $qpStream->tell(), "Final tell failed with $i step");
        }
    }

    public function testSeek()
    {
        $this->setExpectedException('RuntimeException');
        $stream = Psr7\stream_for('test');
        $qpStream = new Base64StreamDecorator($stream);
        $qpStream->seek(10);
    }
}
