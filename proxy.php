<?php
$basePath = realpath(dirname(__FILE__) . '/..');
require_once "$basePath/vendor/autoload.php";

ini_set('display_errors', true);
error_reporting(E_ALL);

$configFile = "$basePath/include/config.php";
$configDistFile = "$basePath/include/config.dist.php";
require $configDistFile;
if (file_exists($configFile)) {
    require $configFile;
} else {
    $configDistFile = "$basePath/include/config.dist.php";
    $msg = "$configFile does not exist. Please create one with the help of " .
        "$configDistFile.";
    echo $msg;
    return false;
}

$proxy = new Amcsi_HttpProxy_Proxy;

$proxy->setConf($conf);

$proxy->dispatch();

