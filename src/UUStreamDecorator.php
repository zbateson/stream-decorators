<?php
/**
 * This file is part of the ZBateson\StreamDecorators project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */
namespace ZBateson\StreamDecorators;

/**
 * GuzzleHttp\Psr7 stream decoder extension for UU-Encoded streams.
 *
 * Extends AbstractMimeTransferStreamDecorator, which prevents getSize and
 * seeking to anywhere except the beginning (rewinding).
 *
 * The size of the underlying stream and the position of bytes can't be
 * determined because the number of encoded bytes is indeterminate without
 * reading the entire stream.
 *
 * @author Zaahid Bateson
 */
class UUStreamDecorator extends AbstractMimeTransferStreamDecorator
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
     * Calls AbstractMimeTransferStreamDecorator::seek, which throws a
     * RuntimeException if attempting to seek to a non-zero position.
     *
     * Overridden to reset buffers.
     *
     * @param int $offset
     * @param int $whence
     * @throws RuntimeException
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        parent::seek($offset, $whence);
        // no exception thrown if reached here...
        $this->bufferLength = 0;
        $this->buffer = '';
    }

    /**
     * Finds the next end-of-line character to ensure a line isn't broken up
     * while buffering.
     *
     * @return string
     */
    private function readToEndOfLine($length)
    {
        $str = $this->readRaw($length);
        if ($str === false || $str === '') {
            return '';
        }
        while (substr($str, -1) !== "\n") {
            $chr = $this->readRaw(1);
            if ($chr === false || $chr === '') {
                break;
            }
            $str .= $chr;
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
        $ret = str_replace("\r", '', $str);
        $ret = preg_replace('/[^\x21-\xf5`\n]/', '`', $ret);
        if ($this->position === 0) {
            $ret = preg_replace('/^\s*begin[^\r\n]+\s*$|^\s*end\s*$/im', '', $ret);
        } else {
            $ret = preg_replace('/^\s*end\s*$/im', '', $ret);
        }
        return trim($ret);
    }

    /**
     * Buffers bytes into $this->buffer, removing uuencoding headers and footers
     * and decoding them.
     */
    private function readRawBytesIntoBuffer()
    {
        // 5040 = 63 * 80, seems to be good balance for buffering in benchmarks
        // testing with a simple 'if ($length < x)' and calculating a better
        // size reduces speeds by up to 4x
        $encoded = $this->filterEncodedString($this->readToEndOfLine(5040));
        if ($encoded === '') {
            $this->buffer = '';
        } else {
            //var_dump($encoded);
            $this->buffer = convert_uudecode($encoded);
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

    public function write($string)
    {
        // not implemented yet
    }
}
