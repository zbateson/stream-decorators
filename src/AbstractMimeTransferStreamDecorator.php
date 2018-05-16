<?php
/**
 * This file is part of the ZBateson\StreamDecorators project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */
namespace ZBateson\StreamDecorators;

use Psr\Http\Message\StreamInterface;
use GuzzleHttp\Psr7\StreamDecoratorTrait;

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
     * To determine the size of the underlying stream, the entire contents are
     * read and discarded.  Therefore calling getSize is an expensive operation.
     *
     * @return int
     */
    public function getSize()
    {
        return $this->getSizeWithSeekBack(true);
    }

    /**
     * Can optionally seek back to the current position.
     *
     * Useful when called from seek with SEEK_END, there's no need to seek back
     * to the current position when called within seek.
     *
     * @param type $seekBack
     */
    private function getSizeWithSeekBack($seekBack) {
        $pos = $this->position;
        $this->rewind();
        while (!$this->eof()) {
            $this->read(1048576);
        }
        $length = $this->position;
        if ($seekBack) {
            $this->seek($pos);
        }
        return $length;
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
     * Called before seeking to the specified offset, can be overridden by
     * sub-classes to reset internal buffers, etc...
     */
    protected function beforeSeek() {
        // do nothing.
    }

    /**
     * Seeks to the given position.
     *
     * This operation basically reads and discards to the given position because
     * the size of the underlying stream can't be calculated otherwise, and is
     * an expensive operation.
     *
     * @param int $offset
     * @param int $whence
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        $pos = $offset;
        if ($whence === SEEK_CUR) {
            $pos = $this->tell() + $offset;
        } elseif ($whence === SEEK_END) {
            $pos = $this->getSizeWithSeekBack(false) + $offset;
        }
        // $this->position may not report actual position, for instance if the
        // underlying stream has been moved ahead.  Checking if the requested
        // position is the same as $pos before moving can cause problems when
        // using a LimitStream for instance after the parent stream has been
        // read from, etc...
        $this->beforeSeek($pos);
        $this->seekRaw(0);
        $this->position = 0;
        $this->read($pos);
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
     * @return string the string that was read
     */
    public abstract function read($length);
}
