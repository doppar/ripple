<?php

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (! is_file($autoload)) {
    fwrite(STDERR, "Missing vendor/autoload.php. Run `composer install` in the ripple package root.\n");
    exit(1);
}

require $autoload;
