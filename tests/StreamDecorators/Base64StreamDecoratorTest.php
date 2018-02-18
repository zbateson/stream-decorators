<?php
namespace ZBateson\StreamDecorators;

use PHPUnit_Framework_TestCase;
use GuzzleHttp\Psr7;

/**
 * Description of Base64StreamDecoratorTest
 *
 * @group Base64StreamDecorator
 * @covers ZBateson\StreamDecorators\Base64StreamDecorator
 * @author Zaahid Bateson
 */
class Base64StreamDecoratorTest extends PHPUnit_Framework_TestCase
{
    public function testReadAndRewind()
    {
        $str = str_repeat('é J\'interdis aux marchands de vanter trop leur marchandises. Car '
            . 'ils se font vite pédagogues et t\'enseignent comme but ce qui '
            . 'n\'est par essence qu\'un moyen, et te trompant ainsi sur la '
            . 'route à suivre les voilà bientôt qui te dégradent, car si leur '
            . 'musique est vulgaire ils te fabriquent pour te la vendre une âme '
            . 'vulgaire.é', 30);
        $stream = Psr7\stream_for(base64_encode($str));
        $b64Stream = new Base64StreamDecorator($stream);

        for ($i = 1; $i < strlen($str); ++$i) {
            $b64Stream->rewind();
            for ($j = 0; $j < strlen($str); $j += $i) {
                $this->assertEquals(substr($str, $j, $i), $b64Stream->read($i), "Read $j failed at $i step");
            }
            $this->assertEquals(strlen($str), $b64Stream->tell(), "Final tell failed with $i step");
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
            $stream = Psr7\stream_for(base64_encode($substr));
            $b64Stream = new Base64StreamDecorator($stream);
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
            $b64Stream = new Base64StreamDecorator($stream);
            for ($j = $i; !$b64Stream->eof(); ++$j) {
                $this->assertEquals(substr($str, $j, 1), $b64Stream->read(1), "Failed reading to EOF on substr $i iteration $j");
            }
        }
    }

    public function testReadIgnoresNonB64Characters()
    {
        $str = "V3Vi  YWx1YmFkdWJkdXV1dXV1dXV1dXV1\t()*^^&((&(*@#(*dXV1dXV      &&#*@**# 1dXV1dXV\r\n1dXV___---1dXViIQ==";
        $stream = Psr7\stream_for($str);
        $b64Stream = new Base64StreamDecorator($stream);
        $this->assertEquals('Wubalubadubduuuuuuuuuuuuuuuuuuuuuuuuuuuuub!', $b64Stream->getContents());
    }

    public function testGetSize()
    {
        $str = 'é J\'interdis aux marchands de vanter trop leur marchandises. Car '
            . 'ils se font vite pédagogues et t\'enseignent comme but ce qui '
            . 'n\'est par essence qu\'un moyen, et te trompant ainsi sur la '
            . 'route à suivre les voilà bientôt qui te dégradent, car si leur '
            . 'musique est vulgaire ils te fabriquent pour te la vendre une âme '
            . 'vulgaire.é';
        $stream = Psr7\stream_for(base64_encode($str));
        $b64Stream = new Base64StreamDecorator($stream);
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
        $b64Stream = new Base64StreamDecorator($stream);

        for ($i = 1; $i < strlen($str); ++$i) {
            $b64Stream->rewind();
            for ($j = 0; $j < strlen($str); $j += $i) {
                $this->assertEquals($j, $b64Stream->tell(), "Tell at $j failed with $i step");
                $b64Stream->read($i);
            }
            $this->assertEquals(strlen($str), $b64Stream->tell(), "Final tell failed with $i step");
        }
    }

    public function testSeek()
    {
        $this->setExpectedException('RuntimeException');
        $stream = Psr7\stream_for(base64_encode('test'));
        $b64Stream = new Base64StreamDecorator($stream);
        $b64Stream->seek(10);
    }
}
