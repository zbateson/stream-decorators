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
     * Finds the next end-of-line character to ensure a line isn't broken up
     * while buffering.
     *
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
     * Removes invalid characters from a uuencoded string, and 'BEGIN' and 'END'
     * line headers and footers from the passed string before returning it.
     *
     * @param string $str
     * @return string
     */
    private function filterEncodedString($str)
    {
        return preg_replace('/[^a-zA-Z0-9\/\+=]/', '', $str);
    }

    /**
     * Buffers bytes into $this->buffer, removing uuencoding headers and footers
     * and decoding them.
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
     *
     * @param string $string
     * @codeCoverageIgnore
     */
    public function write($string)
    {
        // not implemented yet
    }
}
