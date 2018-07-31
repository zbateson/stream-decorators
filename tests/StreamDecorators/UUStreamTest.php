<?php
namespace ZBateson\StreamDecorators;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\StreamWrapper;

/**
 * Description of UUStreamTest
 *
 * @group UUStream
 * @covers ZBateson\StreamDecorators\UUStream
 * @author Zaahid Bateson
 */
class UUStreamTest extends TestCase
{
    public function testReadAndRewind()
    {
        $str = str_repeat('é J\'interdis aux marchands de vanter trop leur marchandises. Car '
            . 'ils se font vite pédagogues et t\'enseignent comme but ce qui '
            . 'n\'est par essence qu\'un moyen, et te trompant ainsi sur la '
            . 'route à suivre les voilà bientôt qui te dégradent, car si leur '
            . 'musique est vulgaire ils te fabriquent pour te la vendre une âme '
            . 'vulgaire.é', 10);
        $stream = Psr7\stream_for(convert_uuencode($str));
        for ($i = 1; $i < strlen($str); ++$i) {
            $stream->rewind();
            $uuStream = new UUStream(new NonClosingStream($stream));
            for ($j = 0; $j < strlen($str); $j += $i) {
                $this->assertEquals(substr($str, $j, $i), $uuStream->read($i), "Read $j failed at $i step");
            }
            $this->assertEquals(strlen($str), $uuStream->tell(), "Final tell failed with $i step");
        }
    }

    public function testReadWithCrLf()
    {
        $str = str_repeat('é J\'interdis aux marchands de vanter trop leur marchandises. Car '
            . 'ils se font vite pédagogues et t\'enseignent comme but ce qui '
            . 'n\'est par essence qu\'un moyen, et te trompant ainsi sur la '
            . 'route à suivre les voilà bientôt qui te dégradent, car si leur '
            . 'musique est vulgaire ils te fabriquent pour te la vendre une âme '
            . 'vulgaire.é', 10);
        $encoded = preg_replace('/([^\r]?)\n/', "$1\r\n", convert_uuencode($str));

        $stream = Psr7\stream_for($encoded);
        for ($i = 1; $i < strlen($str); ++$i) {
            $stream->rewind();
            $uuStream = new UUStream(new NonClosingStream($stream));
            for ($j = 0; $j < strlen($str); $j += $i) {
                $this->assertEquals(substr($str, $j, $i), $uuStream->read($i), "Read $j failed at $i step");
            }
            $this->assertEquals(strlen($str), $uuStream->tell(), "Final tell failed with $i step");
        }
    }

    public function testReadContents()
    {
        $str = 'é J\'interdis aux marchands de vanter trop leur marchandises. Car '
            . 'ils se font vite pédagogues et t\'enseignent comme but ce qui '
            . 'n\'est par essence qu\'un moyen, et te trompant ainsi sur la '
            . 'route à suivre les voilà bientôt qui te dégradent, car si leur '
            . 'musique est vulgaire ils te fabriquent pour te la vendre une âme '
            . 'vulgaire.é';
        for ($i = 0; $i < strlen($str); ++$i) {
            $substr = substr($str, 0, $i + 1);
            $stream = Psr7\stream_for(convert_uuencode($substr));
            $uuStream = new UUStream($stream);
            $this->assertEquals($substr, $uuStream->getContents());
        }
    }

    public function testReadToEof()
    {
        $str = 'é J\'interdis aux marchands de vanter trop leur marchandises. Car '
            . 'ils se font vite pédagogues et t\'enseignent comme but ce qui '
            . 'n\'est par essence qu\'un moyen, et te trompant ainsi sur la '
            . 'route à suivre les voilà bientôt qui te dégradent, car si leur '
            . 'musique est vulgaire ils te fabriquent pour te la vendre une âme '
            . 'vulgaire.é';
        for ($i = 0; $i < strlen($str); ++$i) {
            $stream = Psr7\stream_for(convert_uuencode(substr($str, $i)));
            $uuStream = new UUStream($stream);
            for ($j = $i; !$uuStream->eof(); ++$j) {
                $this->assertEquals(substr($str, $j, 1), $uuStream->read(1), "Failed reading to EOF on substr $i iteration $j");
            }
        }
    }

    public function testGetSize()
    {
        $str = 'Sweetest little pie';
        $stream = Psr7\stream_for(quoted_printable_encode($str));
        $uuStream = new UUStream($stream);
        $this->assertNull($uuStream->getSize());
    }

