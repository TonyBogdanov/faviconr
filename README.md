# Faviconr

[![Buy Me a Coffee](http://static.tonybogdanov.com/github/coffee.svg)](http://ko-fi.co/1236KUKJNC96B)

Console tool for generating and integrating favicon images.

Currently supports:

- Generating all types of images from a single source image
- Supports JPEG, GIF and PNG source image via [PHP GD](http://php.net/manual/en/book.image.php)
- Supports SVG source image via [PHP ImageMagick](http://php.net/manual/en/book.imagick.php)
- Can automatically determine a dominant color
- Can perform a favicon definition injection in multiple files (simply add a `<!-- favicons -->` placeholder)
- Automated generation via options saved in a `faviconr.json` file
- Wizard for interactive generation of a `faviconr.json` file

## Requirements

- PHP 5.5 or later with the `php` command globally available in the console.

## Installing

### Using [NPM](http://npmjs.org)

```shell
npm install --save TonyBogdanov/faviconr
```

### Using [Bower](http://bower.io/)

```shell
bower install --save TonyBogdanov/faviconr
```

### Manually

To install the tool manually simply download `build/faviconr.phar`.

## Usage

Open your favourite console and run the following:

```shell
php faviconr.phar init
```

This will start an interactive wizard which will help you create a `faviconr.json` file with information on
what operations to perform.

Once you have the file you'll be able to call:

```shell
php faviconr.phar generate path/to/faviconr.json
```

or simply

```shell
php faviconr.phar generate
```

from the same directory.

Alternatively you can call the `generate` command without a `faviconr.json` file by supplying all required options.
For help about the command run:

```shell
php faviconr.phar help generate
```

Keep in mind that any relative paths you set in the `faviconr.json` file will be relative to the directory from which
you run the `generate` command, NOT to the directory where the `faviconr.json` file is located.

As a recommendation you should always run the commands in the directory of `faviconr.json` or use absolute paths.

## Running as a NodeJS script

Say you've installed Faviconr to your project as a NPM dependency. Now you can simply add:

```json
"favicons": "cd build/faviconr & faviconr generate -vv"
```

to your `package.json` file to automate the favicon generation even further.

Now you will have a NPM script called `favicons`, which you can run via:

```shell
npm run favicons
```

When run, the console will `cd` to the `build/faviconr` directory and execute the `faviconr generate -vv` command.
In this example we assume you have a `faviconr.json` file in `build/faviconr`.

As far as `faviconr` is concerned, `build/faviconr` will also be the current working directory.

The path we `cd` into must be relative to where your `package.json` file is located. NodeJS makes it easier by always
running from the directory of your `package.json`, which means you can run `npm run favicons` even from a sub-directory.