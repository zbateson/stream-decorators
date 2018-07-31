<?php
namespace ZBateson\StreamDecorators;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\StreamWrapper;
use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\CachingStream;

/**
 * Description of QuotedPrintableStreamTest
 *
 * @group QuotedPrintableStream
 * @covers ZBateson\StreamDecorators\QuotedPrintableStream
 * @author Zaahid Bateson
 */
class QuotedPrintableStreamTest extends TestCase
{
    public function testReadAndRewind()
    {
        // borrowed from wikipedia's quoted printable article, added non-ascii
        // start character and end character
        $str = str_repeat('é J\'interdis aux marchands de vanter trop leur marchandises. Car '
            . 'ils se font vite pédagogues et t\'enseignent comme but ce qui '
            . 'n\'est par essence qu\'un moyen, et te trompant ainsi sur la '
            . 'route à suivre les voilà bientôt qui te dégradent, car si leur '
            . 'musique est vulgaire ils te fabriquent pour te la vendre une âme '
            . 'vulgaire.é', 10);
        $stream = Psr7\stream_for(quoted_printable_encode($str));
        for ($i = 1; $i < strlen($str); ++$i) {
            $stream->rewind();
            $qpStream = new QuotedPrintableStream(new NonClosingStream($stream));
            for ($j = 0; $j < strlen($str); $j += $i) {
                $this->assertEquals(substr($str, $j, $i), $qpStream->read($i), "Read $j failed at $i step");
            }
            $this->assertEquals(strlen($str), $qpStream->tell(), "Final tell failed with $i step");
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
            $stream = Psr7\stream_for(quoted_printable_encode($substr));
            $qpStream = new QuotedPrintableStream($stream);
            $this->assertEquals($substr, $qpStream->getContents());
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
            $substr = substr($str, $i);
            $stream = Psr7\stream_for(quoted_printable_encode($substr));
            $qpStream = new QuotedPrintableStream($stream);
            for ($j = 0; !$qpStream->eof(); ++$j) {
                $this->assertEquals(substr($substr, $j, 1), $qpStream->read(1), "Failed reading to EOF on substr $i iteration $j");
            }
        }
    }

    public function testReadIgnorableNewLineCharacters()
    {
        $str = 'J\'interdis aux marchands de vanter trop leur marchandises. Car '
            . 'ils se font vite pédagogues et t\'enseignent comme but ce qui '
            . 'n\'est par essence qu\'un moyen, et te trompant ainsi sur la '
            . 'route à suivre les voilà bientôt qui te dégradent, car si leur '
            . 'musique est vulgaire ils te fabriquent pour te la vendre une âme '
            . 'vulgaire.';
        $encoded = "J'interdis aux marchands de vanter trop leur marchandises. Car ils se font =\r\n"
            . "vite p=C3=A9dagogues et t'enseignent comme but ce qui n'est par essence qu'=\r\n"
            . "un moyen, et te trompant ainsi sur la route =C3=A0 suivre les voil=C3=A0 bi=\r"
            . "ent=C3=B4t qui te d=C3=A9gradent, car si leur musique est vulgaire ils te f=\n"
            . "abriquent pour te la vendre une =C3=A2me vulgaire.";
        $stream = Psr7\stream_for($encoded);
        for ($i = 1; $i < strlen($str); ++$i) {
            $stream->rewind();
            $qpStream = new QuotedPrintableStream(new NonClosingStream($stream));
            for ($j = 0; $j < strlen($str); $j += $i) {
                $this->assertEquals(substr($str, $j, $i), $qpStream->read($i), "Read $j failed at $i step");
            }
            $this->assertEquals(strlen($str), $qpStream->tell(), "Final tell failed with $i step");
        }
    }

    public function testGetSize()
    {
        $str = 'Sweetest little pie';
        $stream = Psr7\stream_for(quoted_printable_encode($str));
        $qpStream = new QuotedPrintableStream($stream);
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
        $stream = Psr7\stream_for(quoted_printable_encode($str));
        for ($i = 1; $i < strlen($str); ++$i) {
            $stream->rewind();
            $qpStream = new QuotedPrintableStream(new NonClosingStream($stream));
            for ($j = 0; $j < strlen($str); $j += $i) {
                $this->assertEquals($j, $qpStream->tell(), "Tell at $j failed with $i step");
                $qpStream->read($i);
            }
            $this->assertEquals(strlen($str), $qpStream->tell(), "Final tell failed with $i step");
        }
    }

    public function testBadlyEncodedStrings()
    {
        $encoded = "=";
        $stream = Psr7\stream_for($encoded);
        $qpStream = new QuotedPrintableStream($stream);
        $this->assertEquals('', $qpStream->getContents());

        $encoded = "= ";
        $stream = Psr7\stream_for($encoded);
        $qpStream = new QuotedPrintableStream($stream);
        $this->assertEquals('= ', $qpStream->getContents());

        $encoded = "=asdf";
        $stream = Psr7\stream_for($encoded);
        $qpStream = new QuotedPrintableStream($stream);
        $this->assertEquals('=', $qpStream->read(1));
        $this->assertEquals('a', $qpStream->read(1));
        $this->assertEquals('s', $qpStream->read(1));
        $this->assertEquals('d', $qpStream->read(1));
        $this->assertEquals('f', $qpStream->read(1));
    }

    public function testDecodeFile()
    {
        $encoded = './tests/_data/blueball.qp.txt';
        $org = './tests/_data/blueball.png';
        $f = fopen($encoded, 'r');

        $stream = new QuotedPrintableStream(Psr7\stream_for($f));
        $this->assertEquals(file_get_contents($org), $stream->getContents(), 'Decoded blueball not equal to original file');
    }

    public function testWrite()
    {
        $contents = str_repeat('é J\'interdis aux marchands de vanter trop leur marchandises. Car '
            . 'ils se font vite pédagogues et t\'enseignent comme but ce qui '
            . 'n\'est par essence qu\'un moyen, et te trompant ainsi sur la '
            . 'route à suivre les voilà bientôt qui te dégradent, car si leur '
            . 'musique est vulgaire ils te fabriquent pour te la vendre une âme '
            . 'vulgaire.é', 5);

        for ($i = 1; $i < strlen($contents); ++$i) {
            $stream = Psr7\stream_for(fopen('php://temp', 'r+'));
            $out = new QuotedPrintableStream(new NonClosingStream($stream));
            for ($j = 0; $j < strlen($contents); $j += $i) {
                $out->write(substr($contents, $j, $i));
            }
            $out->close();

            $stream->rewind();
            $in = new QuotedPrintableStream(new NonClosingStream($stream));
            $this->assertEquals($contents, rtrim($in->getContents()));

            $stream->rewind();
            $raw = $stream->getContents();
            $arr = explode("\r\n", $raw);
            $this->assertGreaterThan(0, count($arr));
            for ($x = 0; $x < count($arr); ++$x) {
                $this->assertLessThanOrEqual(76, strlen($arr[$x]));
            }
        }
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testSeekUnsopported()
    {
        $stream = Psr7\stream_for(quoted_printable_encode('Sweetest little pie'));
        $test = new QuotedPrintableStream($stream);
        $this->assertFalse($test->isSeekable());
        $test->seek(0);
    }
}
