<?php
/**
 * This file is part of the ZBateson\StreamDecorators project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */
namespace ZBateson\StreamDecorators;

/**
 * GuzzleHttp\Psr7 stream decoder extension for quoted printable streams.
 *
 * 
 *
 * @author Zaahid Bateson
 */
class QuotedPrintableStreamDecorator extends AbstractMimeTransferStreamDecorator
{
    /**
     * @var string Last line of written text (used to maintain good line-breaks)
     */
    private $lastLine = '';

    /**
     * Overridden to reset buffered write block.
     */
    protected function beforeSeek() {
        $this->lastLine = '';
    }

    /**
     * Reads $length chars from the underlying stream, prepending the past $pre
     * to it first.
     *
     * If the characters read (including the prepended $pre) contain invalid
     * quoted-printable characters, the underlying stream is rewound by the
     * total number of characters ($length + strlen($pre)).
     *
     * The quoted-printable encoded characters are returned.  If the characters
     * read are invalid, '3D' is returned indicating an '=' character.
     *
     * @param int $length
     * @param string $pre
     * @return string
     */
    private function readEncodedChars($length, $pre = '')
    {
        $str = $pre . $this->readRaw($length);
        $len = strlen($str);
        if ($len > 0 && !preg_match('/^[0-9a-f]{2}$|^[\r\n].$/is', $str)) {
            $this->seekRaw(-$len, SEEK_CUR);
            return '3D';    // '=' character
        }
        return $str;
    }

    /**
     * Decodes the passed $block of text.
     *
     * If the last or before last character is an '=' char, indicating the
     * beginning of a quoted-printable encoded char, 1 or 2 additional bytes are
     * read from the underlying stream respectively.
     *
     * The decoded string is returned.
     *
     * @param string $block
     * @return string
     */
    private function decodeBlock($block)
    {
        if (substr($block, -1) === '=') {
            $block .= $this->readEncodedChars(2);
        } elseif (substr($block, -2, 1) === '=') {
            $first = substr($block, -1);
            $block = substr($block, 0, -1);
            $block .= $this->readEncodedChars(1, $first);
        }
        return quoted_printable_decode($block);
    }

    /**
     * Reads up to $length characters, appends them to the passed $str string,
     * and returns the total number of characters read.
     *
     * -1 is returned if there are no more bytes to read.
     *
     * @param int $length
     * @param string $append
     * @return int
     */
    private function readRawDecodeAndAppend($length, &$str)
    {
        $block = $this->readRaw($length);
        if ($block === false || $block === '') {
            return -1;
        }
        $decoded = $this->decodeBlock($block);
        $count = strlen($decoded);
        $str .= $decoded;
        return $count;
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
        $count = 0;
        $bytes = '';
        while ($count < $length) {
            $nRead = $this->readRawDecodeAndAppend($length - $count, $bytes);
            if ($nRead === -1) {
                break;
            }
            $this->position += $nRead;
            $count += $nRead;
        }
        return $bytes;
    }

    /**
     * Writes the passed string to the underlying stream after encoding it as
     * quoted-printable.
     *
     * Note that reading and writing to the same stream without rewinding is not
     * supported.
     *
     * @param string $string
     */
    public function write($string)
    {
        $write = rtrim(quoted_printable_encode($this->lastLine . $string), "\r\n");
        $this->writeRaw($write);
        $this->position += strlen($string);

        $lpos = strrpos($write, "\n");
        $lastLine = $write;
        if ($lpos !== false) {
            $lastLine = substr($write, $lpos + 1);
        }
        $this->lastLine = quoted_printable_decode($lastLine);
        $this->seekRaw(-strlen($lastLine), SEEK_CUR);
    }
}
