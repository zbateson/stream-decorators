<?php
/**
 * This file is part of the ZBateson\StreamDecorators project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */
namespace ZBateson\StreamDecorators;

/**
 * GuzzleHttp\Psr7 stream decoder extension for base64 streams.
 *
 * Extends AbstractMimeTransferStreamDecorator, which prevents getSize and
 * seeking to anywhere except the beginning (rewinding).
 *
 * The size of the underlying stream and the position of bytes can't be
 * determined because the amount of whitespace isn't defined, so only a read
 * operation to the end of the stream could return the correct size.
 *
 * @author Zaahid Bateson
 */
class Base64StreamDecorator extends AbstractMimeTransferStreamDecorator
{
    /**
     * @var int calculated read/write remainder for next read or write
     *      operation.
     */
    protected $remainder = 0;

    /**
     * Calls AbstractMimeTransferStreamDecorator::seek, which throws a
     * RuntimeException if attempting to seek to a non-zero position.
     *
     * Overridden to reset the calculated remainder when rewinding the stream.
     *
     * @param int $offset
     * @param int $whence
     * @throws RuntimeException
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        parent::seek($offset, $whence);
        $this->remainder = 0;
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
        while (($r = $this->readRaw(1)) !== '') {
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
     * Reads the next binary byte by calculating it from the underlying base64
     * stream, advances the read position ($this->position) and returns the
     * byte.
     *
     * @return string
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

    /**
     * Reads $length number of bytes from the underlying raw stream, filtering
     * out invalid base64 bytes (e.g. newlines, or any byte not within the valid
     * range).
     *
     * The method continues reading until $length number of bytes can be
     * returned, or the end of the stream has been reached.
     *
     * @param int $length
     * @return string
     */
    private function readAndFilterRawBlock($length)
    {
        $bytes = '';
        while (strlen($bytes) < $length) {
            $read = $this->readRaw($length - strlen($bytes));
            if ($read === '') {
                return $bytes;
            }
            $bytes .= preg_replace('/[^a-zA-Z0-9\/\+\=]+/', '', $read);
        }
        return $bytes;
    }

    /**
     * Reads the number of bytes denoted by $length into the passed $bytes
     * string by calculating their values using $this->remainder, and keeping
     * any additional remainders in $this->remainder.
     *
     * This method is called when $this->position is not 3-byte aligned and the
     * next byte to be read needs to be calculated either at the beginning of a
     * read operation or at the end.
     *
     * The method returns -1 if the end of the stream has been (even if bytes
     * have been read and concatenated to the passed $bytes string).  Otherwise,
     * the number of bytes and concatenated to $bytes read is returned.
     *
     * @param int $length
     * @param string $bytes
     * @return int
     */
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

    /**
     * Reads up to $length number of bytes when $this->position is 3-bytes
     * aligned.  If $length is not 3-byte aligned, the last block will not be
     * read -- passing a $length of less than 3 will result in 0 bytes being
     * read.
     *
     * This method uses base64_decode which is much faster than the calculated
     * implementation in this class.
     *
     * The method returns the number of bytes read.
     *
     * @param int $length
     * @param string $bytes
     * @return int
     */
    private function readAlignedBytesAndConcat($length, &$bytes)
    {
        $readRaw = intval(($length * 4) / 3);
        $readRaw -= $readRaw % 4;   // leave off partial blocks
        $decoded = base64_decode($this->readAndFilterRawBlock($readRaw));
        $length -= strlen($decoded);
        $this->position += strlen($decoded);
        $bytes .= $decoded;
        return $length;
    }

    /**
     * Determines how many bytes need to be read and calculated manually and
     * calls $this->readUnalignedBytesAndConcat for them, and how many can be
     * read without calculation using $this->readAlignedBytesAndConcat.
     *
     * @param int $length
     * @return string
     */
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
     * Reads up to $length bytes and returns them.
     *
     * @param int $length
     * @return string
     */
    public function read($length)
    {
        // let Guzzle decide what to do.
        if ($length <= 0 || $this->eof()) {
            return $this->readRaw($length);
        }
        return $this->readBytes($length);
    }

    /**
     *
     * @param string $string
     */
    public function write($string)
    {
        // not implemented yet
    }
}
