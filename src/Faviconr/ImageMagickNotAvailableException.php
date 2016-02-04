<?php

namespace Faviconr\Faviconr;

class ImageMagickNotAvailableException extends \Exception
{
    public function __construct()
    {
        parent::__construct('The ImageMagick library is either not installed or not enabled.');
    }
}