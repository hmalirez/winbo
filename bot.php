<?php
require_once 'config.php';

function makeRequest($method, $params = []) {
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/' . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

function sendMessage($chat_id, $text, $reply_markup = null, $parse_mode = 'HTML') {
    $params = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => $parse_mode
    ];
    if ($reply_markup) {
        $params['reply_markup'] = json_encode($reply_markup);
    }
    return makeRequest('sendMessage', $params);
}

function editMessage($chat_id, $message_id, $text, $reply_markup = null) {
    $params = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    if ($reply_markup) {
        $params['reply_markup'] = json_encode($reply_markup);
    }
    return makeRequest('editMessageText', $params);
}

function getRandomLine($file) {
    if (!file_exists($file)) return null;
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (empty($lines)) return null;
    return trim($lines[array_rand($lines)]);
}

function addLine($file, $line) {
    $line = trim($line);
    if (in_array($file, [CONFIG_FILE, CONFIG_FREE_FILE, USER_CONFIG_FILE])) {
        $configName = '[🇮🇷@Win2Ray]•[𝖥𝖱𝖤𝖤]';
        if (preg_match('/"(remark|ps)"\s*:\s*"[^"]+"/', $line)) {
            $line = preg_replace('/"(remark|ps)"\s*:\s*"[^"]+"/', '"remark":"' . $configName . '"', $line);
        }
    }
    file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function getRandomConfig($file, $replaceIp = null) {
    if (!file_exists($file)) return null;
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (empty($lines)) return null;
    
    $config = $lines[array_rand($lines)];
    $configName = '[🇮🇷@Win2Ray]•[𝖥𝖱𝖤𝖤]';
    
    if (preg_match('/"(remark|ps)"\s*:\s*"([^"]+)"/', $config, $m)) {
        $config = preg_replace('/"(remark|ps)"\s*:\s*"[^"]+"/', '"remark":"' . $configName . '"', $config);
    } else {
        $config = preg_replace('/"server"/', '"remark":"' . $configName . '","server"', $config);
    }
    
    if ($replaceIp) {
        $config = preg_replace('/"server"\s*:\s*"[^"]+"/', '"server":"' . $replaceIp . '"', $config);
    }
    
    return $config;
}

function getUserConfig($userId) {
    $file = DATA_DIR . '/user_config_' . $userId . '.txt';
    if (!file_exists($file)) return null;
    return file_get_contents($file);
}

function replaceServerIp($config, $newIp) {
    $patterns = [
        '/"server"\s*:\s*"[^"]+"/',
        '/"server"\s*:\s*"[\\s\\S]*?"/',
        '/server=([^&]+)/'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $config)) {
            if (strpos($pattern, 'server=') !== false) {
                $config = preg_replace($pattern, 'server=' . $newIp, $config);
            } else {
                $config = preg_replace($pattern, '"server":"' . $newIp . '"', $config);
            }
            break;
        }
    }
    return $config;
}

function saveUserConfig($userId, $config) {
    $file = DATA_DIR . '/user_config_' . $userId . '.txt';
    file_put_contents($file, $config);
}

function createInlineKeyboard($buttons, $backBtn = false, $homeBtn = false) {
    $keyboard = [];
    foreach ($buttons as $btn) {
        $keyboard[] = [$btn];
    }
    if ($backBtn || $homeBtn) {
        $row = [];
        if ($backBtn) $row[] = ['text' => "🔙 بازگشت", 'callback_data' => 'back'];
        if ($homeBtn) $row[] = ['text' => "🏠 صفحه اصلی", 'callback_data' => 'home'];
        $keyboard[] = $row;
    }
    return ['inline_keyboard' => $keyboard];
}

function mainMenuKeyboard($isAdminUser = false) {
    $buttons = [
        ['text' => "📥 دریافت کانفیگ", 'callback_data' => 'get_config'],
        ['text' => "📤 ارسال به ربات", 'callback_data' => 'send_to_bot'],
        ['text' => "🎁 کد هدیه", 'callback_data' => 'gift_code']
    ];
    if ($isAdminUser) {
        $buttons[] = ['text' => "🔒 مدیریت", 'callback_data' => 'management'];
    }
    return createInlineKeyboard($buttons);
}

