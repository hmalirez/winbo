<?php
$botToken = '8933092110:AAE1-gF6Jw0aLfS0SR-aaOxZ6V-kKHECnvE';
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
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
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

function getRemarkName() {
    return '[@Win2Ray]•[𝖥𝖱𝖤𝖤]';
}

function renameConfigRemark($content) {
    $remarkName = getRemarkName();
    
    // Handle vmess:// URLs (base64 encoded JSON)
    if (strpos($content, 'vmess://') === 0) {
        $base64Part = substr($content, 8);
        $decoded = base64_decode($base64Part);
        if ($decoded) {
            $json = json_decode($decoded, true);
            if ($json && is_array($json)) {
                $json['ps'] = $remarkName;
                $json['remark'] = $remarkName;
                $content = 'vmess://' . base64_encode(json_encode($json, JSON_UNESCAPED_UNICODE));
            }
        }
        return $content;
    }
    
    // Handle ss:// URLs
    if (strpos($content, 'ss://') === 0) {
        // Decode base64 and modify if possible
        $base64Part = substr($content, 5);
        
        // Remove any query string for decoding
        $mainPart = $base64Part;
        if (strpos($base64Part, '?') !== false) {
            $parts = explode('?', $base64Part, 2);
            $mainPart = $parts[0];
        }
        
        $decoded = base64_decode(rawurldecode($mainPart));
        if ($decoded) {
            // Check if decoded content has remark in it
            if (preg_match('/\?.*remark=/', $decoded)) {
                // Replace remark in decoded string - do NOT use urlencode to preserve Unicode
                $decoded = preg_replace('/remark=[^&]*/i', 'remark=' . $remarkName, $decoded);
                $encoded = base64_encode($decoded);
                $content = 'ss://' . $encoded;
            } else {
                // Try to add remark
                $parts = explode(':', $decoded);
                if (count($parts) >= 3) {
                    $params = [];
                    if (isset($parts[3])) {
                        parse_str($parts[3], $params);
                    }
                    $params['remark'] = $remarkName;
                    $parts[3] = http_build_query($params);
                    $encoded = base64_encode(implode(':', $parts));
                    $content = 'ss://' . $encoded;
                }
            }
            return $content;
        }
        
        // If base64 decode fails, try to handle as plain URL format
        if (preg_match('/\?.*remark=/i', $content)) {
            $content = preg_replace('/remark=[^&\s]*/i', 'remark=' . $remarkName, $content);
        }
        return $content;
    }
    
    // Handle trojan:// URLs
    if (strpos($content, 'trojan://') === 0) {
        $parsed = parse_url($content);
        $query = [];
        parse_str($parsed['query'] ?? '', $query);
        $query['remark'] = $remarkName;
        // Build query manually to preserve Unicode
        $newQuery = '';
        foreach ($query as $k => $v) {
            $newQuery .= ($newQuery ? '&' : '') . "$k=$v";
        }
        $content = 'trojan://@' . ($parsed['host'] ?? '') . ':' . ($parsed['port'] ?? '') . '?' . $newQuery;
        return $content;
    }
    
    // Handle plain text configs (YAML/JSON)
    $content = preg_replace('/remark:\s*([^\n,}]*(?:\{[^}]*\})?[^\n,}]*)/i', 'remark: ' . $remarkName, $content);
    $content = preg_replace('/"remark"\s*:\s*"[^"]*"/i', '"remark":"' . $remarkName . '"', $content);
    return $content;
}
?>