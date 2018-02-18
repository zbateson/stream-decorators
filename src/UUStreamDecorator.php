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
    private function readToEndOfLine()
    {
        $str = '';
        while (($chr = $this->readRaw(1)) !== '') {
            $str .= $chr;
            if ($chr === "\n") {
                break;
            }
        }
        return $str;
    }

    /**
     * Buffers bytes into $this->buffer, removing uuencoding headers and footers
     * and decoding them.
     */
    private function readRawBytesIntoBuffer()
    {
        $prep = $this->readRaw(2048);
        $prep .= $this->readToEndOfLine();

        $pattern = '/(^\s*end\s*$|^\s*begin[^\r\n]+\s*$)/im';
        $prep = preg_replace($pattern, '', $prep);
        $nRead = strlen($prep);
        
        if ($nRead === 0) {
            $this->buffer = '';
            $this->bufferLength = 0;
            return;
        }

        $this->buffer = convert_uudecode(trim($prep));
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
