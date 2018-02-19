<?php
namespace ZBateson\StreamDecorators;

use PHPUnit_Framework_TestCase;
use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\StreamWrapper;

/**
 * Description of UUStreamDecoratorTest
 *
 * @group UUStreamDecorator
 * @covers ZBateson\StreamDecorators\UUStreamDecorator
 * @author Zaahid Bateson
 */
class UUStreamDecoratorTest extends PHPUnit_Framework_TestCase
{
    public function testReadAndRewind()
    {
        $str = str_repeat('é J\'interdis aux marchands de vanter trop leur marchandises. Car '
            . 'ils se font vite pédagogues et t\'enseignent comme but ce qui '
            . 'n\'est par essence qu\'un moyen, et te trompant ainsi sur la '
            . 'route à suivre les voilà bientôt qui te dégradent, car si leur '
            . 'musique est vulgaire ils te fabriquent pour te la vendre une âme '
            . 'vulgaire.é', 30);
        $stream = Psr7\stream_for(convert_uuencode($str));
        $uuStream = new UUStreamDecorator($stream);

        for ($i = 1; $i < strlen($str); ++$i) {
            $uuStream->rewind();
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
            . 'vulgaire.é', 30);
        $encoded = preg_replace('/([^\r]?)\n/', "$1\r\n", convert_uuencode($str));
        $stream = Psr7\stream_for($encoded);
        $uuStream = new UUStreamDecorator($stream);

        for ($i = 1; $i < strlen($str); ++$i) {
            $uuStream->rewind();
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
            $uuStream = new UUStreamDecorator($stream);
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
            $uuStream = new UUStreamDecorator($stream);
            for ($j = $i; !$uuStream->eof(); ++$j) {
                $this->assertEquals(substr($str, $j, 1), $uuStream->read(1), "Failed reading to EOF on substr $i iteration $j");
            }
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
        $stream = Psr7\stream_for(convert_uuencode($str));
        $uuStream = new UUStreamDecorator($stream);
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
        $uuStream = new UUStreamDecorator($stream);

        for ($i = 1; $i < strlen($str); ++$i) {
            $uuStream->rewind();
            for ($j = 0; $j < strlen($str); $j += $i) {
                $this->assertEquals($j, $uuStream->tell(), "Tell at $j failed with $i step");
                $uuStream->read($i);
            }
            $this->assertEquals(strlen($str), $uuStream->tell(), "Final tell failed with $i step");
        }
    }

    public function testSeek()
    {
        $this->setExpectedException('RuntimeException');
        $stream = Psr7\stream_for(convert_uuencode('test'));
        $uuStream = new UUStreamDecorator($stream);
        $uuStream->seek(10);
    }

    public function testReadWithBeginAndEnd()
    {
        $str = 'é J\'interdis aux marchands de vanter trop leur marchandises. Car '
            . 'ils se font vite pédagogues et t\'enseignent comme but ce qui '
            . 'n\'est par essence qu\'un moyen, et te trompant ainsi sur la '
            . 'route à suivre les voilà bientôt qui te dégradent, car si leur '
            . 'musique est vulgaire ils te fabriquent pour te la vendre une âme '
            . 'vulgaire.é';
        $str = str_repeat($str, 30);
        //for ($i = 0; $i < strlen($str); ++$i) {
            
            $substr = $str; //substr($str, 0, $i + 1);
            $encoded = convert_uuencode($substr);
            $encoded = "begin 666 devil.txt\r\n\r\n" . $encoded . "\r\nend\r\n";
            file_put_contents('./tests/_data/output_test.txt', $encoded);

            $stream = Psr7\stream_for($encoded);
            $uuStream = new UUStreamDecorator($stream);
            $this->assertEquals($substr, $uuStream->getContents());
        //}
    }

    public function testDecodeFile()
    {
        $encoded = './tests/_data/blueball.uu.txt';
        $org = './tests/_data/blueball.png';
        $f = fopen($encoded, 'r');

        $streamDecorator = new UUStreamDecorator(Psr7\stream_for($f));
        $handle = StreamWrapper::getResource($streamDecorator);

        $this->assertEquals(file_get_contents($org), stream_get_contents($handle), 'Decoded blueball not equal to original file');

        fclose($handle);
        fclose($f);
    }
}
