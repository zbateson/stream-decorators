<?php
namespace ZBateson\MailMimeParser\Stream;

use PHPUnit_Framework_TestCase;
use GuzzleHttp\Psr7;

/**
 * Description of Base64StreamDecoratorTest
 *
 * @group Stream
 * @group Base64StreamDecorator
 * @covers ZBateson\MailMimeParser\Stream\Base64StreamDecorator
 * @author Zaahid Bateson
 */
class Base64StreamDecoratorTest extends PHPUnit_Framework_TestCase
{
    public function testReadAndRewind()
    {
        $str = 'Feeling very uncreative at the moment, so this is the best I\'ve got';
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
        $str = 'Wubalubadubduuuuuuuuuuuuuuuuuuuuuuuuuuuuub!';
        for ($i = 0; $i < strlen($str); ++$i) {
            $substr = substr($str, 0, $i + 1);
            $stream = Psr7\stream_for(base64_encode($substr));
            $b64Stream = new Base64StreamDecorator($stream);
            $this->assertEquals($substr, $b64Stream->getContents());
        }
    }

    public function testReadToEof()
    {
        $str = 'Feeling very uncreative at the moment, so this is the best I\'ve got';

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
        $str = 'Wubalubadubduuuuuuuuuuuuuuuuuuuuuuuuuuuuub!';

        $stream = Psr7\stream_for(base64_encode($str));
        $b64Stream = new Base64StreamDecorator($stream);
        $this->assertNull($b64Stream->getSize());
    }

    public function testTell()
    {
        $str = 'Feeling very uncreative at the moment, so this is the best I\'ve got';
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
