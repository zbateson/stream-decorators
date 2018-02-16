<?php
namespace ZBateson\MailMimeParser\Stream;

use PHPUnit_Framework_TestCase;
use GuzzleHttp\Psr7;

/**
 * Description of QuotedPrintableStreamDecoratorTest
 *
 * @group Stream
 * @group QuotedPrintableStreamDecorator
 * @covers ZBateson\MailMimeParser\Stream\QuotedPrintableStreamDecorator
 * @author Zaahid Bateson
 */
class QuotedPrintableStreamDecoratorTest extends PHPUnit_Framework_TestCase
{
    public function testReadAndRewind()
    {
        // borrowed from wikipedia's quoted printable article, added non-ascii
        // start character and end character
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
        $str = 'Wubalubadubduuuuuuuuuuuuuuuuuuuuuuuuuuuuub!';

        $stream = Psr7\stream_for(quoted_printable_encode($str));
        $qpStream = new QuotedPrintableStreamDecorator($stream);
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

    public function testSeek()
    {
        $this->setExpectedException('RuntimeException');
        $stream = Psr7\stream_for(quoted_printable_encode('test'));
        $qpStream = new Base64StreamDecorator($stream);
        $qpStream->seek(10);
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
}
