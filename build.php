<?php
/**
 * This file is part of the Audoc package.
 *
 * (c) Tony Bogdanov <support@tonybogdanov.com>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

function scan($path, $root = null) {
    if(!isset($root)) {
        $root       = $path;
    }

    $path           = realpath($path) . DIRECTORY_SEPARATOR;
    $root           = realpath($root) . DIRECTORY_SEPARATOR;
    $result         = array();

    $open           = opendir($path);
    while(false !== ($read = readdir($open))) {
        if('.' == $read || '..' == $read) {
            continue;
        }
        if(is_file($path . $read)) {
            $result[$path . $read] = substr($path . $read, strlen($root));
        } else if(is_dir($path . $read)) {
            $result = array_merge($result, scan($path . $read, $root));
        }
    }
    closedir($open);

    return $result;
}

@unlink('build/faviconr.phar');

$paths              = array(
    'bower_components/Aura.Autoload/src',
    'bower_components/console',
    'bower_components/color-thief-php/lib',
    'src',
);
$phar               = new Phar('build/faviconr.phar');

foreach($paths as $path) {
    foreach(scan($path) as $absolute => $relative) {
        $phar->addFromString($path . '/' . $relative, php_strip_whitespace($absolute));
    }
}

$phar->delete('bower_components/console/.bower.json');
$phar->delete('bower_components/console/.gitignore');
$phar->delete('bower_components/console/CHANGELOG.md');
$phar->delete('bower_components/console/composer.json');
$phar->delete('bower_components/console/LICENSE');
$phar->delete('bower_components/console/README.md');
$phar->delete('bower_components/console/phpunit.xml.dist');

$phar->addFromString('bower_components/Aura.Autoload/autoload.php', php_strip_whitespace('bower_components/Aura.Autoload/autoload.php'));
$phar->addFromString('index.php', '<' . '?php' . PHP_EOL . 'require_once(dirname(__FILE__) . \'/src/cli.php\');');
$phar->compressFiles(Phar::GZ);

echo 'Done.';