    public function testTell()
    {
        $str = 'é J\'interdis aux marchands de vanter trop leur marchandises. Car '
            . 'ils se font vite pédagogues et t\'enseignent comme but ce qui '
            . 'n\'est par essence qu\'un moyen, et te trompant ainsi sur la '
            . 'route à suivre les voilà bientôt qui te dégradent, car si leur '
            . 'musique est vulgaire ils te fabriquent pour te la vendre une âme '
            . 'vulgaire.é';

        $stream = Psr7\stream_for(convert_uuencode($str));
        for ($i = 1; $i < strlen($str); ++$i) {
            $stream->rewind();
            $uuStream = new UUStream(new NonClosingStream($stream));
            for ($j = 0; $j < strlen($str); $j += $i) {
                $this->assertEquals($j, $uuStream->tell(), "Tell at $j failed with $i step");
                $uuStream->read($i);
            }
            $this->assertEquals(strlen($str), $uuStream->tell(), "Final tell failed with $i step");
        }
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testSeekUnsopported()
    {
        $stream = Psr7\stream_for(quoted_printable_encode('Sweetest little pie'));
        $test = new UUStream($stream);
        $this->assertFalse($test->isSeekable());
        $test->seek(0);
    }

    public function testReadWithBeginAndEnd()
    {
        $str = 'é J\'interdis aux marchands de vanter trop leur marchandises. Car '
            . 'ils se font vite pédagogues et t\'enseignent comme but ce qui '
            . 'n\'est par essence qu\'un moyen, et te trompant ainsi sur la '
            . 'route à suivre les voilà bientôt qui te dégradent, car si leur '
            . 'musique est vulgaire ils te fabriquent pour te la vendre une âme '
            . 'vulgaire.é';
        $str = str_repeat($str, 10);
        for ($i = 0; $i < strlen($str); ++$i) {

            $substr = substr($str, 0, $i + 1);
            $encoded = convert_uuencode($substr);
            $encoded = "begin 666 devil.txt\r\n\r\n" . $encoded . "\r\nend\r\n";

            $stream = Psr7\stream_for($encoded);
            $uuStream = new UUStream($stream);
            $this->assertEquals($substr, $uuStream->getContents());
        }
    }

    public function testDecodeFile()
    {
        $encoded = './tests/_data/blueball.uu.txt';
        $org = './tests/_data/blueball.png';
        $stream = new UUStream(Psr7\stream_for(fopen($encoded, 'r')));
        $this->assertEquals(file_get_contents($org), $stream->getContents(), 'Decoded blueball not equal to original file');
    }

    public function testDecodeFileWithSpaces()
    {
        $encoded = './tests/_data/blueball-2.uu.txt';
        $org = './tests/_data/blueball.png';
        $stream = new UUStream(Psr7\stream_for(fopen($encoded, 'r')));
        $this->assertEquals(file_get_contents($org), $stream->getContents(), 'Decoded blueball not equal to original file');
    }

    public function testWrite()
    {
        $org = './tests/_data/blueball.png';
        $contents = file_get_contents($org);

        for ($i = 1; $i < strlen($contents); ++$i) {
            $stream = Psr7\stream_for(fopen('php://temp', 'r+'));
            $out = new UUStream(new NonClosingStream($stream));
            for ($j = 0; $j < strlen($contents); $j += $i) {
                $out->write(substr($contents, $j, $i));
            }
            $out->close();

            $stream->rewind();
            $in = new UUStream(new NonClosingStream($stream));
            $this->assertEquals($contents, $in->getContents());
            $in->close();

            $stream->rewind();
            $raw = $stream->getContents();
            $arr = explode("\r\n", $raw);
            $this->assertGreaterThan(0, count($arr));
            for ($x = 0; $x < count($arr); ++$x) {
                $this->assertLessThanOrEqual(61, strlen($arr[$x]));
            }
        }
    }

    public function testWriteDifferentContentLengths()
    {
        $contents = 'é J\'interdis aux marchands de vanter trop leur marchandises. Car '
            . 'ils se font vite pédagogues et t\'enseignent comme but ce qui '
            . 'n\'est par essence qu\'un moyen, et te trompant ainsi sur la '
            . 'route à suivre les voilà bientôt qui te dégradent, car si leur '
            . 'musique est vulgaire ils te fabriquent pour te la vendre une âme '
            . 'vulgaire.é';

        for ($i = 1; $i < strlen($contents); ++$i) {
            $str = substr($contents, 0, strlen($contents) - $i);
            $stream = Psr7\stream_for(fopen('php://temp', 'r+'));
            $out = new UUStream(new NonClosingStream($stream));
            for ($j = 0; $j < strlen($str); $j += $i) {
                $out->write(substr($str, $j, $i));
            }
            $out->close();

            $stream->rewind();
            $in = new UUStream(new NonClosingStream($stream));
            $this->assertEquals($str, $in->getContents());
            $stream->rewind();

            $raw = $stream->getContents();
            $arr = explode("\r\n", $raw);
            $this->assertGreaterThan(0, count($arr));
            for ($x = 0; $x < count($arr); ++$x) {
                $this->assertLessThanOrEqual(61, strlen($arr[$x]));
            }
        }
    }
}
