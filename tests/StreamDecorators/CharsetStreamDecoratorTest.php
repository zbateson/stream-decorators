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
        $csStream = new CharsetStreamDecorator($stream, 'UTF-32', 'UTF-8');

        for ($i = 1; $i < mb_strlen($str, 'UTF-8'); ++$i) {
            $csStream->rewind();
            for ($j = 0; $j < mb_strlen($str, 'UTF-8'); $j += $i) {
                $char = $csStream->read($i);
                $this->assertEquals(mb_substr($str, $j, $i, 'UTF-8'), $char, "Read $j failed at $i step");
            }
            $this->assertEquals(mb_strlen($str, 'UTF-8'), $csStream->tell(), "Final tell failed with $i step");
        }
    }

    public function testReadContents()
    {
        $str = str_repeat('هلا هلا شخبار بعد؟ شلون تبرمج؟', 50);

        for ($i = 0; $i < mb_strlen($str); ++$i) {
            $substr = mb_substr($str, 0, $i + 1, 'UTF-8');
            $stream = Psr7\stream_for($this->converter->convert($substr, 'UTF-8', 'UTF-16'));
            $csStream = new CharsetStreamDecorator($stream, 'UTF-16', 'UTF-8');
            $this->assertEquals($substr, $csStream->getContents());
        }
    }

    public function testReadToEof()
    {
        $str = str_repeat('هلا هلا شخبار بعد؟ شلون تبرمج؟', 10);
        for ($i = 0; $i < mb_strlen($str, 'UTF-8'); ++$i) {
            $substr = mb_substr($str, $i, null, 'UTF-8');
            $stream = Psr7\stream_for($this->converter->convert($substr, 'UTF-8', 'WINDOWS-1256'));
            $csStream = new CharsetStreamDecorator($stream, 'WINDOWS-1256', 'UTF-8');
            for ($j = 0; !$csStream->eof(); ++$j) {
                $read = $csStream->read(1);
                $this->assertEquals(mb_substr($substr, $j, 1, 'UTF-8'), $read, "Failed reading to EOF on substr $i iteration $j");
            }
        }
    }

    public function testReadToEmpty()
    {
        $str = str_repeat('هلا هلا شخبار بعد؟ شلون تبرمج؟', 10);
        $stream = Psr7\stream_for($this->converter->convert($str, 'UTF-8', 'WINDOWS-1256'));
        $csStream = new CharsetStreamDecorator($stream, 'WINDOWS-1256', 'UTF-8');
        $i = 0;
        while (($chr = $csStream->read(1)) !== '') {
            $this->assertEquals(mb_substr($str, $i++, 1, 'UTF-8'), $chr, "Failed reading to false on substr $i");
        }
    }

    public function testGetSize()
    {
        $str = 'é J\'interdis aux marchands de vanter trop leur marchandises. Car '
            . 'ils se font vite pédagogues et t\'enseignent comme but ce qui '
            . 'n\'est par essence qu\'un moyen, et te trompant ainsi sur la '
            . 'route à suivre les voilà bientôt qui te dégradent, car si leur '
            . 'musique est vulgaire ils te fabriquent pour te la vendre une âme '
            . 'vulgaire.é';

        $stream = Psr7\stream_for($this->converter->convert($str, 'UTF-8', 'UTF-16'));
        $csStream = new CharsetStreamDecorator($stream, 'UTF-16', 'UTF-8');
        for ($i = 0; $i < mb_strlen($str, 'UTF-8'); ++$i) {
            $this->assertEquals(mb_strlen($str, 'UTF-8'), $csStream->getSize());
            $this->assertEquals(mb_substr($str, $i, 1, 'UTF-8'), $csStream->read(1), "Failed reading to EOF on substr $i");
        }
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
        $csStream = new CharsetStreamDecorator($stream);

        for ($i = 1; $i < strlen($str); ++$i) {
            $csStream->rewind();
            for ($j = 0; $j < strlen($str); $j += $i) {
                $this->assertEquals($j, $csStream->tell(), "Tell at $j failed with $i step");
                $csStream->read($i);
            }
            $this->assertEquals(strlen($str), $csStream->tell(), "Final tell failed with $i step");
        }
    }

    public function testSeekCur()
    {
        $stream = Psr7\stream_for('test');
        $csStream = new CharsetStreamDecorator($stream);
        $this->assertEquals('te', $csStream->read(2));
        $csStream->seek(-2, SEEK_CUR);
        $this->assertEquals('te', $csStream->read(2));
        $csStream->seek(1, SEEK_CUR);
        $this->assertEquals('t', $csStream->read(1));
    }

    public function testSeek()
    {
        $stream = Psr7\stream_for('0123456789');
        $csStream = new CharsetStreamDecorator($stream);
        $csStream->seek(4);
        $this->assertEquals('4', $csStream->read(1));
        $csStream->seek(-1, SEEK_END);
        $this->assertEquals('9', $csStream->read(1));
    }
}
