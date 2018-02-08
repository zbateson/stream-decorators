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
 * A default abstract implementation of GuzzleHttp\Psr7 StreamInterface for MIME
 * message transfer encodings that:
 *
 *  o Returns null for getSize
 *  o Allows seeking to the beginning of the stream (rewind), and throw a
 *    RuntimeException otherwise
 *  o Provides a $position member for subclasses and a default 'tell'
 *    implementation returning it
 *  o Uses StreamDecoratorTrait, setting method visibility to protected and
 *    changing their names to getRawSize, seekRaw, tellRaw, writeRaw and readRaw
 *
 * @author Zaahid Bateson
 */
abstract class AbstractMimeTransferStreamDecorator implements StreamInterface
{
    use StreamDecoratorTrait {
        StreamDecoratorTrait::getSize as protected getRawSize;
        StreamDecoratorTrait::seek as protected seekRaw;
        StreamDecoratorTrait::tell as protected tellRaw;
        StreamDecoratorTrait::write as protected writeRaw;
        StreamDecoratorTrait::read as protected readRaw;
    }

    /**
     * @var int current read/write position
     */
    protected $position = 0;

    /**
     * Not determinable without reading the contents of the stream to filter out
     * invalid bytes, new lines, header/footer data, etc...
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
     * Allows seeking to the beginning of the stream only (rewind), and
     * otherwise throws a RuntimeException
     *
     * @param int $offset
     * @param int $whence
     * @throws RuntimeException
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
        $this->seekRaw(0);
    }

    /**
     * Override to implement 'write' functionality and write bytes to the
     * underlying stream.
     *
     * @param string $string
     */
    public abstract function write($string);

    /**
     * Override to implement read functionality, reading $length bytes from the
     * underlying stream
     *
     * @param int $length
     */
    public abstract function read($length);
}
