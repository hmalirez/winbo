<?php
$botToken = '8933092110:AAE1-gF6Jw0aLfS0SR-aaOxZ6V-kKHECnvE';
$adminId = 5229414557;

$donatedConfigPath = __DIR__ . '/files/configs/donated.txt';
$adminConfigPath = __DIR__ . '/files/configs/admin.txt';
$statesDir = __DIR__ . '/files/states/';
$settingsPath = __DIR__ . '/files/settings.json';

function ensureStateDir() {
    if (!is_dir($GLOBALS['statesDir'])) {
        mkdir($GLOBALS['statesDir'], 0755, true);
    }
}

function getSettings() {
    if (!file_exists($GLOBALS['settingsPath'])) {
        return ['force_channel' => ''];
    }
    return json_decode(file_get_contents($GLOBALS['settingsPath']), true) ?: ['force_channel' => ''];
}

function saveSettings($settings) {
    if (!is_dir(dirname($GLOBALS['settingsPath']))) {
        mkdir(dirname($GLOBALS['settingsPath']), 0755, true);
    }
    file_put_contents($GLOBALS['settingsPath'], json_encode($settings));
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

function isMember($chatId, $channel) {
    if (!$channel) return true;
    $response = bot('getChatMember', [
        'chat_id' => $channel,
        'user_id' => $chatId
    ]);
    if ($response && isset($response['result']['status'])) {
        $status = $response['result']['status'];
        return in_array($status, ['member', 'administrator', 'creator']);
    }
    return false;
}

function getJoinKeyboard($channel) {
    $channelLink = '';
    if (strpos($channel, '@') === 0) {
        $channelLink = "https://t.me/" . ltrim($channel, '@');
    } elseif (strpos($channel, 'https://t.me/') === 0 || strpos($channel, 'https://telegram.me/') === 0) {
        $channelLink = $channel;
    } else {
        $channelLink = "https://t.me/" . $channel;
    }
    
    return json_encode([
        'inline_keyboard' => [
            [['text' => '🔗 لینک کانال', 'url' => $channelLink]],
            [['text' => '✅ عضو شدم', 'callback_data' => 'verify_join']]
        ]
    ]);
}
?>