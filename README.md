# zbateson/stream-decorators

Psr7 stream decorators for character set conversion and common mail format content encodings.

[![Build Status](https://travis-ci.org/zbateson/StreamDecorators.svg?branch=master)](https://travis-ci.org/zbateson/StreamDecorators)
[![Code Coverage](https://scrutinizer-ci.com/g/zbateson/StreamDecorators/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/zbateson/StreamDecorators/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/zbateson/StreamDecorators/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/zbateson/StreamDecorators/?branch=master)
[![Total Downloads](https://poser.pugx.org/zbateson/stream-decorators/downloads)](https://packagist.org/packages/zbateson/stream-decorators)
[![Latest Stable Version](https://poser.pugx.org/zbateson/stream-decorators/version)](https://packagist.org/packages/zbateson/stream-decorators)

The goals of this project are to be:

* Well written
* Standards-compliant but forgiving
* Includable via composer
* Tested where possible

To include it for use in your project, please install via composer:

```
composer require zbateson/stream-decorators
```

## Requirements

StreamDecorators requires PHP 5.4 or newer or HHVM.  Tested on PHP 5.4, 5.5, 5.6, 7, 7.1 and 7.2 and HHVM 3.6, 3.12, 3.24 and 'current' on travis.

## Usage

```php
$stream = GuzzleHttp\Psr7\stream_for($handle);
$b64Stream = new ZBateson\StreamDecorators\Base64StreamDecorator($stream);
$charsetStream = new ZBateson\StreamDecorators\CharsetStreamDecorator($b64Stream, 'UTF-32', 'UTF-8');

while (($line = GuzzleHttp\Psr7\readline()) !== false) {
    echo $line, "\r\n";
}

```

The library consists of the following Psr\Http\Message\StreamInterface implementations:
* ZBateson\StreamDecorators\QuotedPrintableStreamDecorator
* ZBateson\StreamDecorators\Base64StreamDecorator
* ZBateson\StreamDecorators\UUStreamDecorator
* ZBateson\StreamDecorators\CharsetStreamDecorator

All of them take a single argument of a StreamInterface with the exception of CharsetStreamDecorators, which also takes $fromCharset and $toCharset as arguments respectively.

In addition, the library exposes a ZBateson\StreamDecorators\Util\CharsetConverter class which provides the following:
* a map of supported charsets with different names (aliases)
* better charset lookup (by removing special chars during lookup, etc...) to catch more charsets that may be found in Content-Type headers
* abstraction of the following mb_* and iconv functions, preferring mb_* for supported charsets:
* mb_convert_encoding, iconv with CharsetConverter::convert
* mb_substr, iconv_substr with CharsetConverter::getSubstr
* mb_strlen, iconv_strlen with CharsetConverter::getLength

## License

BSD licensed - please see [license agreement](https://github.com/zbateson/StreamDecorator/blob/master/LICENSE).
