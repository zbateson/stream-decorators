<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */
namespace ZBateson\MailMimeParser\Stream;

/**
 * 
 *
 * @author Zaahid Bateson
 */
class UUStreamDecorator extends AbstractMimeTransferStreamDecorator
{
    private $bufferLength = 0;
    private $buffer = '';

    public function seek($offset, $whence = SEEK_SET)
    {
        parent::seek($offset, $whence);
        // no exception thrown if reached here...
        $this->bufferLength = 0;
        $this->buffer = '';
    }

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

    private function getDecodedBytes($length)
    {
        $data = $this->buffer;
        $retLen = $this->bufferLength;
        while ($retLen < $length) {
            $this->readRawBytesIntoBuffer();
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
     *
     * @param type $length
     * @return type
     */
    public function read($length)
    {
        // let Guzzle decide what to do.
        if ($length <= 0 || ($this->eof() && $this->bufferLength === 0)) {
            return $this->readRaw($length);
        }

        return $this->getDecodedBytes($length);
    }

    public function write($string)
    {
        // not implemented yet
    }
}
