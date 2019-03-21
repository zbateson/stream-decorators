<?php
namespace ZBateson\StreamDecorators;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\StreamWrapper;

/**
 * Description of Base64StreamTest
 *
 * @group Base64Stream
 * @covers ZBateson\StreamDecorators\Base64Stream
 * @author Zaahid Bateson
 */
class Base64StreamTest extends TestCase
{
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
            $stream = Psr7\stream_for(base64_encode($substr));
            $b64Stream = new Base64Stream($stream);
            $this->assertEquals($substr, $b64Stream->getContents());
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
            $stream = Psr7\stream_for(base64_encode(substr($str, $i)));
            $b64Stream = new Base64Stream($stream);
            for ($j = $i; !$b64Stream->eof(); ++$j) {
                $this->assertEquals(substr($str, $j, 1), $b64Stream->read(1), "Failed reading to EOF on substr $i iteration $j");
            }
        }
    }

    public function testGetSize()
    {
        $str = 'Sweetest little pie';
        $stream = Psr7\stream_for(base64_encode($str));
        $b64Stream = new Base64Stream($stream);
        $this->assertNull($b64Stream->getSize());
    }

    public function testTell()
    {
        $str = 'é J\'interdis aux marchands de vanter trop leur marchandises. Car '
            . 'ils se font vite pédagogues et t\'enseignent comme but ce qui '
            . 'n\'est par essence qu\'un moyen, et te trompant ainsi sur la '
            . 'route à suivre les voilà bientôt qui te dégradent, car si leur '
            . 'musique est vulgaire ils te fabriquent pour te la vendre une âme '
            . 'vulgaire.é';
        $stream = Psr7\stream_for(base64_encode($str));
        for ($i = 1; $i < strlen($str); ++$i) {
            $stream->rewind();
            $b64Stream = new Base64Stream(new NonClosingStream($stream));
            for ($j = 0; $j < strlen($str); $j += $i) {
                $this->assertEquals($j, $b64Stream->tell(), "Tell at $j failed with $i step");
                $b64Stream->read($i);
            }
            $this->assertEquals(strlen($str), $b64Stream->tell(), "Final tell failed with $i step");
        }
    }

    public function testDecodeFile()
    {
        $encoded = './tests/_data/blueball.b64.txt';
        $org = './tests/_data/blueball.png';
        $f = fopen($encoded, 'r');

        $streamDecorator = new Base64Stream(new PregReplaceFilterStream(Psr7\stream_for($f), '/[^a-zA-Z0-9\/\+=]/', ''));
        $handle = StreamWrapper::getResource($streamDecorator);

        $this->assertEquals(file_get_contents($org), stream_get_contents($handle), 'Decoded blueball not equal to original file');

        fclose($handle);
        fclose($f);
    }

    public function testDecodeWordFileWithStreamGetContents()
    {
        $encoded = './tests/_data/test.b64.txt';
        $org = './tests/_data/test.doc';
        $f = fopen($encoded, 'r');

        $streamDecorator = new Base64Stream(new PregReplaceFilterStream(Psr7\stream_for($f), '/[^a-zA-Z0-9\/\+=]/', ''));
        $handle = StreamWrapper::getResource($streamDecorator);

        $horg = fopen($org, 'r');
        $this->assertTrue(
            stream_get_contents($handle) === stream_get_contents($horg)
        );

        fclose($horg);
        fclose($handle);
        fclose($f);
    }

    public function testWriteAndDetach()
    {
        $org = './tests/_data/blueball.png';
        $contents = file_get_contents($org);

        for ($i = 1; $i < strlen($contents); ++$i) {

            $f = fopen('php://temp', 'r+');
            $stream = Psr7\stream_for($f);

            $ostream = new Base64Stream(new NonClosingStream($stream));
            for ($j = 0; $j < strlen($contents); $j += $i) {
                $ostream->write(substr($contents, $j, $i));
            }
            $ostream->detach();

            $stream->rewind();
            $istream = new Base64Stream($stream);
            $this->assertEquals($contents, $istream->getContents());
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
            $f = fopen('php://temp', 'r+');
            $stream = Psr7\stream_for($f);

            $ostream = new Base64Stream(new NonClosingStream($stream));
            for ($j = 0; $j < strlen($str); $j += $i) {
                $ostream->write(substr($str, $j, $i));
            }
            $ostream->close();

            $stream->rewind();
            $istream = new Base64Stream($stream);
            $this->assertEquals($str, $istream->getContents());
        }
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testSeekUnsopported()
    {
        $str = 'Sweetest little pie';
        $stream = Psr7\stream_for(base64_encode($str));
        $test = new Base64Stream($stream);
        $this->assertFalse($test->isSeekable());
        $test->seek(0);
    }
}
