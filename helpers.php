<?php

if (!function_exists('dump')) {
    function dump($var)
    {
        $logFile = __DIR__ . '/dump.log';
        file_put_contents($logFile, print_r($var, true) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