function isAdmin($userId) {
    return $userId == ADMIN_ID;
}

function getMainMenu($isAdminUser = false) {
    $text = "🇮🇷 خوش آمدید به ربات Win2Ray\n\n🔸 لطفاً یک گزینه را انتخاب کنید";
    return ['text' => $text, 'keyboard' => mainMenuKeyboard($isAdminUser)];
}

function registerUser($userId) {
    $users = [];
    if (file_exists(USERS_FILE)) {
        $users = json_decode(file_get_contents(USERS_FILE), true) ?: [];
    }
    if (!isset($users[$userId])) {
        $users[$userId] = ['registered_at' => time()];
        file_put_contents(USERS_FILE, json_encode($users));
    }
}

function getStats() {
    $stats = [
        'users' => 0,
        'configs' => 0,
        'ips' => 0,
        'configs_free' => 0,
        'ips_free' => 0,
        'generated_configs' => 0
    ];
    
    if (file_exists(USERS_FILE)) {
        $users = json_decode(file_get_contents(USERS_FILE), true);
        $stats['users'] = count($users) ?: 0;
    }
    
    if (file_exists(CONFIG_FILE)) {
        $lines = file(CONFIG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $stats['configs'] = count($lines) ?: 0;
    }
    
    if (file_exists(CONFIG_FREE_FILE)) {
        $lines = file(CONFIG_FREE_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $stats['configs_free'] = count($lines) ?: 0;
    }
    
    if (file_exists(IP_FILE)) {
        $lines = file(IP_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $stats['ips'] = count($lines) ?: 0;
    }
    
    if (file_exists(IP_FREE_FILE)) {
        $lines = file(IP_FREE_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $stats['ips_free'] = count($lines) ?: 0;
    }
    
    $files = glob(DATA_DIR . '/user_config_*.txt');
    $stats['generated_configs'] = count($files) ?: 0;
    
    return $stats;
}

function extractSubUser($url) {
    if (preg_match('/sub=([^&\s]+)/', $url, $m)) {
        return $m[1];
    }
    return null;
}

function canUseGift($userId) {
    $usage = [];
    if (file_exists(GIFT_USAGE_FILE)) {
        $usage = json_decode(file_get_contents(GIFT_USAGE_FILE), true) ?: [];
    }
    
    if (!isset($usage[$userId])) return true;
    
    $lastUsage = $usage[$userId];
    return (time() - $lastUsage) > (7 * 24 * 60 * 60);
}

function markGiftUsed($userId) {
    $usage = [];
    if (file_exists(GIFT_USAGE_FILE)) {
        $usage = json_decode(file_get_contents(GIFT_USAGE_FILE), true) ?: [];
    }
    $usage[$userId] = time();
    file_put_contents(GIFT_USAGE_FILE, json_encode($usage));
}

function getStateFile($userId) {
    return DATA_DIR . '/state_' . $userId . '.json';
}

function getUserState($userId) {
    $stateFile = getStateFile($userId);
    if (file_exists($stateFile)) {
        return json_decode(file_get_contents($stateFile), true);
    }
    return null;
}

function setUserState($userId, $state) {
    $stateFile = getStateFile($userId);
    file_put_contents($stateFile, json_encode($state));
}

function clearUserState($userId) {
    $stateFile = getStateFile($userId);
    if (file_exists($stateFile)) {
        unlink($stateFile);
    }
}

function isValidV2rayConfig($text) {
    $text = trim($text);
    if (empty($text)) return false;
    $textLower = strtolower($text);
    $hasServer = preg_match('/"server"\s*:\s*"[^"]+"/i', $text);
    $hasPort = preg_match('/"port"\s*:\s*"[^"]+"/i', $text) || preg_match('/"port"\s*:\s*\d+/', $text);
    if (!$hasServer || !$hasPort) return false;
    
    $patterns = ['vless', 'vmess', 'trojan', 'shadowsocks', 'ss://'];
    foreach ($patterns as $pattern) {
        if (strpos($textLower, $pattern) !== false) return true;
    }
    if (preg_match('/^\{.*\}$/s', $text) || strpos($textLower, 'ss://') === 0) return true;
    return false;
}

function isValidIp($text) {
    $ipPattern = '/^(?:(?:[0-9]{1,3}\.){3}[0-9]{1,3}|[0-9a-fA-F:]+)$/';
    $lines = explode("\n", $text);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line && !preg_match('/^https?:\/\//', $line) && !preg_match($ipPattern, $line)) {
            return false;
        }
    }
    return true;
}

function forwardMessageToAll($fromChatId, $messageId, $text = null) {
    $users = [];
    if (file_exists(USERS_FILE)) {
        $users = json_decode(file_get_contents(USERS_FILE), true) ?: [];
    }
    foreach ($users as $userId => $data) {
        if ($userId != ADMIN_ID) {
            if ($text) {
                sendMessage($userId, $text, mainMenuKeyboard());
            } else {
                makeRequest('forwardMessage', [
                    'chat_id' => $userId,
                    'from_chat_id' => $fromChatId,
                    'message_id' => $messageId
                ]);
            }
        }
    }
}

function useGiftCode($code) {
    if (!file_exists(GIFT_FILE)) return null;
    $lines = file(GIFT_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $foundIndex = -1;
    foreach ($lines as $i => $line) {
        $subUser = extractSubUser(trim($line));
        if ($subUser && $subUser == $code) {
            $foundIndex = $i;
            break;
        }
    }
    if ($foundIndex === -1) return null;
    
    $url = trim($lines[$foundIndex]);
    unset($lines[$foundIndex]);
    file_put_contents(GIFT_FILE, implode(PHP_EOL, $lines));
    
    return $url;
}

ensureDataDir();

$content = file_get_contents('php://input');
$update = json_decode($content, true);

if (!$update) exit;

// Handle messages
if (isset($update['message'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $message_id = $message['message_id'];
    $user_id = $message['from']['id'];
    $text = trim($message['text'] ?? '');
    
    registerUser($user_id);
    
    if ($text == '/start') {
        $data = getMainMenu(isAdmin($user_id));
        sendMessage($chat_id, $data['text'], $data['keyboard']);
    }
    
    $state = getUserState($user_id);
    
    // Admin handlers
    if (isAdmin($user_id)) {
        // Forwarded messages for broadcast
        if (isset($message['forward_from']) || isset($message['forward_sender_name']) || isset($message['forward_from_chat'])) {
            forwardMessageToAll($chat_id, $message_id);
            sendMessage($chat_id, "✅ پیام به همه کاربران فوروارد شد", mainMenuKeyboard());
        }
        
        // Admin config input
        if (isset($state['waiting_for']) && $state['waiting_for'] == 'admin_configs') {
            $lines = explode("\n", $text);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line) addLine(CONFIG_FILE, $line);
            }
            editMessage($chat_id, $state['message_id'], "✅ کانفیگ‌ها ذخیره شدند", mainMenuKeyboard());
            clearUserState($user_id);
        }
        
        // Admin IP input
        if (isset($state['waiting_for']) && $state['waiting_for'] == 'admin_ips') {
            $lines = explode("\n", $text);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line) addLine(IP_FILE, $line);
            }
            editMessage($chat_id, $state['message_id'], "✅ آیپی‌ها ذخیره شدند", mainMenuKeyboard());
            clearUserState($user_id);
        }
        
        // Admin gift URLs input
        if (isset($state['waiting_for']) && $state['waiting_for'] == 'admin_gift_urls') {
            $lines = explode("\n", $text);
            $urls = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line) $urls[] = $line;
            }
            if (!empty($urls)) {
                file_put_contents(GIFT_FILE, implode(PHP_EOL, $urls) . PHP_EOL, FILE_APPEND | LOCK_EX);
            }
            editMessage($chat_id, $state['message_id'], "✅ لینک‌های اشتراک ذخیره شدند", mainMenuKeyboard());
            clearUserState($user_id);
        }
    }
    
    // User handlers for state-based inputs
    // User IP input for custom config
    if (isset($state['waiting_for']) && $state['waiting_for'] == 'send_ip' && !isAdmin($user_id)) {
        $config = getUserConfig($user_id);
        if ($config) {
            $newConfig = replaceServerIp($config, $text);
            saveUserConfig($user_id, $newConfig);
            $msgText = "⚙️ کانفیگ با آیپی شما:\n\n<code>" . htmlspecialchars($newConfig) . "</code>\n\n🔸 برای تعویض دیگر آیپی روی دکمه زیر بزنید:";
            $keyboard = createInlineKeyboard([
                ['text' => "🔄 تعویض آیپی", 'callback_data' => 'change_ip_custom']
            ], true, true);
        } else {
            $msgText = "❌ خطایی رخ داد";
            $keyboard = createInlineKeyboard([], true, true);
        }
        editMessage($chat_id, $state['message_id'], $msgText, $keyboard);
        clearUserState($user_id);
    }
    
    // Donate config input
    if (isset($state['waiting_for']) && $state['waiting_for'] == 'donate_config') {
        if (isValidV2rayConfig($text)) {
            addLine(USER_CONFIG_FILE, $text);
            editMessage($chat_id, $state['message_id'], "✅ کانفیگ شما ذخیره شد", mainMenuKeyboard());
        } else {
            editMessage($chat_id, $state['message_id'], "❌ کانفیگ معتبر نیست. لطفاً دوباره تلاش کنید:", createInlineKeyboard([], true, true));
        }
        clearUserState($user_id);
    }
    
    // Donate IP input
    if (isset($state['waiting_for']) && $state['waiting_for'] == 'donate_ip') {
        if (isValidIp($text)) {
            addLine(IP_FREE_FILE, $text);
            editMessage($chat_id, $state['message_id'], "✅ آیپی تمیز ذخیره شد", mainMenuKeyboard());
        } else {
            editMessage($chat_id, $state['message_id'], "❌ آیپی معتبر نیست. لطفاً دوباره تلاش کنید:", createInlineKeyboard([], true, true));
        }
        clearUserState($user_id);
    }
    
    // Gift code input
    if (isset($state['waiting_for']) && $state['waiting_for'] == 'gift_code_input') {
        if (canUseGift($user_id)) {
            $url = useGiftCode($text);
            if ($url) {
                markGiftUsed($user_id);
                editMessage($chat_id, $state['message_id'], "🎉 کد هدیه معتبر بود!\n\n🔗 لینک اشتراک:\n<code>" . htmlspecialchars($url) . "</code>", mainMenuKeyboard());
            } else {
                editMessage($chat_id, $state['message_id'], "❌ کد هدیه نامعتبر است یا قبلاً استفاده شده", createInlineKeyboard([], true, true));
            }
        } else {
            editMessage($chat_id, $state['message_id'], "❌ هر کاربر هفته‌ای یک بار می‌تواند کد هدیه وارد کند", createInlineKeyboard([], true, true));
        }
        clearUserState($user_id);
    }
}

