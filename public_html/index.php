<?php

use App\Kernel;

// Locally the project root is the parent of public_html.
// On SuperHosting the web root is /home/zaniskyu/public_html and the code is in /home/zaniskyu/zanimalnik.
$projectDir = is_file(dirname(__DIR__).'/vendor/autoload_runtime.php')
    ? dirname(__DIR__)
    : dirname(__DIR__).'/zanimalnik';

require_once $projectDir.'/vendor/autoload_runtime.php';

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
