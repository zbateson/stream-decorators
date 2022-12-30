<?php

namespace ZBateson\StreamDecorators;

use GuzzleHttp\Psr7;
use LegacyPHPUnit\TestCase;
use RuntimeException;
use ZBateson\MbWrapper\MbWrapper;

/**
 * Description of CharsetStreamTest
 *
 * @group CharsetStream
 * @covers ZBateson\StreamDecorators\CharsetStream
 * @author Zaahid Bateson
 */
class CharsetStreamTest extends TestCase
{
    private $converter;

    public function testRead()
    {
        $str = \str_repeat('هلا هلا شخبار بعد؟ شلون تبرمج؟', 50);

        $stream = Psr7\Utils::streamFor($this->converter->convert($str, 'UTF-8', 'UTF-32'));

        for ($i = 1; $i < \mb_strlen($str, 'UTF-8'); ++$i) {
            $stream->rewind();
            $csStream = new CharsetStream(new NonClosingStream($stream), 'UTF-32', 'UTF-8');

            for ($j = 0; $j < \mb_strlen($str, 'UTF-8'); $j += $i) {
                $char = $csStream->read($i);
                $this->assertSame(\mb_substr($str, $j, $i, 'UTF-8'), $char, "Read {$j} failed at {$i} step");
            }
            $this->assertSame(\mb_strlen($str, 'UTF-8'), $csStream->tell(), "Final tell failed with {$i} step");
        }
    }

    public function testReadContents()
    {
        $str = \str_repeat('هلا هلا شخبار بعد؟ شلون تبرمج؟', 50);

        for ($i = 0; $i < \mb_strlen($str); ++$i) {
            $substr = \mb_substr($str, 0, $i + 1, 'UTF-8');
            $stream = Psr7\Utils::streamFor($this->converter->convert($substr, 'UTF-8', 'UTF-16'));
            $csStream = new CharsetStream($stream, 'UTF-16', 'UTF-8');
            $this->assertSame($substr, $csStream->getContents());
        }
    }

    public function testReadToEof()
    {
        $str = \str_repeat('هلا هلا شخبار بعد؟ شلون تبرمج؟', 10);

        for ($i = 0; $i < \mb_strlen($str, 'UTF-8'); ++$i) {
            $substr = \mb_substr($str, $i, null, 'UTF-8');
            $stream = Psr7\Utils::streamFor($this->converter->convert($substr, 'UTF-8', 'WINDOWS-1256'));
            $csStream = new CharsetStream($stream, 'WINDOWS-1256', 'UTF-8');

            for ($j = 0; ! $csStream->eof(); ++$j) {
                $read = $csStream->read(1);
                $this->assertSame(\mb_substr($substr, $j, 1, 'UTF-8'), $read, "Failed reading to EOF on substr {$i} iteration {$j}");
            }
        }
    }

    public function testReadUtf16LeToEof()
    {
        $str = \str_repeat('هلا هلا شخبار بعد؟ شلون تبرمج؟', 10);

        for ($i = 0; $i < \mb_strlen($str, 'UTF-8'); ++$i) {
            $substr = \mb_substr($str, $i, null, 'UTF-8');
            $stream = Psr7\Utils::streamFor($this->converter->convert($substr, 'UTF-8', 'UTF-16LE'));
            $csStream = new CharsetStream($stream, 'UTF-16LE', 'UTF-8');

            for ($j = 0; ! $csStream->eof(); ++$j) {
                $read = $csStream->read(1);
                $this->assertSame(\mb_substr($substr, $j, 1, 'UTF-8'), $read, "Failed reading to EOF on substr {$i} iteration {$j}");
            }
        }
    }

    public function testReadToEmpty()
    {
        $str = \str_repeat('هلا هلا شخبار بعد؟ شلون تبرمج؟', 10);
        $stream = Psr7\Utils::streamFor($this->converter->convert($str, 'UTF-8', 'WINDOWS-1256'));
        $csStream = new CharsetStream($stream, 'WINDOWS-1256', 'UTF-8');
        $i = 0;

        while (($chr = $csStream->read(1)) !== '') {
            $this->assertSame(\mb_substr($str, $i++, 1, 'UTF-8'), $chr, "Failed reading to false on substr {$i}");
        }
    }

    public function testGetSize()
    {
        $str = 'Sweetest little pie';
        $stream = Psr7\Utils::streamFor($this->converter->convert($str, 'UTF-8', 'UTF-16'));
        $csStream = new CharsetStream($stream, 'UTF-16', 'UTF-8');
        $this->assertNull($csStream->getSize());
    }

    public function testTell()
    {
        $str = 'é J\'interdis aux marchands de vanter trop leur marchandises. Car '
            . 'ils se font vite pédagogues et t\'enseignent comme but ce qui '
            . 'n\'est par essence qu\'un moyen, et te trompant ainsi sur la '
            . 'route à suivre les voilà bientôt qui te dégradent, car si leur '
            . 'musique est vulgaire ils te fabriquent pour te la vendre une âme '
            . 'vulgaire.é';
        $stream = Psr7\Utils::streamFor($str);

        for ($i = 1; $i < \strlen($str); ++$i) {
            $stream->rewind();
            $csStream = new CharsetStream(new NonClosingStream($stream));

            for ($j = 0; $j < \strlen($str); $j += $i) {
                $this->assertSame($j, $csStream->tell(), "Tell at {$j} failed with {$i} step");
                $csStream->read($i);
            }
            $this->assertSame(\strlen($str), $csStream->tell(), "Final tell failed with {$i} step");
        }
    }

    public function testSeekUnsopported()
    {
        $stream = Psr7\Utils::streamFor('Sweetest little pie');
        $test = new CharsetStream($stream);
        $this->assertFalse($test->isSeekable());
        $exceptionThrown = false;

        try {
            $test->seek(0);
        } catch (RuntimeException $exc) {
            $exceptionThrown = true;
        }
        $this->assertTrue($exceptionThrown);
    }

    public function testWrite()
    {
        $str = \str_repeat('هلا هلا شخبار بعد؟ شلون تبرمج؟', 10);

        for ($i = 1; $i < \mb_strlen($str); ++$i) {
            $stream = Psr7\Utils::streamFor(\fopen('php://temp', 'r+'));
            $oStream = new CharsetStream(new NonClosingStream($stream), 'UTF-32', 'UTF-8');

            for ($j = 0; $j < \mb_strlen($str, 'UTF-8'); $j += $i) {
                $oStream->write(\mb_substr($str, $j, $i, 'UTF-8'));
            }
            $stream->rewind();

            $iStream = new CharsetStream(new NonClosingStream($stream), 'UTF-32', 'UTF-8');
            $this->assertSame($str, $iStream->getContents());

            $stream->rewind();
            $streamContents = $stream->getContents();
            $this->assertNotEquals($str, $streamContents);
            $this->assertGreaterThan(\strlen($str), \strlen($streamContents));
            $this->assertSame($str, $this->converter->convert($streamContents, 'UTF-32', 'UTF-8'));
        }
    }

    protected function legacySetUp()
    {
        $this->converter = new MbWrapper();
    }
}
