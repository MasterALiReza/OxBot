<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
try {
    ob_start();
    require 'panel/users.php';
    ob_end_clean();
    echo 'OK';
} catch (Throwable ) {
    ob_end_clean();
    echo 'Error: ' . ->getMessage() . ' on line ' . ->getLine() . ' in ' . ->getFile();
}
