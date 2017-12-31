<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */
namespace ZBateson\MailMimeParser\Stream;

use Psr\Http\Message\StreamInterface;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use RuntimeException;

/**
 * GuzzleHttp\Psr7 stream decoder extension for base64 streams.
 *
 * Pass it a Psr7 stream, and the extension will decode/encode bytes as they're
 * read/written.
 *
 * Because the stream may contain non-base64 characters (e.g. newlines, or
 * invalid characters that should be ignored), seeking is only supported back to
 * the beginning of the stream.
 *
 * The size of the stream is also not determinable without reading it, and so
 * returns null.
 *
 * @author Zaahid Bateson
 */
class Base64StreamDecorator implements StreamInterface
{
    use StreamDecoratorTrait {
        StreamDecoratorTrait::getSize as private getEncodedSize;
        StreamDecoratorTrait::eof as private eofEncoded;
        StreamDecoratorTrait::tell as private tellEncoded;
        StreamDecoratorTrait::seek as private seekEncoded;
        StreamDecoratorTrait::write as private writeEncoded;
        StreamDecoratorTrait::read as private readEncoded;
    }

    /**
     * @var int current read/write position
     */
    private $position = 0;

    /**
     * @var int calculated read/write remainder for next read or write
     *      operation.
     */
    private $remainder = 0;

    /**
     * Not determinable without reading the contents of the stream to filter out
     * invalid bytes/new lines.
     *
     * @return null
     */
    public function getSize()
    {
        return null;
    }

    /**
     * Overridden to return the calculated position as bytes in the decoded
     * stream.
     *
     * @return int
     */
    public function tell()
    {
        return $this->position;
    }

    /**
     * Overridden to seek to the correct un-encoded position in the underlying
     * base64 encoded stream.
     *
     * @param int $offset
     * @param int $whence
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        $pos = $offset;
        if ($whence === SEEK_CUR) {
            $pos = $this->tell() + $offset;
        }
        if ($pos !== 0 || $whence === SEEK_END) {
            throw new RuntimeException(
                "Only rewinding or seeking to the beginning are supported"
            );
        }

        $this->position = 0;
        $this->remainder = 0;
        $this->seekEncoded(0);
    }

    /**
     * Returns a map consisting of char keys mapped to their integer values.
     *
     * The array is initialized once statically, and returned on subsequent
     * calls.
     *
     * @staticvar array $map
     * @return array
     */
    private function getBase64CharMap() {
        static $map = null;
        if ($map === null) {
            $map['='] = null;
            $i = 0;
            for ($char = ord('A'); $char <= ord('Z'); ++$char) {
                $map[chr($char)] = $i++;
            }
            for ($char = ord('a'); $char <= ord('z'); ++$char) {
                $map[chr($char)] = $i++;
            }
            for ($char = ord('0'); $char <= ord('9'); ++$char) {
                $map[chr($char)] = $i++;
            }
            $map['+'] = $i++;
            $map['/'] = $i++;
        }
        return $map;
    }

    /**
     * Reads the next byte from the underlying base64 stream, and converts the
     * returned base64 byte into its mapped value.
     *
     * @return string
     */
    private function readNextBase64()
    {
        $chart = $this->getBase64CharMap();
        while (($r = $this->readEncoded(1)) !== '') {
            if (isset($chart[$r])) {
                return $chart[$r];
            }
        }
        return null;
    }

    /**
     * Calculates the value of the passed base64 $byte and $next byte, assigning
     * any remainder to $this->remainder.
     * 
     * @param string $byte
     * @param string $next
     * @return string
     */
    private function calculateByteAndRemainder($byte, $next)
    {
        if ($this->position % 3 === 0) {
            $byte = $next << 2;
            $next = $this->readNextBase64();
            if ($next === null) {
                $this->remainder = 0;
                return $byte;
            }
            $byte |= ($next >> 4);
            $this->remainder = ($next & 0xf) << 4;
        } elseif (($this->position - 1) % 3 === 0) {
            $byte |= ($next >> 2);
            $this->remainder = ($next & 0x3) << 6;
        } else {
            $byte |= $next;
        }

        return $byte;
    }

    /**
     *
     * @return type
     */
    private function readNextByte()
    {
        $next = $this->readNextBase64();
        if ($next === null) {
            return null;
        }
        $byte = $this->calculateByteAndRemainder($this->remainder, $next);

        ++$this->position;
        return chr($byte);
    }

    private function readAndFilterEncodedBlock($length)
    {
        $bytes = '';
        while (strlen($bytes) < $length) {
            $read = $this->readEncoded($length - strlen($bytes));
            if ($read === '') {
                return $bytes;
            }
            $bytes .= preg_replace('/[^a-zA-Z0-9\/\+\=]+/', '', $read);
        }
        return $bytes;
    }

    private function readUnalignedBytesAndConcat($length, &$bytes)
    {
        for ($i = 0; $i < $length; ++$i) {
            $byte = $this->readNextByte();
            if ($byte === null) {
                return -1;
            }
            $bytes .= $byte;
        }
        return $i;
    }

    private function readAlignedBytesAndConcat($length, &$bytes)
    {
        $readEncoded = intval(($length * 4) / 3);
        $readEncoded -= $readEncoded % 4;   // leave off partial blocks
        $decoded = base64_decode($this->readAndFilterEncodedBlock($readEncoded));
        $length -= strlen($decoded);
        $this->position += strlen($decoded);
        $bytes .= $decoded;
        return $length;
    }

    private function readBytes($length)
    {
        $bytes = '';
        $positionRemainder = 3 - (($this->position + 3 - 1) % 3 + 1);
        $read = $this->readUnalignedBytesAndConcat(min([$length, $positionRemainder]), $bytes);
        if ($read === -1) {
            return $bytes;
        }
        $length -= $read;
        if ($length > 2) {
            $length = $this->readAlignedBytesAndConcat($length, $bytes);
        }
        $this->readUnalignedBytesAndConcat($length, $bytes);
        return $bytes;
    }

    /**
     *
     * @param type $length
     * @return type
     */
    public function read($length)
    {
        // let Guzzle decide what to do.
        if ($length <= 0 || $this->eof()) {
            return $this->readEncoded($length);
        }
        $bytes = $this->readBytes($length);
        return $bytes;
    }
}
