<?php
/**
 * This file is part of the Faviconr package.
 *
 * (c) Tony Bogdanov <support@tonybogdanov.com>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

namespace Faviconr;

use Faviconr\Faviconr\InvalidColorException;
use Faviconr\Faviconr\InvalidImageException;
use Faviconr\Faviconr\GDNotAvailableException;
use Faviconr\Faviconr\ImageMagickNotAvailableException;
use Faviconr\Faviconr\InvalidPathException;
use Faviconr\Faviconr\InvalidURLException;
use Faviconr\Faviconr\RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use ColorThief\ColorThief;

class Faviconr
{
    const FORMAT_PNG        = 1;
    const FORMAT_ICO        = 2;

    /**
     * @var resource
     */
    protected $gd           = null;

    /**
     * @var OutputInterface
     */
    protected $output       = null;

    protected function log($message, $verbosity = 1)
    {
        if(!$this->output instanceof OutputInterface) {
            return $this;
        }

        if(1 == $verbosity && $this->output->isVerbose()) {
            $this->output->writeln($message);
        }

        if(2 == $verbosity && $this->output->isVeryVerbose()) {
            $this->output->writeln('    ' . $message);
        }

        return $this;
    }

    protected function loadImage($image)
    {
        $this->log('Load GD compatible image', 2);

        if(!function_exists('imagecreatefromstring')) {
            $this->log('Fail', 2);
            throw new GDNotAvailableException();
        }

        $content            = @file_get_contents($image);
        if(!is_string($content)) {
            $this->log('Fail', 2);
            throw new InvalidImageException(sprintf('Could not load <info>%s</info>, could not read resource.', $image));
        }

        $this->gd           = @imagecreatefromstring($content);
        if(!is_resource($this->gd)) {
            $this->log('Fail', 2);
            throw new InvalidImageException(sprintf('Could not load <info>%s</info>, resource is not a valid' .
                ' GD compatible image.', $image));
        }

        $width              = imagesx($this->gd);
        $height             = imagesy($this->gd);
        if(310 > $width || 310 > $height) {
            $this->log('Fail', 2);
            throw new InvalidImageException(sprintf('Source image must be at least <info>%s</info>,' .
                ' actual size is: <info>%s</info>', '310x310', $width . 'x' . $height));
        }

        $this->log('Success', 2);
        return $this;
    }

    protected function loadSVG($image)
    {
        $this->log('Load SVG image', 2);

        if(!class_exists('Imagick')) {
            $this->log('Fail', 2);
            throw new ImageMagickNotAvailableException();
        }

        $content            = @file_get_contents($image);
        if(!is_string($content)) {
            $this->log('Fail', 2);
            throw new InvalidImageException(sprintf('Could not load <info>%s</info>, could not read resource.', $image));
        }

        $image              = new \Imagick();
        $image->readImageBlob($content);


        $width              = $image->getImageWidth();
        $height             = $image->getImageHeight();
        if(310 > $width || 310 > $height) {
            $this->log('Fail', 2);
            throw new InvalidImageException(sprintf('Source image must be at least <info>%s</info>,' .
                ' actual size is: <info>%s</info>', '310x310', $width . 'x' . $height));
        }

        $image->setImageFormat('png24');

        $this->gd           = @imagecreatefromstring((string) $image);
        $image->destroy();
        if(!is_resource($this->gd)) {
            $this->log('Fail', 2);
            throw new InvalidImageException(sprintf('Could not load <info>%s</info>, ImageMagick could not convert' .
                ' the resource to a valid GD compatible image.', $image));
        }

        $this->log('Success', 2);
        return $this;
    }

    protected function generateImage($path, $tarWidth, $tarHeight = null, $format = self::FORMAT_PNG)
    {
        if(!isset($tarHeight)) {
            $tarHeight      = $tarWidth;
        }

        $width              = imagesx($this->gd);
        $height             = imagesy($this->gd);

        $this->log('Generate ' . basename($path));
        $this->log('Source image is ' . $width.  'x' . $height, 2);

        $cropWidth          = $width;
        $cropHeight         = round($cropWidth * $tarHeight / $tarWidth);

        if($cropHeight > $height) {
            $cropHeight     = $height;
            $cropWidth      = round($cropHeight * $tarWidth / $tarHeight);
        }

        $this->log('Crop source image to ' . $cropWidth . 'x' . $cropHeight, 2);
        $crop               = @imagecreatetruecolor($cropWidth, $cropHeight);
        @imagealphablending($crop, false);
        @imagefill($crop, 0, 0, imagecolorallocatealpha($crop, 255, 255, 255, 127));
        @imagealphablending($crop, true);
        @imagesavealpha($crop, true);
        @imagecopyresampled($crop, $this->gd, 0, 0, round(($width - $cropWidth) / 2),
            round(($height - $cropHeight) / 2), $cropWidth, $cropHeight, $cropWidth, $cropHeight);

        $this->log('Create empty image ' . $tarWidth . 'x' . $tarHeight, 2);
        $image              = @imagecreatetruecolor($tarWidth, $tarHeight);
        @imagealphablending($image, false);
        @imagefill($image, 0, 0, imagecolorallocatealpha($image, 255, 255, 255, 127));
        @imagealphablending($image, true);
        @imagesavealpha($image, true);

        $this->log('Resize cropped image to ' . $tarWidth . 'x' . $tarHeight, 2);
        @imagecopyresampled($image, $crop, 0, 0, 0, 0, $tarWidth, $tarHeight, $cropWidth, $cropHeight);
        @imagedestroy($crop);

        switch($format) {
            case self::FORMAT_PNG:
                $this->log('Export to PNG', 2);
                if(!@imagepng($image, $path)) {
                    @imagedestroy($image);
                    throw new RuntimeException(sprintf('Could not write <info>%s</info> image to <info>%s</info>' .
                        ' in <info>%s</info>.', 'PNG', $path, __METHOD__));
                }
                break;

            case self::FORMAT_ICO:
                $this->log('Export to ICO', 2);

                $pixels     = array();
                $opacities  = array();
                $current    = 0;

                for($y = $tarHeight - 1; $y >= 0; $y--) {
                    for($x = 0; $x < $tarWidth; $x++) {
                        $color      = @imagecolorat($image, $x, $y);

                        $alpha      = ($color & 0x7F000000) >> 24;
                        $alpha      = (1 - ($alpha / 127)) * 255;

                        $color      &= 0xFFFFFF;
                        $color      |= 0xFF000000 & ($alpha << 24);

                        $pixels[]   = $color;

                        $opacity    = ($alpha <= 127) ? 1 : 0;
                        $current    = ($current << 1) | $opacity;

                        if(0 == (($x + 1) % 32)) {
                            $opacities[]    = $current;
                            $current        = 0;
                        }
                    }
                    if(0 < ($x % 32)) {
                        while(0 < ($x++ % 32)) {
                            $current        = $current << 1;
                        }
                        $opacities[]        = $current;
                        $current            = 0;
                    }
                }

                $data       = pack('VVVvvVVVVVV', 40, $tarWidth, $tarHeight * 2, 1, 32, 0, 0, 0, 0, 0, 0);

                foreach($pixels as $color) {
                    $data   .= pack('V', $color);
                }
                foreach($opacities as $opacity) {
                    $data   .= pack('N', $opacity);
                }

                if(!@file_put_contents($path, pack('vvv', 0, 1, 1) . pack('CCCCvvVV', $tarWidth, $tarHeight, 0, 0, 1, 32,
                        40 + ($tarWidth * $tarHeight * 4) + ((int) ceil($tarWidth / 32) * $tarHeight * 4), 22) . $data)) {
                    @imagedestroy($image);
                    throw new RuntimeException(sprintf('Could not write <info>%s</info> image to <info>%s</info>' .
                        ' in <info>%s</info>.', 'ICO', $path, __METHOD__));
                }
                break;
        }

        @imagedestroy($image);
        $this->log('Success: ' . basename($path), 2);
        return $this;
    }

    protected function generateSafariPinnedTab($path)
    {
        $this->log('Generate ' . basename($path), 2);

        $cnt                = <<<SVG_CONTENT
<?xml version="1.0" standalone="no"?>
<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 20010904//EN" "http://www.w3.org/TR/2001/REC-SVG-20010904/DTD/svg10.dtd">
<svg version="1.0" xmlns="http://www.w3.org/2000/svg" width="880.000000pt" height="880.000000pt"
    viewBox="0 0 880.000000 880.000000" preserveAspectRatio="xMidYMid meet">
    <g transform="translate(0.000000,880.000000) scale(0.100000,-0.100000)" fill="#000000" stroke="none">
        <path d="M0 4400 l0 -3300 4400 0 4400 0 0 3300 0 3300 -4400 0 -4400 0 0 -3300z"/>
    </g>
</svg>
SVG_CONTENT;

        if(!@file_put_contents($path, $cnt)) {
            throw new RuntimeException(sprintf('Could not write to <info>%s</info> in <info>%s</info>.',
                $path, __METHOD__));
        }

        $this->log('Success: ' . basename($path), 2);
        return $this;
    }

    protected function generateBrowserConfig($path, $url, $color)
    {
        $this->log('Generate ' . basename($path), 2);

        $cnt                = <<<CFG_CONTENT
<?xml version="1.0" encoding="utf-8"?>
<browserconfig>
  <msapplication>
    <tile>
      <square70x70logo src="{$url}mstile-70x70.png"/>
      <square150x150logo src="{$url}mstile-150x150.png"/>
      <square310x310logo src="{$url}mstile-310x310.png"/>
      <wide310x150logo src="{$url}mstile-310x150.png"/>
      <TileColor>{$color}</TileColor>
    </tile>
  </msapplication>
</browserconfig>
CFG_CONTENT;

        if(!@file_put_contents($path, $cnt)) {
            throw new RuntimeException(sprintf('Could not write to <info>%s</info> in <info>%s</info>.',
                $path, __METHOD__));
        }

        $this->log('Success: ' . basename($path), 2);
        return $this;
    }

    protected function generateManifest($path, $url, $name)
    {
        $this->log('Generate ' . basename($path), 2);

        $url                = str_replace('/', '\\/', $url);
        $cnt                = <<<MAN_CONTENT
{
	"name": "{$name}",
	"icons": [
		{
			"src": "{$url}android-chrome-36x36.png",
			"sizes": "36x36",
			"type": "image\/png",
			"density": 0.75
		},
		{
			"src": "{$url}android-chrome-48x48.png",
			"sizes": "48x48",
			"type": "image\/png",
			"density": 1
		},
		{
			"src": "{$url}android-chrome-72x72.png",
			"sizes": "72x72",
			"type": "image\/png",
			"density": 1.5
		},
		{
			"src": "{$url}android-chrome-96x96.png",
			"sizes": "96x96",
			"type": "image\/png",
			"density": 2
		},
		{
			"src": "{$url}android-chrome-144x144.png",
			"sizes": "144x144",
			"type": "image\/png",
			"density": 3
		},
		{
			"src": "{$url}android-chrome-192x192.png",
			"sizes": "192x192",
			"type": "image\/png",
			"density": 4
		}
	]
}
MAN_CONTENT;

        if(!@file_put_contents($path, $cnt)) {
            throw new RuntimeException(sprintf('Could not write to <info>%s</info> in <info>%s</info>.',
                $path, __METHOD__));
        }

        $this->log('Success: ' . basename($path), 2);
        return $this;
    }

    public function __construct($image, OutputInterface $output = null)
    {
        $this->output       = $output;
        $errors             = array();

        try {
            $this->loadImage($image);
            return;
        } catch(\Exception $e) {
            $errors[]       = $e->getMessage();
        }

        try {
            $this->loadSVG($image);
            return;
        } catch(\Exception $e) {
            $errors[]       = $e->getMessage();
        }

        throw new InvalidImageException(sprintf('Could not load <info>%s</info>, resource is not supported:%s',
            $image, PHP_EOL . ' -- ' . implode(PHP_EOL . ' -- ', $errors)));
    }

    public function __destruct()
    {
        if(is_resource($this->gd)) {
            @imagedestroy($this->gd);
        }
    }

    public function determineDominantColor()
    {
        $this->log('Auto-determine dominant color', 2);

        $color              = ColorThief::getColor($this->gd);
        $color              = '#' . str_pad(dechex($color[0]), 2, '0', STR_PAD_LEFT) .
            str_pad(dechex($color[1]), 2, '0', STR_PAD_LEFT) . str_pad(dechex($color[2]), 2, '0', STR_PAD_LEFT);

        $this->log('Success: ' . $color, 2);
        return $color;
    }

    public function generateAssets($path, $url, $color, $title)
    {
        if(!is_string($path)) {
            throw new InvalidPathException('Target path must be a valid string.');
        } else if(empty($path)) {
            $path           = '.';
        }

        $absolute           = realpath($path);
        if(false === $absolute || !is_dir($absolute) || !is_writable($absolute)) {
            throw new InvalidPathException(sprintf('Target path <info>%s</info> must be a valid writable directory.',
                $path));
        }
        $absolute           .= DIRECTORY_SEPARATOR;

        if(!is_string($url)) {
            throw new InvalidURLException('Target URL must be a valid string.');
        } else if(empty($url)) {
            $url            = '.';
        }
        $url                = rtrim($url, '/') . '/';

        if(!is_string($color)) {
            throw new InvalidColorException('Target color must be a valid string.');
        } else if(!preg_match('/^#?[0-9a-f]{6}$/', $color)) {
            throw new InvalidColorException('Target color must be a valid HEX color (e.g. #123def).');
        }
        $color              = '#' . ltrim($color, '#');

        $this->log('Generate assets in ' . $absolute);

        foreach(array(57, 60, 72, 76, 114, 120, 144, 152, 180) as $tarWidth) {
            $this->generateImage($absolute . 'apple-touch-icon-' . $tarWidth . 'x' . $tarWidth . '.png', $tarWidth);
        }

        $this->log(sprintf('Copy <info>%s</info> to <info>%s</info>.', 'apple-touch-icon-180x180.png',
            'apple-touch-icon.png'));
        copy($absolute . 'apple-touch-icon-180x180.png', $absolute . 'apple-touch-icon.png');

        $this->log(sprintf('Copy <info>%s</info> to <info>%s</info>.', 'apple-touch-icon-180x180.png',
            'apple-touch-icon-precomposed.png'));
        copy($absolute . 'apple-touch-icon-180x180.png', $absolute . 'apple-touch-icon-precomposed.png');

        foreach(array(36, 48, 72, 96, 144, 192) as $tarWidth) {
            $this->generateImage($absolute . 'android-chrome-' . $tarWidth . 'x' . $tarWidth . '.png', $tarWidth);
        }

        foreach(array(16, 32, 96) as $tarWidth) {
            $this->generateImage($absolute . 'favicon-' . $tarWidth . 'x' . $tarWidth . '.png', $tarWidth);
        }

        $this->generateImage($absolute . 'mstile-70x70.png', 70);
        $this->generateImage($absolute . 'mstile-144x144.png', 144);
        $this->generateImage($absolute . 'mstile-150x150.png', 150);
        $this->generateImage($absolute . 'mstile-310x310.png', 310);
        $this->generateImage($absolute . 'mstile-310x150.png', 310, 150);

        $this->generateImage($absolute . 'favicon.ico', 16, 16, self::FORMAT_ICO);

        $this->generateSafariPinnedTab($absolute . 'safari-pinned-tab.svg');
        $this->generateBrowserConfig($absolute . 'browserconfig.xml', $url, $color);
        $this->generateManifest($absolute . 'manifest.json', $url, $title);

        return $this;
    }

    public function injectDefinition($path, $url, $color, $title, $recursive = false,
                                     $extensions = array('php', 'phtml', 'html', 'htm'))
    {
        if(!is_string($path)) {
            throw new InvalidPathException('Target path must be a valid string.');
        } else if(empty($path)) {
            $path           = '.';
        }

        $absolute           = realpath($path);
        if(false === $absolute || (!is_dir($absolute) && !is_file($absolute)) || !is_readable($absolute)) {
            throw new InvalidPathException(sprintf('Target path <info>%s</info> must be a valid readable file or' .
                ' directory.', $path));
        }

        if(is_dir($absolute)) {
            $scan           = function($path) use(&$scan, $recursive, $extensions) {
                $open       = @opendir($path);
                if(false === $open) {
                    throw new RuntimeException(sprintf('Could not open directory <info>%s</info> for reading.', $path));
                }

                $list       = array();

                while(false !== ($read = @readdir($open))) {
                    if('.' == $read || '..' == $read) {
                        continue;
                    }
                    if(is_dir($path . $read) && $recursive) {
                        $list   = array_merge($list, $scan($path . $read . DIRECTORY_SEPARATOR));
                    } else if(is_file($path . $read) && in_array(pathinfo($read, PATHINFO_EXTENSION), $extensions)) {
                        $list[] = $path . $read;
                    }
                }

                @closedir($open);

                return $list;
            };

            foreach($scan($absolute . DIRECTORY_SEPARATOR) as $subPath) {
                $this->injectDefinition($subPath, $url, $color, $title);
            }

            return $this;
        }

        if(!is_string($url)) {
            throw new InvalidURLException('Target URL must be a valid string.');
        } else if(empty($url)) {
            $url            = '.';
        }
        $url                = rtrim($url, '/') . '/';

        if(!is_string($color)) {
            throw new InvalidColorException('Target color must be a valid string.');
        } else if(!preg_match('/^#?[0-9a-f]{6}$/', $color)) {
            throw new InvalidColorException('Target color must be a valid HEX color (e.g. #123def).');
        }
        $color              = '#' . ltrim($color, '#');

        $content            = @file_get_contents($absolute);
        if(false === $content) {
            throw new InvalidPathException(sprintf('Could not read <info>%s</info>.', $absolute));
        }
		
		// Invalid HTML, sorry Safari
		// <link rel="mask-icon" href="{$url}safari-pinned-tab.svg" color="${color}">

        $definition         = <<<DEFINITION
<link rel="apple-touch-icon" sizes="57x57" href="{$url}apple-touch-icon-57x57.png">
<link rel="apple-touch-icon" sizes="60x60" href="{$url}apple-touch-icon-60x60.png">
<link rel="apple-touch-icon" sizes="72x72" href="{$url}apple-touch-icon-72x72.png">
<link rel="apple-touch-icon" sizes="76x76" href="{$url}apple-touch-icon-76x76.png">
<link rel="apple-touch-icon" sizes="114x114" href="{$url}apple-touch-icon-114x114.png">
<link rel="apple-touch-icon" sizes="120x120" href="{$url}apple-touch-icon-120x120.png">
<link rel="apple-touch-icon" sizes="144x144" href="{$url}apple-touch-icon-144x144.png">
<link rel="apple-touch-icon" sizes="152x152" href="{$url}apple-touch-icon-152x152.png">
<link rel="apple-touch-icon" sizes="180x180" href="{$url}apple-touch-icon-180x180.png">
<link rel="icon" type="image/png" href="{$url}favicon-32x32.png" sizes="32x32">
<link rel="icon" type="image/png" href="{$url}android-chrome-192x192.png" sizes="192x192">
<link rel="icon" type="image/png" href="{$url}favicon-96x96.png" sizes="96x96">
<link rel="icon" type="image/png" href="{$url}favicon-16x16.png" sizes="16x16">
<link rel="manifest" href="{$url}manifest.json">
<link rel="mask-icon" href="{$url}safari-pinned-tab.svg">
<link rel="shortcut icon" href="{$url}favicon.ico">
<meta name="msapplication-TileColor" content="${color}">
<meta name="msapplication-TileImage" content="{$url}mstile-144x144.png">
<meta name="msapplication-config" content="{$url}browserconfig.xml">
<meta name="theme-color" content="${color}">
DEFINITION;

        $this->log('Inject definition in ' . $absolute);

        $placeholderSingle  = '<!-- favicons -->';
        $placeholderStart   = '<!-- favicons start -->';
        $placeholderEnd     = '<!-- favicons end -->';

        $content            = str_replace($placeholderSingle, $placeholderStart . $placeholderEnd, $content);

        $offset             = 0;
        $injects            = 0;
        while(false !== ($start = strpos(substr($content, $offset), $placeholderStart))) {
            $indent         = '';
            $indentStart    = $start + $offset;

            if(0 < $indentStart) {
                while(0 < $indentStart) {
                    $indentStart--;

                    $char   = substr($content, $indentStart, 1);
                    if("\r" == $char || "\n" == $char) {
                        $indentStart++;
                        break;
                    }

                    $indent = $char . $indent;
                }
            }

            $end            = strpos(substr($content, $indentStart), $placeholderEnd);
            if(false === $end) {
                $this->log('Failed.', 2);
                throw new RuntimeException(sprintf('Found <info>%s</info> with no corresponding <info>%s</info>.',
                    $placeholderStart, $placeholderEnd));
            }

            $content        = substr_replace($content, $indent . $placeholderStart . PHP_EOL . $indent .
                implode(PHP_EOL . $indent, explode(PHP_EOL, $definition)) . PHP_EOL . $indent . $placeholderEnd,
                $indentStart, $end + strlen($placeholderEnd));

            $offset         = $indentStart + $end;
            $injects++;
        }

        if(0 == $injects) {
            $this->log('Nothing injected.', 2);
            return $this;
        } else if(1 == $injects) {
            $this->log('Success.', 2);
        } else if(1 < $injects) {
            $this->log(sprintf('Successfully injected <info>%d</info> times.', $injects), 2);
        }

        $this->log(sprintf('Update <info>%s</info>', $absolute));
        if(!@file_put_contents($path, $content)) {
            $this->log('Failed.', 2);
            throw new RuntimeException('Could not write to ' . $absolute);
        }

        $this->log('Success.', 2);
        return $this;
    }
}