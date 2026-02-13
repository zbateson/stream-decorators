<?php

namespace ZBateson\StreamDecorators;

use GuzzleHttp\Psr7;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(PregReplaceFilterStream::class)]
#[Group('PregReplaceFilterStream')]
class PregReplaceFilterStreamTest extends TestCase
{
    public function testRead() : void
    {
        $stream = Psr7\Utils::streamFor('a-ll t-h-e k-ing\'s me-n');
        $test = new PregReplaceFilterStream($stream, '/\-/', '');
        $this->assertSame('all the king\'s men', $test->getContents());
    }

    public function testReadBuffered() : void
    {
        $str = \str_repeat('All the King\'s Men ', 8000);
        $filter = \str_repeat('A-l-l t-h-e K-in-g\'s M-en ', 8000);
        $stream = Psr7\Utils::streamFor($filter);

        $test = new PregReplaceFilterStream($stream, '/\-/', '');
        for ($i = 0; $i < \strlen($str); $i += 10) {
            $this->assertSame(\substr($str, $i, 10), $test->read(10));
        }
    }

    public function testSeekUnsopported() : void
    {
        $stream = Psr7\Utils::streamFor('a-ll t-h-e k-ing\'s me-n');
        $test = new PregReplaceFilterStream($stream, '/\-/', '');
        $this->assertFalse($test->isSeekable());
        $exceptionThrown = false;
        try {
            $test->seek(0);
        } catch (RuntimeException $exc) {
            $exceptionThrown = true;
        }
        $this->assertTrue($exceptionThrown);
    }
}
