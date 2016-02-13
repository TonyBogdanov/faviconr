<?php
/**
 * This file is part of the Faviconr package.
 *
 * (c) Tony Bogdanov <support@tonybogdanov.com>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

// Get the autoloader
if(is_file(dirname(__FILE__) . '/../bower_components/Aura.Autoload/src/Loader.php')) {
    require_once(dirname(__FILE__) . '/../bower_components/Aura.Autoload/src/Loader.php');
    require_once(dirname(__FILE__) . '/../bower_components/Aura.Autoload/autoload.php');
} else if(is_file(dirname(__FILE__) . '/../../Aura.Autoload/src/Loader.php')) {
    require_once(dirname(__FILE__) . '/../../Aura.Autoload/src/Loader.php');
    require_once(dirname(__FILE__) . '/../../Aura.Autoload/autoload.php');
} else {
    throw new \Exception('Could not locate auraphp/Aura.Autoload');
}

$loader = new \Aura\Autoload\Loader();
$loader->addPrefix('Symfony\Component\Console', dirname(__FILE__) . '/../bower_components/console');
$loader->addPrefix('Symfony\Component\Console', dirname(__FILE__) . '/../../console');
$loader->addPrefix('ColorThief', dirname(__FILE__) . '/../bower_components/color-thief-php/lib/ColorThief');
$loader->addPrefix('ColorThief', dirname(__FILE__) . '/../../color-thief-php/lib/ColorThief');
$loader->addPrefix('Faviconr', dirname(__FILE__) . '/classes');
$loader->register();