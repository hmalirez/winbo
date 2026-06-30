<?php
$botToken = '8693487702:AAEQJpOcSy8kszy1uM0OXU-hYVsfzN-bDQI';
$adminId = 5229414557;

$donatedConfigPath = __DIR__ . '/files/configs/donated.txt';
$adminConfigPath = __DIR__ . '/files/configs/admin.txt';
$statesDir = __DIR__ . '/files/states/';

function ensureStateDir() {
    if (!is_dir($GLOBALS['statesDir'])) {
        mkdir($GLOBALS['statesDir'], 0755, true);
    }
}

function bot($method, $data = []) {
    global $botToken;
    $url = "https://api.telegram.org/bot$botToken/$method";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);
    return $result ?: ['ok' => false, 'error' => 'Invalid response'];
}

function editMessageText($chatId, $messageId, $text, $replyMarkup) {
    return bot('editMessageText', [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
        'reply_markup' => $replyMarkup,
        'parse_mode' => 'HTML'
    ]);
}

function getConfigsFromFile($filePath) {
    if (!file_exists($filePath)) return [];
    $content = file_get_contents($filePath);
    $lines = array_filter(explode("\n", $content));
    $configs = [];
    foreach ($lines as $line) {
        if (trim($line)) {
            $configs[] = trim($line);
        }
    }
    return $configs;
}

function saveConfigToFile($filePath, $config) {
    $configs = getConfigsFromFile($filePath);
    $configs[] = $config;
    file_put_contents($filePath, implode("\n", $configs) . "\n");
}

function clearConfigsFile($filePath) {
    file_put_contents($filePath, '');
}
?>