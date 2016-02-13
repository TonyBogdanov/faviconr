<?php
/**
 * This file is part of the Faviconr package.
 *
 * (c) Tony Bogdanov <support@tonybogdanov.com>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

namespace Faviconr\Faviconr;

class ImageMagickNotAvailableException extends \Exception
{
    public function __construct()
    {
        parent::__construct('The ImageMagick library is either not installed or not enabled.');
    }
}