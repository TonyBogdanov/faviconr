<?php

namespace Faviconr\Faviconr;

class GDNotAvailableException extends \Exception
{
    public function __construct()
    {
        parent::__construct('The GD library is either not installed or not enabled.');
    }
}