// Handle callback queries
if (isset($update['callback_query'])) {
    $callback = $update['callback_query'];
    $chat_id = $callback['message']['chat']['id'];
    $message_id = $callback['message']['message_id'];
    $user_id = $callback['from']['id'];
    $data = $callback['data'];
    
    registerUser($user_id);
    
    // Home button
    if ($data == 'home') {
        $main = getMainMenu();
        editMessage($chat_id, $message_id, $main['text'], $main['keyboard']);
    }
    
    // Back button - goes to previous state (for simplicity, back goes to main menu)
    if ($data == 'back') {
        $main = getMainMenu();
        editMessage($chat_id, $message_id, $main['text'], $main['keyboard']);
    }
    
    // Get config menu
    if ($data == 'get_config') {
        $text = "📥 دریافت کانفیگ\n\n🔸 لطفاً نوع کانفیگ را انتخاب کنید:";
        $keyboard = createInlineKeyboard([
            ['text' => "⚙️ کانفیگ اختصاصی", 'callback_data' => 'custom_config'],
            ['text' => "🎁 کانفیگ اهدایی", 'callback_data' => 'free_config']
        ], true, true);
        editMessage($chat_id, $message_id, $text, $keyboard);
    }
    
    // Custom config flow
    if ($data == 'custom_config') {
        $text = "⚙️ کانفیگ اختصاصی\n\n🔸 آیا آیپی تمیز کلودفلر دارید؟";
        $keyboard = createInlineKeyboard([
            ['text' => "✅ بله", 'callback_data' => 'has_ip'],
            ['text' => "❌ خیر", 'callback_data' => 'no_ip']
        ], true, true);
        editMessage($chat_id, $message_id, $text, $keyboard);
    }
    
    if ($data == 'has_ip') {
        setUserState($user_id, ['waiting_for' => 'send_ip', 'message_id' => $message_id]);
        $text = "🔸 لطفاً یک آیپی تمیز کلودفلر بفرستید:";
        $keyboard = createInlineKeyboard([], true, true);
        editMessage($chat_id, $message_id, $text, $keyboard);
    }
    
    if ($data == 'no_ip') {
        $config = getRandomConfig(CONFIG_FILE);
        if (!$config) {
            $text = "❌ کانفیگی موجود نیست";
            $keyboard = createInlineKeyboard([], true, true);
        } else {
            saveUserConfig($user_id, $config);
            $text = "⚙️ کانفیگ شما آماده شد:\n\n<code>" . htmlspecialchars($config) . "</code>\n\n🔸 برای تعویض آیپی روی دکمه زیر بزنید:";
            $keyboard = createInlineKeyboard([
                ['text' => "🔄 تعویض آیپی", 'callback_data' => 'change_ip_custom']
            ], true, true);
        }
        editMessage($chat_id, $message_id, $text, $keyboard);
    }
    
    if ($data == 'change_ip_custom') {
        if (!file_exists(IP_FILE)) {
            $text = "❌ فایل آیپی موجود نیست";
            $keyboard = createInlineKeyboard([
                ['text' => "🔄 تعویض آیپی", 'callback_data' => 'change_ip_custom']
            ], true, true);
        } else {
            $ip = getRandomLine(IP_FILE);
            if (!$ip) {
                $text = "❌ آیپی جدیدی موجود نیست";
                $keyboard = createInlineKeyboard([
                    ['text' => "🔄 تعویض آیپی", 'callback_data' => 'change_ip_custom']
                ], true, true);
} else {
                    $config = getUserConfig($user_id);
                    if (!$config) {
                        $text = "❌ کانفیگ یافت نشد";
                        $keyboard = createInlineKeyboard([], true, true);
                    } else {
                        $newConfig = replaceServerIp($config, $ip);
                        saveUserConfig($user_id, $newConfig);
                        $text = "⚙️ کانفیگ با آیپی جدید:\n\n<code>" . htmlspecialchars($newConfig) . "</code>\n\n🔸 برای تعویض دوباره روی دکمه زیر بزنید:";
                        $keyboard = createInlineKeyboard([
                            ['text' => "🔄 تعویض آیپی", 'callback_data' => 'change_ip_custom']
                        ], true, true);
                    }
                }
        }
        editMessage($chat_id, $message_id, $text, $keyboard);
    }
    
    // Free config flow
    if ($data == 'free_config') {
        $config = getRandomConfig(CONFIG_FREE_FILE);
        if (!$config) {
            $text = "❌ کانفیگ اهدایی موجود نیست";
            $keyboard = createInlineKeyboard([], true, true);
        } else {
            saveUserConfig($user_id, $config);
            $text = "🎁 کانفیگ اهدایی:\n\n<code>" . htmlspecialchars($config) . "</code>\n\n🔸 برای تعویض آیپی روی دکمه زیر بزنید:";
            $keyboard = createInlineKeyboard([
                ['text' => "🔄 تعویض آیپی", 'callback_data' => 'change_ip_free']
            ], true, true);
        }
        editMessage($chat_id, $message_id, $text, $keyboard);
    }
    
    if ($data == 'change_ip_free') {
        if (!file_exists(IP_FREE_FILE)) {
            $text = "❌ فایل آیپی موجود نیست";
            $keyboard = createInlineKeyboard([
                ['text' => "🔄 تعویض آیپی", 'callback_data' => 'change_ip_free']
            ], true, true);
        } else {
            $ip = getRandomLine(IP_FREE_FILE);
            if (!$ip) {
                $text = "❌ آیپی جدیدی موجود نیست";
                $keyboard = createInlineKeyboard([
                    ['text' => "🔄 تعویض آیپی", 'callback_data' => 'change_ip_free']
                ], true, true);
} else {
                    $config = getUserConfig($user_id);
                    if (!$config) {
                        $text = "❌ کانفیگ یافت نشد";
                        $keyboard = createInlineKeyboard([], true, true);
                    } else {
                        $newConfig = replaceServerIp($config, $ip);
                        saveUserConfig($user_id, $newConfig);
                        $text = "🎁 کانفیگ اهدایی با آیپی جدید:\n\n<code>" . htmlspecialchars($newConfig) . "</code>\n\n🔸 برای تعویض دوباره روی دکمه زیر بزنید:";
                        $keyboard = createInlineKeyboard([
                            ['text' => "🔄 تعویض آیپی", 'callback_data' => 'change_ip_free']
                        ], true, true);
                    }
                }
        }
        editMessage($chat_id, $message_id, $text, $keyboard);
    }
    
    // Send to bot menu (donate configs/IPs)
    if ($data == 'send_to_bot') {
        $text = "📤 ارسال به ربات\n\n🔸 لطفاً نوع ارسال را انتخاب کنید:";
        $keyboard = createInlineKeyboard([
            ['text' => "🎁 اهدای کانفیگ", 'callback_data' => 'donate_config'],
            ['text' => "🌐 اهدای آیپی", 'callback_data' => 'donate_ip']
        ], true, true);
        editMessage($chat_id, $message_id, $text, $keyboard);
    }
    
    if ($data == 'donate_config') {
        setUserState($user_id, ['waiting_for' => 'donate_config', 'message_id' => $message_id]);
        $text = "🎁 اهدای کانفیگ\n\n🔸 لطفاً کانفیگ v2ray را ارسال کنید:";
        $keyboard = createInlineKeyboard([], true, true);
        editMessage($chat_id, $message_id, $text, $keyboard);
    }
    
    if ($data == 'donate_ip') {
        setUserState($user_id, ['waiting_for' => 'donate_ip', 'message_id' => $message_id]);
        $text = "🌐 اهدای آیپی\n\n🔸 لطفاً آیپی تمیز کلودفلر ارسال کنید:";
        $keyboard = createInlineKeyboard([], true, true);
        editMessage($chat_id, $message_id, $text, $keyboard);
    }
    
    if ($data == 'gift_code') {
        setUserState($user_id, ['waiting_for' => 'gift_code_input', 'message_id' => $message_id]);
        $text = "🎁 کد هدیه\n\n🔸 لطفاً کد هدیه را وارد کنید:";
        $keyboard = createInlineKeyboard([], true, true);
        editMessage($chat_id, $message_id, $text, $keyboard);
    }
    
    // Admin management menu (shown only for admin)
    if ($data == 'management') {
        $text = "🔒 پنل مدیریت\n\n🔸 لطفاً گزینه مورد نظر را انتخاب کنید:";
        $keyboard = createInlineKeyboard([
            ['text' => "⚙️ ارسال کانفیگ", 'callback_data' => 'admin_send_config'],
            ['text' => "🌐 ارسال آیپی", 'callback_data' => 'admin_send_ip'],
            ['text' => "📢 فوروارد همگانی", 'callback_data' => 'broadcast'],
            ['text' => "📊 آمار", 'callback_data' => 'stats'],
            ['text' => "🎁 کد هدیه", 'callback_data' => 'admin_gift'],
            ['text' => "🗑️ حذف محتوا", 'callback_data' => 'delete_content']
        ], false, true);
        editMessage($chat_id, $message_id, $text, $keyboard);
    }
    
    // Admin: Send configs
    if ($data == 'admin_send_config') {
        setUserState($user_id, ['waiting_for' => 'admin_configs', 'message_id' => $message_id]);
        $text = "⚙️ ارسال کانفیگ\n\n🔸 لطفاً لیست کانفیگ‌ها را ارسال کنید (هر کانفیگ در یک خط):";
        $keyboard = createInlineKeyboard([], true, true);
        editMessage($chat_id, $message_id, $text, $keyboard);
    }
    
    // Admin: Send IPs
    if ($data == 'admin_send_ip') {
        setUserState($user_id, ['waiting_for' => 'admin_ips', 'message_id' => $message_id]);
        $text = "🌐 ارسال آیپی\n\n🔸 لطفاً لیست آیپی‌های تمیز را ارسال کنید (هر آیپی در یک خط):";
        $keyboard = createInlineKeyboard([], true, true);
        editMessage($chat_id, $message_id, $text, $keyboard);
    }
    
    // Admin: Gift code management
    if ($data == 'admin_gift') {
        $text = "🎁 مدیریت کد هدیه\n\n🔸 لطفاً گزینه مورد نظر را انتخاب کنید:";
        $keyboard = createInlineKeyboard([
            ['text' => "📥 ارسال محتوا", 'callback_data' => 'admin_gift_send'],
            ['text' => "📋 دریافت کد هدیه", 'callback_data' => 'admin_gift_get_codes']
        ], true, true);
        editMessage($chat_id, $message_id, $text, $keyboard);
    }
    
    if ($data == 'admin_gift_send') {
        setUserState($user_id, ['waiting_for' => 'admin_gift_urls', 'message_id' => $message_id]);
        $text = "📥 ارسال محتوا\n\n🔸 لطفاً لینک‌های اشتراک را ارسال کنید (هر لینک در یک خط):";
        $keyboard = createInlineKeyboard([], true, true);
        editMessage($chat_id, $message_id, $text, $keyboard);
    }
    
    if ($data == 'admin_gift_get_codes') {
        $codes = [];
        if (file_exists(GIFT_FILE)) {
            $codes = file(GIFT_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        }
        $text = "📋 کدهای هدیه موجود:\n\n" . (empty($codes) ? "❌ کدی موجود نیست" : implode("\n", array_map(function($c) { return "🔹 " . $c; }, $codes)));
        $keyboard = createInlineKeyboard([], true, true);
        editMessage($chat_id, $message_id, $text, $keyboard);
    }
    
    // Admin: Broadcast
    if ($data == 'broadcast') {
        $text = "📢 فوروارد همگانی\n\n🔸 لطفاً پیام یا محتوا را فوروارد کنید:";
        $keyboard = createInlineKeyboard([], true, true);
        editMessage($chat_id, $message_id, $text, $keyboard);
    }
    
    // Admin: Stats
    if ($data == 'stats') {
        $stats = getStats();
        $text = "📊 آمار ربات\n\n🔹 تعداد کاربران: " . $stats['users'] . "\n🔹 کانفیگ‌های اختصاصی: " . $stats['configs'] . "\n🔹 کانفیگ‌های اهدایی: " . $stats['configs_free'] . "\n🔹 آیپی‌های اختصاصی: " . $stats['ips'] . "\n🔹 آیپی‌های اهدایی: " . $stats['ips_free'] . "\n🔹 کانفیگ‌های تولید شده: " . $stats['generated_configs'];
        $keyboard = createInlineKeyboard([], true, true);
        editMessage($chat_id, $message_id, $text, $keyboard);
    }
    
    // Admin: Delete content
    if ($data == 'delete_content') {
        $text = "🗑️ حذف محتوا\n\n🔸 لطفاً محتوای مورد نظر را انتخاب کنید:";
        $keyboard = createInlineKeyboard([
            ['text' => "🗑️ حذف کانفیگ اختصاصی", 'callback_data' => 'del_configs'],
            ['text' => "🗑️ حذف آیپی اختصاصی", 'callback_data' => 'del_ips'],
            ['text' => "🗑️ حذف کانفیگ اهدایی", 'callback_data' => 'del_configs_free'],
            ['text' => "🗑️ حذف آیپی اهدایی", 'callback_data' => 'del_ips_free']
        ], true, true);
        editMessage($chat_id, $message_id, $text, $keyboard);
    }
    
    // Delete handlers
    if (strpos($data, 'del_') === 0) {
        $fileMap = [
            'del_configs' => CONFIG_FILE,
            'del_ips' => IP_FILE,
            'del_configs_free' => CONFIG_FREE_FILE,
            'del_ips_free' => IP_FREE_FILE
        ];
        
        if (isset($fileMap[$data])) {
            $file = $fileMap[$data];
            file_put_contents($file, '');
            $text = "✅ محتوا حذف شد";
            $keyboard = createInlineKeyboard([], true, true);
            editMessage($chat_id, $message_id, $text, $keyboard);
        }
    }
}