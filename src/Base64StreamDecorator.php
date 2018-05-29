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
 * @author Zaahid Bateson
 */
class Base64StreamDecorator extends AbstractMimeTransferStreamDecorator
{
    /**
     * @var string string of buffered bytes
     */
    private $buffer = '';

    /**
     * @var int number of bytes in $buffer
     */
    private $bufferLength = 0;

    /**
     * @var string remainder of write operation if the bytes didn't align to 3
     *      bytes
     */
    private $remainder = '';

    /**
     * @var int number of raw bytes written to the underlying stream
     */
    private $writePosition = 0;

    /**
     * Resets the internal buffers.
     */
    protected function beforeSeek() {
        $this->bufferLength = 0;
        $this->buffer = '';
        $this->flush();
    }

    /**
     * Reads to a 4-character boundary of valid base64 characters, ensuring a
     * base64 chunk isn't split during read operations.
     *
     * @param int $length number of encoded bytes to read
     * @return string
     */
    private function readToBase64Boundary($length)
    {
        $raw = $this->readRaw($length);
        if ($raw === false || $raw === '') {
            return '';
        }
        $str = $this->filterEncodedString($raw);
        $strlen = strlen($str);
        while (($strlen % 4) !== 0) {
            $raw = $this->readRaw(4 - ($strlen % 4));
            if ($raw === false || $raw === '') {
                break;
            }
            $append = $this->filterEncodedString($raw);
            $str .= $append;
            $strlen += strlen($append);
        }
        return $str;
    }

    /**
     * Removes invalid characters from an encoded base64 string.
     *
     * @param string $str
     * @return string
     */
    private function filterEncodedString($str)
    {
        return preg_replace('/[^a-zA-Z0-9\/\+=]/', '', $str);
    }

    /**
     * Buffers bytes into $this->buffer after decoding them.
     */
    private function readRawBytesIntoBuffer()
    {
        // 5148 is divisible by both 78 and 4.  With CRLF removed, would be
        // 5016 encoded bytes to decode if indeed each line is 76 characters
        $encoded = $this->readToBase64Boundary(5148);
        if ($encoded === '') {
            $this->buffer = '';
        } else {
            $this->buffer = base64_decode($encoded);
        }
        $this->bufferLength = strlen($this->buffer);
    }

    /**
     * Attempts to fill up to $length bytes of decoded bytes into $this->buffer,
     * and returns them.
     *
     * @param int $length
     * @return string
     */
    private function getDecodedBytes($length)
    {
        $data = $this->buffer;
        $retLen = $this->bufferLength;
        while ($retLen < $length) {
            $this->readRawBytesIntoBuffer($length);
            if ($this->bufferLength === 0) {
                break;
            }
            $retLen += $this->bufferLength;
            $data .= $this->buffer;
        }
        $ret = substr($data, 0, $length);
        $this->buffer = substr($data, $length);
        $this->bufferLength = strlen($this->buffer);
        $this->position += strlen($ret);
        return $ret;
    }

    /**
     * Returns true if the end of stream has been reached.
     *
     * @return type
     */
    public function eof()
    {
        return ($this->bufferLength === 0 && parent::eof());
    }

    /**
     * Attempts to read $length bytes after decoding them, and returns them.
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
        return $this->getDecodedBytes($length);
    }

    /**
     * Encodes the passed parameter to base64, adding a CRLF character after
     * every 76 characters, and returning the resulting string
     *
     * @param string $bytes
     */
    private function getEncodedAndChunkedString($bytes)
    {
        $encoded = base64_encode($bytes);
        $firstLine = '';
        if ($this->writePosition !== 0) {
            $next = 76 - ($this->writePosition % 78);
            if (strlen($encoded) > $next) {
                $firstLine = substr($encoded, 0, $next) . "\r\n";
                $encoded = substr($encoded, $next);
            }
        }
        $write = $firstLine . rtrim(chunk_split($encoded, 76));
        $this->writePosition += strlen($write);
        return $write;
    }

    /**
     * Writes the passed string to the underlying stream after encoding it to
     * base64.
     *
     * Note that reading and writing to the same stream without rewinding is not
     * supported.
     *
     * Also note that some bytes may not be written until close, detach, seek or
     * flush are called.  This happens if written data doesn't align to 3 bytes.
     * For instance if attempting to write a single byte 'M', writing out 'TQ=='
     * would require seeking back and overwriting 'Q==' if a subsequent byte is
     * written.  This is avoided by buffering, but requires indicating when the
     * last byte is written.
     *
     * @param string $string
     * @return int the number of bytes written
     */
    public function write($string)
    {
        $bytes = $this->remainder . $string;
        $len = strlen($bytes);
        if (($len % 3) !== 0) {
            $this->remainder = substr($bytes, -($len % 3));
            $bytes = substr($bytes, 0, $len - ($len % 3));
        } else {
            $this->remainder = '';
        }

        $write = $this->getEncodedAndChunkedString($bytes);
        $this->writeRaw($write);
        $written = strlen($string);
        $this->position += $written;
        return $written;
    }

    /**
     * Writes out any remaining bytes to the underlying stream.
     */
    public function flush()
    {
        if ($this->isWritable() && $this->remainder !== '') {
            $this->writeRaw($this->getEncodedAndChunkedString($this->remainder));
        }
        $this->remainder = '';
        $this->writePosition = 0;
        parent::flush();
    }
}
