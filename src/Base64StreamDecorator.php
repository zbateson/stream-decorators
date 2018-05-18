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
     * Resets the internal buffers.
     */
    protected function beforeSeek() {
        $this->bufferLength = 0;
        $this->buffer = '';
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
     * Encodes the passed parameter to base64, and writes bytes to the
     * underlying stream, adding a CRLF character after every 76 characters.
     *
     * @param string $write
     */
    private function encodeAndWriteChunked($write)
    {
        $p = $this->tellRaw();
        $encoded = base64_encode($write);
        if ($p !== 0) {
            $next = 76 - ($p % 78);
            if (strlen($encoded) > $next) {
                $this->writeRaw(substr($encoded, 0, $next) . "\r\n");
                $encoded = substr($encoded, $next);
            }
        }
        $this->writeRaw(rtrim(chunk_split($encoded, 76)));
    }

    /**
     * Writes the passed string to the underlying stream after encoding it to
     * base64.
     *
     * Note that reading and writing to the same stream without rewinding is not
     * supported.
     *
     * @param string $string
     */
    public function write($string)
    {
        $write = $this->buffer . $string;
        $this->encodeAndWriteChunked($write);
        $this->buffer = '';

        $len = strlen($write);
        $this->position += strlen($string);
        // because each line is 76 characters (divisible by 4), we don't need
        // to worry about breaking a chunk up.
        if (($len % 3) !== 0) {
            $this->buffer = substr($write, -($len % 3));
            $this->seekRaw(-4, SEEK_CUR);
        }
    }
}
