<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/inc/config.php';
require __DIR__ . '/../function.php';
$textbotlang = languagechange();

$text_dict = [
    'requestAgent' => $textbotlang['textbot']['requestAgent'] ?? 'fallback_agent',
    'agentPanel' => $textbotlang['textbot']['agentPanel'] ?? 'fallback_agentpanel',
    'panelAdmin' => $textbotlang['Admin']['panelAdmin'] ?? 'fallback_admin'
];

echo json_encode($text_dict, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
