<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/inc/config.php';

$jsKeys = array_filter(
    $textbotlang['panel'] ?? [],
    fn($k) => strncmp($k, 'js', 2) === 0,
    ARRAY_FILTER_USE_KEY
);

echo json_encode($jsKeys, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
