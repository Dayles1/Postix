<?php

/*
 * Register the Composer autoloader.
 *
 * Composer eagerly loads danog/madelineproto/src/polyfill.php on every request, and
 * that file echoes a performance notice when PHP_OS_FAMILY is Windows. The notice is
 * written before Laravel produces anything, so it ends up in front of every response
 * body and makes JSON endpoints unparsable ("Unexpected token 'W'").
 *
 * Buffer the autoloader, drop that one notice, and pass any other output through so a
 * genuine warning from some other bootstrap file is still visible.
 */

ob_start();

$loader = require __DIR__.'/../vendor/autoload.php';

$output = (string) ob_get_clean();

if ($output !== '') {
    $output = (string) preg_replace(
        '/^WARNING: MadelineProto runs around 10x slower on windows.*\R?/m',
        '',
        $output,
        1
    );

    if ($output !== '') {
        echo $output;
    }
}

return $loader;
