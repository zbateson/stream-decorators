<?php

namespace ZBateson\StreamDecorators;

use GuzzleHttp\Psr7;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(NonClosingStream::class)]
#[Group('NonClosingStream')]
class NonClosingStreamTest extends TestCase
{
    public function testClose() : void
    {
        $str = 'Testacular';
        $org = Psr7\Utils::streamFor($str);
        $stream = new NonClosingStream($org);
        $stream->close();
        $this->assertSame($org->getContents(), 'Testacular');
    }

    public function testDetach() : void
    {
        $str = 'Testacular';
        $org = Psr7\Utils::streamFor($str);
        $stream = new NonClosingStream($org);
        $stream->detach();
        $this->assertSame($org->getContents(), 'Testacular');
    }
}
