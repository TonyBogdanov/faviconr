<?php
/**
 * This file is part of the Faviconr package.
 *
 * (c) Tony Bogdanov <support@tonybogdanov.com>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

if('cli' != php_sapi_name()) {
    return;
}

require_once(dirname(__FILE__) . '/autoload.php');

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Faviconr\Faviconr;

$console    = new Application();

$console
    ->register('init')
    ->setDescription('Starts an interactive wizard for generating a faviconr.json file.')
    ->setCode(function(InputInterface $input, OutputInterface $output) {
        $question       = $this->getHelper('question');

        $output->writeln('You are about to generate a faviconr.json file');
        $output->writeln('for automated favicon generation.');
        $output->write(PHP_EOL);

        $output->writeln('Once you have the file you\'ll be able to call');
        $output->writeln('"php faviconr.phar generate faviconr.json".');
        $output->writeln('or just "php faviconr.phar generate" in the same directory.');
        $output->write(PHP_EOL);

        $path           = getcwd();
        while(true) {
            $cPath      = realpath($question->ask($input, $output, new Question('Where would you like to save' .
                ' the generated' . PHP_EOL . 'faviconr.json file: (' . $path . ') ', $path)));
            if(false === $cPath) {
                $output->writeln('Invalid path.');
                $output->write(PHP_EOL);
                continue;
            }

            $path       = $cPath;
            $output->write(PHP_EOL);
            break;
        }

        $filename       = 'faviconr.json';
        while(true) {
            $cFilename  = $question->ask($input, $output, new Question('How would you like to name the file: (' .
                $filename . ') ', $filename));
            if(!preg_match('/^[0-9a-z_\.-]+$/i', $cFilename)) {
                $output->writeln('Invalid filename, please use 0-9a-zA-Z-_.');
                $output->write(PHP_EOL);
                continue;
            }

            $filename   = $cFilename;
            $output->write(PHP_EOL);
            break;
        }

        $json           = array();

        while(0 == strlen($json['image'] = $question->ask($input, $output,
                new Question('Choose a path to your source image,' . PHP_EOL .
                    'can be GIF, PNG, JPEG or SVG (if you have ImageMagick),' . PHP_EOL .
                    'must be at least 310x310: ')))) {
            $output->writeln('Cannot be empty.');
            $output->write(PHP_EOL);
        }
        $output->write(PHP_EOL);

        while(0 == strlen($json['title'] = $question->ask($input, $output,
                new Question('Choose the title (name) of your site / app: ')))) {
            $output->writeln('Cannot be empty.');
            $output->write(PHP_EOL);
        }
        $output->write(PHP_EOL);

        while(0 == strlen($json['url'] = $question->ask($input, $output,
                new Question('Choose the base path or URL from where the favicons' . PHP_EOL . 'will be served: ')))) {
            $output->writeln('Cannot be empty.');
            $output->write(PHP_EOL);
        }
        $output->write(PHP_EOL);

        if($question->ask($input, $output, new ConfirmationQuestion('Would you like to manually specify a dominant color' .
            PHP_EOL . '(HEX), or let the script determine it' . PHP_EOL .
            'automatically from the source image? [y/N] ', false))) {
            while(true) {
                $color  = $question->ask($input, $output, new Question('Color: '));
                if(!preg_match('/^#?[0-9a-f]{6}$/i', $color)) {
                    $output->writeln('Invalid HEX color, please use #123def.');
                    $output->write(PHP_EOL);
                    continue;
                }

                $json['color'] = $color;
                break;
            }
        }
        $output->write(PHP_EOL);

        if($question->ask($input, $output, new ConfirmationQuestion('Would you like the script to generate all required' .
            PHP_EOL . 'favicon assets when run? [Y/n] ', true))) {
            $output->write(PHP_EOL);

            while(0 == strlen($json['dest'] = $question->ask($input, $output,
                    new Question('Choose a path to a valid directory where to save' . PHP_EOL .
                        'the generated assets: ')))) {
                $output->writeln('Cannot be empty.');
                $output->write(PHP_EOL);
            }
        }
        $output->write(PHP_EOL);

        if($question->ask($input, $output, new ConfirmationQuestion('Would you like the script to perform favicon' .
            PHP_EOL . 'definition injections when run? [Y/n] ', true))) {
            $output->write(PHP_EOL);

            $output->writeln('Add the following comment: <!-- favicons --> in all files' . PHP_EOL .
                'where you want to inject the definition.');
            $output->write(PHP_EOL);

            while(0 == strlen($json['inject'] = $question->ask($input, $output,
                    new Question('Choose a path to a valid file or directory' . PHP_EOL .
                        'where to inject the generated favicon definition: ')))) {
                $output->writeln('Cannot be empty.');
                $output->write(PHP_EOL);
            }
            $output->write(PHP_EOL);

            if($question->ask($input, $output, new ConfirmationQuestion('Is the specified path a directory? (y/N) ',
                false))) {
                $output->write(PHP_EOL);

                $json['inject-recursive'] = $question->ask($input, $output,
                    new ConfirmationQuestion('Would you like to also scan all sub-directories? [y/N] ', false));
                $output->write(PHP_EOL);

                $json['inject-ext'] = array();
                while(true) {
                    $ext = $question->ask($input, $output,
                        new Question('Choose which file types should be scanned.' . PHP_EOL .
                        'Recommended are php, phtml, html, htm' . PHP_EOL . 'Hit enter when you\'re done adding (' .
                            implode(', ', $json['inject-ext']) . '): '));
                    if(empty($ext)) {
                        break;
                    }
                    $json['inject-ext'][] = $ext;
                    $json['inject-ext'] = array_unique($json['inject-ext']);
                    $output->write(PHP_EOL);
                }
            }
        }
        $output->write(PHP_EOL);

        $result         = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if($question->ask($input, $output, new ConfirmationQuestion('Looks good? [Y/n]' . PHP_EOL . PHP_EOL .
            $result . PHP_EOL, true))) {
            if(@file_put_contents($path . DIRECTORY_SEPARATOR . $filename, $result)) {
                $output->writeln('Done.');
            } else {
                $output->writeln(sprintf('Could not write to <info>%s</info>.'), $path . DIRECTORY_SEPARATOR . $filename);
            }
        }
    });

$console
    ->register('generate')
    ->setDefinition(array(
        new InputArgument('image', InputArgument::OPTIONAL,
            'Path to the source favicon image, or a faviconr.json file. If left empty a faviconr.json file in' .
            ' the current working directory will be assumed.'),

        new InputOption('dest', 'd', InputOption::VALUE_REQUIRED,
            'Path to a valid directory where to save the generated favicon assets.'),

        new InputOption('url', 'u', InputOption::VALUE_REQUIRED,
            'The base path or URL where the assets will be hosted. Must end with a trailing slash.'),

        new InputOption('title', 't', InputOption::VALUE_REQUIRED,
            'The title of your website / app.'),

        new InputOption('color', 'c', InputOption::VALUE_REQUIRED,
            'Dominant color to be displayed on supported devices. If not specified, the color will be automatically' .
            ' extracted from the image.'),

        new InputOption('inject', 'i', InputOption::VALUE_REQUIRED,
            'Path to a file or a folder of files in which to inject the generated favicon definition.'),

        new InputOption('inject-recursive', null, InputOption::VALUE_NONE,
            'If this option is present and "--inject" points to a directory, all files in it\'s sub-directories' .
            ' will also be traversed.'),

        new InputOption('inject-ext', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'File extensions to scan for.', array('php', 'phtml', 'html', 'htm'))
    ))
    ->setDescription('Performs a favicon image generation / integration based on the specified options. The command' .
        ' won\'t perform any operation unless at least one of "--dest" or "--inject" are present.')
    ->setCode(function(InputInterface $input, OutputInterface $output) {
        $image          = $input->getArgument('image');
        $options        = $input->getOptions();

        if(empty($image)) {
            $image      = getcwd() . DIRECTORY_SEPARATOR . 'faviconr.json';
            if(!is_file($image)) {
                $output->writeln('Please specify a valid path to a source image or a faviconr.json file.');
                exit(1);
            }
        }

        // faviconr.json?
        $faviconr       = @json_decode(file_get_contents($image), true);
        if(is_array($faviconr) && isset($faviconr['image'])) {
            $options    = array_replace($options, $faviconr);
            $image      = $options['image'];
        }

        $favi           = new Faviconr($image, $output);

        if(isset($options['dest']) || isset($options['inject'])) {
            if(!isset($options['url'])) {
                $output->writeln('When a "--dest" or a "--inject" option is present, "--url" is also required.');
                exit(1);
            }

            if(!isset($options['title'])) {
                $output->writeln('When a "--dest" or a "--inject" option is present, "--title" is also required.');
                exit(1);
            }

            if(!isset($options['color'])) {
                $options['color']       = $favi->determineDominantColor();
            }
        }

        if(isset($options['dest'])) {
            $favi->generateAssets($options['dest'], $options['url'], $options['color'], $options['title']);
        }

        if(isset($options['inject'])) {
            $favi->injectDefinition($options['inject'], $options['url'], $options['color'], $options['title'],
                $options['inject-recursive'], $options['inject-ext']);
        }
    });

$console->run();