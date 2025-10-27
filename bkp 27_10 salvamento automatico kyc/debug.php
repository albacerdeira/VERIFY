<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);
ini_set('error_log', __DIR__ . '/error.log');

function debug_to_console($data) {
    if(is_array($data) || is_object($data)) {
        error_log(print_r($data, true));
    } else {
        error_log($data);
    }
}
