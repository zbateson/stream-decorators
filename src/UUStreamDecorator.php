<?php
/**
 * This file is part of the ZBateson\StreamDecorators project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */
namespace ZBateson\StreamDecorators;

use Psr\Http\Message\StreamInterface;

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
     * @var string name of the UUEncoded file
     */
    protected $filename = null;

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
     * @var boolean set to true when the UU header is written
     */
    private $headerWritten = false;

    /**
     * @var boolean set to true when the UU footer is written
     */
    private $footerWritten = false;

    /**
     * @param StreamInterface $stream Stream to decorate
     * @param string optional file name
     */
    public function __construct(StreamInterface $stream, $filename = null)
    {
        parent::__construct($stream);
        $this->filename = $filename;
    }

    /**
     * Resets the internal buffers.
     */
    protected function beforeSeek() {
        $this->bufferLength = 0;
        $this->buffer = '';
        $this->flush();
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
            $matches = [];
            if (preg_match('/^\s*begin\s+[^\s+]\s+([^\r\n]+)\s*$/im', $ret, $matches)) {
                $this->filename = $matches[1];
            }
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

    /**
     * Writes the 'begin' UU header line.
     */
    private function writeUUHeader()
    {
        $filename = (empty($this->filename)) ? 'null' : $this->filename;
        $this->writeRaw("begin 666 $filename");
        $this->headerWritten = true;
    }

    /**
     * Writes the '`' and 'end' UU footer lines.
     */
    private function writeUUFooter()
    {
       $this->writeRaw("\r\n`\r\nend");
       $this->footerWritten = true;
    }

    /**
     * Writes the passed bytes to the underlying stream after encoding them.
     *
     * @param string $bytes
     */
    private function writeEncoded($bytes)
    {
        $encoded = preg_replace('/\r\n|\r|\n/', "\r\n", rtrim(convert_uuencode($bytes)));
        // removes ending '`' line
        $this->writeRaw("\r\n" . rtrim(substr($encoded, 0, -1)));
    }

    /**
     * Writes the passed string to the underlying stream after encoding it.
     *
     * Note that reading and writing to the same stream without rewinding is not
     * supported.
     *
     * Also note that some bytes may not be written until close, detach, seek or
     * flush are called.  This happens if written data doesn't align to a
     * complete uuencoded 'line' of 45 bytes.  In addition, the UU footer is
     * only written when one of the mentioned methods are called.
     *
     * @param string $string
     */
    public function write($string)
    {
        if ($this->position === 0) {
            $this->writeUUHeader();
        }
        $write = $this->remainder . $string;
        $nRem = strlen($write) % 45;

        $this->remainder = '';
        if ($nRem !== 0) {
            $this->remainder = substr($write, -$nRem);
            $write = substr($write, 0, -$nRem);
        }
        if ($write !== '') {
            $this->writeEncoded($write);
        }
        $this->position += strlen($string);
    }

    /**
     * Writes out any remaining bytes to the underlying stream.
     */
    public function flush()
    {
        if ($this->remainder !== '') {
            $this->writeEncoded($this->remainder);
        }
        $this->remainder = '';
        if ($this->headerWritten && !$this->footerWritten) {
            $this->writeUUFooter();
        }
        parent::flush();
    }

    /**
     * Returns the filename set in the UUEncoded header (or null)
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * Sets the UUEncoded header file name written in the 'begin' header line.
     *
     * @param string $filename
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;
    }
}
