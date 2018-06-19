<?php
/**
 * This file is part of the ZBateson\StreamDecorators project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */
namespace ZBateson\StreamDecorators;

use GuzzleHttp\Psr7\LimitStream;

/**
 * Extention of GuzzleHttp\Psr7\LimitStream that doesn't close the underlying
 * stream when closed or detached.
 *
 * @author Zaahid Bateson
 */
class NonClosingLimitStream extends LimitStream
{
    public function close()
    {
        $this->stream = null;
    }

    public function detach()
    {
        $this->stream = null;
    }
}
