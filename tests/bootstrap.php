<?php
if (!defined('E_DEPRECATED')) {
    define('E_DEPRECATED', 8192);
}
if (!defined('E_USER_DEPRECATED')) {
    define('E_USER_DEPRECATED', 16384);
}
$basePath = realpath(dirname(__FILE__) . '/..');
require_once "$basePath/vendor/autoload.php";
