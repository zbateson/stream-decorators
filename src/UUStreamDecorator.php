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
class QuotedPrintableStreamDecorator extends AbstractMimeTransferStreamDecorator
{
    private function decodeBlock($block)
    {
        if (substr($block, -1) === '=') {
            $block .= $this->readRaw(2);
        } elseif (substr($block, -2, 1) === '=') {
            $block .= $this->readRaw(1);
        }
        return quoted_printable_decode($block);
    }

    private function readRawAndDecode($length, &$bytes)
    {
        $block = $this->readRaw($length);
        if ($block === false || $block === '') {
            return -1;
        }
        $decoded = $this->decodeBlock($block);
        $count = strlen($decoded);
        if ($count > $length) {
            $this->seekRaw(-($count - $length), SEEK_CUR);
            $bytes .= substr($decoded, 0, $length);
            return $length;
        }
        $bytes .= $decoded;
        return $count;
    }

    /**
     *
     * @param type $length
     * @return type
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
            $nRead = $this->readRawAndDecode($length - $count, $bytes);
            if ($nRead === -1) {
                break;
            }
            $this->position += $nRead;
            $count += $nRead;
        }
        return $bytes;
    }

    public function write($string)
    {
        // not implemented yet
    }
}
