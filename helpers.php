<?php

$GLOBALS['debug'] = false;

if (!function_exists('dump')) {
    function dump($var)
    {
        if ($GLOBALS['debug'] === false) {
            return;
        }
        $logFile = __DIR__ . '/dump.log';
        file_put_contents($logFile, print_r($var, true) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
