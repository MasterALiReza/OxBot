<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start();
require '../inc/config.php';

$old_cwd = getcwd();
chdir(__DIR__ . '/../../');
require_once 'botapi.php';
require_once 'MHSanaei-3.2.php';
chdir($old_cwd);
ob_end_clean();

echo "Includes loaded successfully!\n";
