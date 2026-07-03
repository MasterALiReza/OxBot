<?php
// This variable added for high load panels which their response time is long and bot can't communicate with online panel!
// null for default settings
$request_exec_timeout = null;
$dbhost = '{database_url}';
$dbname = '{database_name}';
$usernamedb = '{username_db}';
$passworddb = '{password_db}';
if (function_exists('mysqli_connect')) {
    $connect = mysqli_connect($dbhost, $usernamedb, $passworddb, $dbname);
    if ($connect->connect_error) { die("error" . $connect->connect_error); }
    mysqli_set_charset($connect, "utf8mb4");
} else {
    $connect = null;
}
$options = [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false, ];
$dsn = "mysql:host=$dbhost;dbname=$dbname;charset=utf8mb4";
try { $pdo = new PDO($dsn, $usernamedb, $passworddb, $options); } catch (\PDOException $e) { error_log("Database connection failed: " . $e->getMessage()); }
$APIKEY = '{API_KEY}';
$adminnumber = '{admin_number}';
$domainhosts = '{domain_name}';
$usernamebot = '{username_bot}';
$backup_secure_token = 'SecureBackupToken_3e5c9b67484df7c05b8a02a9b31d04ec'; // Token generated for secure backups

?>