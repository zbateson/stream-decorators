<?php
/**
 * This file is part of the ZBateson\StreamDecorator project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */
namespace ZBateson\StreamDecorators;

use Psr\Http\Message\StreamInterface;
use ZBateson\StreamDecorators\Util\CharsetConverter;

/**
 * GuzzleHttp\Psr7 stream decoder extension for base64 streams.
 *
 * Extends AbstractMimeTransferStreamDecorator, which prevents getSize and
 * seeking to anywhere except the beginning (rewinding).
 *
 * The size of the underlying stream and the position of chars can't be
 * determined because some charsets (e.g. UTF-8), may take between 1 and 6(?) or
 * so bytes, and without reading them they are not known.
 *
 * @author Zaahid Bateson
 */
class CharsetStreamDecorator extends AbstractMimeTransferStreamDecorator
{
    /**
     * @var \ZBateson\StreamDecorators\Util\CharsetConverter the charset
     *      converter
     */
    protected $converter = null;
    
    /**
     * @var string charset of the source stream
     */
    protected $fromCharset = 'ISO-8859-1';
    
    /**
     * @var string charset to convert to
     */
    protected $toCharset = 'UTF-8';

    /**
     * @var int number of characters in $buffer
     */
    private $bufferLength = 0;

    /**
     * @var string a buffer of characters read in the original $fromCharset
     *      encoding
     */
    private $buffer = '';

    /**
     * @param StreamInterface $stream Stream to decorate
     * @param string $fromCharset The underlying stream's charset
     * @param string $toCharset The charset to encode to
     */
    public function __construct(StreamInterface $stream, $fromCharset = 'ISO-8859-1', $toCharset = 'UTF-8')
    {
        parent::__construct($stream);
        $this->converter = new CharsetConverter();
        $this->fromCharset = $fromCharset;
        $this->toCharset = $toCharset;
    }

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
     * Reads a minimum of $length characters from the underlying stream in its
     * encoding into $this->buffer
     *
     * @param int $length
     */
    private function readRawCharsIntoBuffer($length)
    {
        $n = $length + 32;
        while ($this->bufferLength < $n) {
            $raw = $this->readRaw($n + 512);
            if ($raw === false || $raw === '') {
                return;
            }
            $this->buffer .= $raw;
            $this->bufferLength = $this->converter->getLength($this->buffer, $this->fromCharset);
        }
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
     * Reads up to $length decoded bytes from the underlying quoted-printable
     * encoded stream and returns them.
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
        $this->readRawCharsIntoBuffer($length);
        $numChars = min([$this->bufferLength, $length]);
        $chars = $this->converter->getSubstr($this->buffer, $this->fromCharset, 0, $numChars);
        
        $this->position += $numChars;
        $this->buffer = $this->converter->getSubstr($this->buffer, $this->fromCharset, $numChars);
        $this->bufferLength = $this->bufferLength - $numChars;

        return $this->converter->convert($chars, $this->fromCharset, $this->toCharset);
    }

    public function write($string)
    {
        // not implemented yet
    }
}
