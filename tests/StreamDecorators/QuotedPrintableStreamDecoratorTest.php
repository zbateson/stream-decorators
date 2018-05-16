<?php
namespace ZBateson\StreamDecorators;

use PHPUnit_Framework_TestCase;
use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\StreamWrapper;
use GuzzleHttp\Psr7\LimitStream;

/**
 * Description of QuotedPrintableStreamDecoratorTest
 *
 * @group QuotedPrintableStreamDecorator
 * @covers ZBateson\StreamDecorators\AbstractMimeTransferStreamDecorator
 * @covers ZBateson\StreamDecorators\QuotedPrintableStreamDecorator
 * @author Zaahid Bateson
 */
class QuotedPrintableStreamDecoratorTest extends PHPUnit_Framework_TestCase
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
        $qpStream = new QuotedPrintableStreamDecorator($stream);

        for ($i = 1; $i < strlen($str); ++$i) {
            $qpStream->rewind();
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
            $qpStream = new QuotedPrintableStreamDecorator($stream);
            $this->assertEquals($substr, $qpStream->getContents());
        }
    }

    public function testRewindAndReadFromLimitStreamHandle()
    {
        $str = 'é J\'interdis aux marchands de vanter trop leur marchandises. Car '
            . 'ils se font vite pédagogues et t\'enseignent comme but ce qui '
            . 'n\'est par essence qu\'un moyen, et te trompant ainsi sur la '
            . 'route à suivre les voilà bientôt qui te dégradent, car si leur '
            . 'musique est vulgaire ils te fabriquent pour te la vendre une âme '
            . 'vulgaire.é';

        $stream = Psr7\stream_for(quoted_printable_encode($str));
        $limitStream = new LimitStream($stream);
        $qpStream = new QuotedPrintableStreamDecorator($limitStream);

        $limitStream->getContents();
        $qpStream->rewind();
        $this->assertEquals($str, $qpStream->getContents());
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
            $qpStream = new QuotedPrintableStreamDecorator($stream);
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
        $qpStream = new QuotedPrintableStreamDecorator($stream);
        for ($i = 1; $i < strlen($str); ++$i) {
            $qpStream->rewind();
            for ($j = 0; $j < strlen($str); $j += $i) {
                $this->assertEquals(substr($str, $j, $i), $qpStream->read($i), "Read $j failed at $i step");
            }
            $this->assertEquals(strlen($str), $qpStream->tell(), "Final tell failed with $i step");
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

        $stream = Psr7\stream_for(quoted_printable_encode($str));
        $qpStream = new QuotedPrintableStreamDecorator($stream);
        for ($i = 0; $i < strlen($str); ++$i) {
            $this->assertEquals(strlen($str), $qpStream->getSize());
            $this->assertEquals(substr($str, $i, 1), $qpStream->read(1), "Failed reading to EOF on substr $i");
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
        $stream = Psr7\stream_for(quoted_printable_encode($str));
        $qpStream = new QuotedPrintableStreamDecorator($stream);

        for ($i = 1; $i < strlen($str); ++$i) {
            $qpStream->rewind();
            for ($j = 0; $j < strlen($str); $j += $i) {
                $this->assertEquals($j, $qpStream->tell(), "Tell at $j failed with $i step");
                $qpStream->read($i);
            }
            $this->assertEquals(strlen($str), $qpStream->tell(), "Final tell failed with $i step");
        }
    }

    public function testSeekCur()
    {
        $stream = Psr7\stream_for(quoted_printable_encode('test'));
        $qpStream = new QuotedPrintableStreamDecorator($stream);
        $this->assertEquals('te', $qpStream->read(2));
        $qpStream->seek(-2, SEEK_CUR);
        $this->assertEquals('te', $qpStream->read(2));
        $qpStream->seek(1, SEEK_CUR);
        $this->assertEquals('t', $qpStream->read(1));
    }

    public function testSeek()
    {
        $stream = Psr7\stream_for(quoted_printable_encode('0123456789'));
        $qpStream = new QuotedPrintableStreamDecorator($stream);
        $qpStream->seek(4);
        $this->assertEquals('4', $qpStream->read(1));
        $qpStream->seek(-1, SEEK_END);
        $this->assertEquals('9', $qpStream->read(1));
    }

    public function testBadlyEncodedStrings()
    {
        $encoded = "=";
        $stream = Psr7\stream_for($encoded);
        $qpStream = new QuotedPrintableStreamDecorator($stream);
        $this->assertEquals('', $qpStream->getContents());

        $encoded = "= ";
        $stream = Psr7\stream_for($encoded);
        $qpStream = new QuotedPrintableStreamDecorator($stream);
        $this->assertEquals('= ', $qpStream->getContents());

        $encoded = "=asdf";
        $stream = Psr7\stream_for($encoded);
        $qpStream = new QuotedPrintableStreamDecorator($stream);
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

        $streamDecorator = new QuotedPrintableStreamDecorator(Psr7\stream_for($f));
        $handle = StreamWrapper::getResource($streamDecorator);

        $this->assertEquals(file_get_contents($org), stream_get_contents($handle), 'Decoded blueball not equal to original file');

        fclose($handle);
        fclose($f);
    }
}
