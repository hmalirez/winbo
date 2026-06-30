<?php
require_once __DIR__ . '/config.php';

$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (!$update) exit;

$chatId = $update['message']['chat']['id'] ?? $update['callback_query']['message']['chat']['id'] ?? null;
$messageId = $update['message']['message_id'] ?? $update['callback_query']['message']['message_id'] ?? null;
$callbackId = $update['callback_query']['id'] ?? null;
$data = $update['callback_query']['data'] ?? null;
$text = isset($update['message']['text']) && $update['message']['text'] !== '/start' ? $update['message']['text'] : '';

if (isset($update['message']) && $update['message']['text'] === '/start') {
    $isAdmin = ((int)$chatId === $adminId);
    $welcomeText = "👋 به ربات Win2Ray خوش آمدید!\n\nلطفاً یکی از گزینه‌های زیر را انتخاب کنید:";
    bot('sendMessage', ['chat_id' => $chatId, 'text' => $welcomeText, 'reply_markup' => getMainMenu($isAdmin), 'parse_mode' => 'HTML']);
    exit;
}

if (!empty($callbackId)) {
    bot('answerCallbackQuery', ['callback_query_id' => $callbackId, 'show_alert' => false]);
}

if ($data) {
    handleCallback($chatId, $messageId, $data);
}

if (!empty($text) && (strpos($text, 'ss://') !== false || strpos($text, 'vmess://') !== false || strpos($text, 'trojan://') !== false)) {
    handleConfigSubmission($chatId, $messageId, $text);
}

function handleCallback($chatId, $messageId, $data) {
    global $adminId, $donatedConfigPath, $adminConfigPath;
    
    $parts = explode(':', $data);
    $action = $parts[0];
    $isAdmin = ((int)$chatId === $adminId);
    
    if ($action === 'receive_config') {
        showReceiveConfigMenu($chatId, $messageId);
    } elseif ($action === 'donate_config') {
        showDonateConfigMenu($chatId, $messageId, ((int)$chatId === $adminId));
    } elseif ($action === 'manage') {
        if ((int)$chatId === $adminId) {
            showManageMenu($chatId, $messageId);
        } else {
            $text = "⛔ دسترسی محدود فقط برای مدیر";
            editMessageText($chatId, $messageId, $text, getMainMenu(false));
        }
    } elseif ($action === 'custom_configs') {
        $page = max(1, intval($parts[1] ?? 1));
        showCustomConfigs($chatId, $messageId, $page);
    } elseif ($action === 'donated_configs') {
        $page = max(1, intval($parts[1] ?? 1));
        showDonatedConfigs($chatId, $messageId, $page);
    } elseif ($action === 'main_menu') {
        showMainMenu($chatId, $messageId);
    } elseif ($action === 'back') {
        $to = $parts[1] ?? 'main';
        if ($to === 'main') {
            showMainMenu($chatId, $messageId);
        } elseif ($to === 'receive') {
            showReceiveConfigMenu($chatId, $messageId);
        } elseif ($to === 'donate') {
            showDonateConfigMenu($chatId, $messageId, true);
        } elseif ($to === 'manage') {
            showManageMenu($chatId, $messageId);
        }
    } elseif ($action === 'admin_send_config') {
        showAdminSendConfig($chatId, $messageId);
    } elseif ($action === 'admin_clear_list') {
        showAdminClearList($chatId, $messageId);
    } elseif ($action === 'admin_stats') {
        showAdminStats($chatId, $messageId);
    } elseif ($action === 'clear_custom') {
        clearConfigsFile($adminConfigPath);
        editMessageText($chatId, $messageId, "✅ لیست کانفیگ‌های اختصاصی خالی شد!", getManageMenu());
    } elseif ($action === 'clear_donated') {
        clearConfigsFile($donatedConfigPath);
        editMessageText($chatId, $messageId, "✅ لیست کانفیگ‌های اهدایی خالی شد!", getManageMenu());
    } elseif ($action === 'save_custom') {
        promptForCustomConfig($chatId, $messageId);
    } elseif ($action === 'save_donated') {
        promptForDonatedConfig($chatId, $messageId);
    }
}

function handleConfigSubmission($chatId, $messageId, $text) {
    global $adminId;
    
    $isAdmin = ((int)$chatId === $adminId);
    $stateFile = __DIR__ . '/files/states/' . $chatId . '.json';
    $statesDir = __DIR__ . '/files/states/';
    
    if (!is_dir($statesDir)) mkdir($statesDir, 0755, true);
    
    if (!file_exists($stateFile)) return;
    
    $state = json_decode(file_get_contents($stateFile), true);
    
    if ($state && isset($state['pending_config'])) {
        $config = renameConfigRemark($text);
        $targetPath = $state['pending_config'];
        saveConfigToFile($targetPath, $config);
        unlink($stateFile);
        bot('sendMessage', ['chat_id' => $chatId, 'text' => "✅ کانفیگ با موفقیت ذخیره شد!", 'reply_markup' => getMainMenu($isAdmin), 'parse_mode' => 'HTML']);
    }
}

function promptForCustomConfig($chatId, $messageId) {
    $stateFile = __DIR__ . '/files/states/' . $chatId . '.json';
    $statesDir = __DIR__ . '/files/states/';
    if (!is_dir($statesDir)) mkdir($statesDir, 0755, true);
    file_put_contents($stateFile, json_encode(['pending_config' => $GLOBALS['adminConfigPath']]));
    editMessageText($chatId, $messageId, "🔧 ارسال کانفیگ اختصاصی\n\nلطفاً کانفیگ خود را ارسال کنید:", getAdminCancelMenu());
}

function promptForDonatedConfig($chatId, $messageId) {
    $stateFile = __DIR__ . '/files/states/' . $chatId . '.json';
    $statesDir = __DIR__ . '/files/states/';
    if (!is_dir($statesDir)) mkdir($statesDir, 0755, true);
    file_put_contents($stateFile, json_encode(['pending_config' => $GLOBALS['donatedConfigPath']]));
    editMessageText($chatId, $messageId, "🎁 اهدای کانفیگ\n\nلطفاً کانفیگ اهدایی خود را ارسال کنید:", getDonateCancelMenu());
}

function getDonateCancelMenu() {
    return json_encode([
        'inline_keyboard' => [
            [['text' => '🏠 صفحه اصلی', 'callback_data' => 'main_menu']],
            [['text' => '🔙 بازگشت', 'callback_data' => 'back:donate']]
        ]
    ]);
}

function getAdminCancelMenu() {
    return json_encode([
        'inline_keyboard' => [
            [['text' => '🏠 صفحه اصلی', 'callback_data' => 'main_menu']],
            [['text' => '🔙 بازگشت', 'callback_data' => 'back:manage']]
        ]
    ]);
}

function getPagedConfigs($filePath, $page, $perPage = 5) {
    $allConfigs = getConfigsFromFile($filePath);
    $totalConfigs = count($allConfigs);
    $totalPages = max(1, ceil($totalConfigs / $perPage));
    $offset = ($page - 1) * $perPage;
    $configs = array_slice($allConfigs, $offset, $perPage);
    return ['configs' => $configs, 'totalPages' => $totalPages, 'currentPage' => $page, 'totalConfigs' => $totalConfigs];
}

function getMainMenu($isAdmin = false) {
    $buttons = [
        [['text' => '📥 دریافت کانفیگ', 'callback_data' => 'receive_config']],
        [['text' => '🎁 اهدای کانفیگ', 'callback_data' => 'donate_config']]
    ];
    if ($isAdmin) {
        $buttons[] = [['text' => '⚙️ مدیریت', 'callback_data' => 'manage']];
    }
    return json_encode(['inline_keyboard' => $buttons]);
}

function getReceiveConfigMenu() {
    return json_encode([
        'inline_keyboard' => [
            [['text' => '🔧 کانفیگ اختصاصی', 'callback_data' => 'custom_configs:1']],
            [['text' => '🎁 کانفیگ اهدایی', 'callback_data' => 'donated_configs:1']],
            [['text' => '🏠 صفحه اصلی', 'callback_data' => 'main_menu']]
        ]
    ]);
}

function getDonateConfigMenuInline($isAdmin = false) {
    $buttons = [
        [['text' => '📤 ارسال کانفیگ', 'callback_data' => 'save_donated']],
        [['text' => '🏠 صفحه اصلی', 'callback_data' => 'main_menu']]
    ];
    return json_encode(['inline_keyboard' => $buttons]);
}

function getManageMenu() {
    return json_encode([
        'inline_keyboard' => [
            [['text' => '📤 ارسال کانفیگ', 'callback_data' => 'save_custom']],
            [['text' => '🗑️ حذف لیست', 'callback_data' => 'admin_clear_list']],
            [['text' => '📊 آمار کلی ربات', 'callback_data' => 'admin_stats']],
            [['text' => '🏠 صفحه اصلی', 'callback_data' => 'main_menu']]
        ]
    ]);
}

function showMainMenu($chatId, $messageId) {
    global $adminId;
    $isAdmin = ((int)$chatId === $adminId);
    $text = "👋 به ربات Win2Ray خوش آمدید!\n\nلطفاً یکی از گزینه‌های زیر را انتخاب کنید:";
    editMessageText($chatId, $messageId, $text, getMainMenu($isAdmin));
}

function showDonateConfigMenu($chatId, $messageId, $isAdmin) {
    $text = "🎁 اهدای کانفیگ\n\n";
    if ($isAdmin) {
        $text .= "مدیر گرامی، می‌توانید کانفیگ اهدایی خود را ارسال کنید.";
    } else {
        $text .= "کاربر گرامی، می‌توانید کانفیگ اهدایی خود را ارسال کنید.";
    }
    editMessageText($chatId, $messageId, $text, getDonateConfigMenuInline($isAdmin));
}

function showReceiveConfigMenu($chatId, $messageId) {
    $text = "📥 دریافت کانفیگ\n\nلطفاً نوع کانفیگ را انتخاب کنید:";
    editMessageText($chatId, $messageId, $text, getReceiveConfigMenu());
}

function showCustomConfigs($chatId, $messageId, $page) {
    global $adminConfigPath;
    $pagedData = getPagedConfigs($adminConfigPath, $page, 5);
    
    $keyboard = [
        [['text' => '🏠 صفحه اصلی', 'callback_data' => 'main_menu']],
        [['text' => '🔙 بازگشت', 'callback_data' => 'back:receive']]
    ];
    
    $text = "🔧 کانفیگ‌های اختصاصی\n\n";
    $text .= "صفحه $page از {$pagedData['totalPages']}\n\n";
    
    if (empty($pagedData['configs'])) {
        $text .= "هیچ کانفیگ اختصاصی یافت نشد!";
    } else {
        $offset = ($page - 1) * 5;
        foreach ($pagedData['configs'] as $localIndex => $config) {
            $globalIndex = $offset + $localIndex;
            $configWithRemark = renameConfigRemark($config);
            $escapedConfig = htmlspecialchars($configWithRemark);
            $text .= "<b>کانفیگ " . ($globalIndex + 1) . ":</b>\n<code>$escapedConfig</code>\n\n";
        }
        
        if ($pagedData['totalConfigs'] > 5) {
            $navButtons = [];
            if ($page > 1) {
                $navButtons[] = ['text' => '◀️ قبلی', 'callback_data' => "custom_configs:" . ($page - 1)];
            }
            if ($page < $pagedData['totalPages']) {
                $navButtons[] = ['text' => 'بعدی ▶️', 'callback_data' => "custom_configs:" . ($page + 1)];
            }
            if (!empty($navButtons)) {
                $keyboard[] = $navButtons;
            }
        }
    }
    
    editMessageText($chatId, $messageId, $text, json_encode(['inline_keyboard' => $keyboard]));
}

function showDonatedConfigs($chatId, $messageId, $page) {
    global $donatedConfigPath;
    $pagedData = getPagedConfigs($donatedConfigPath, $page, 5);
    
    $keyboard = [
        [['text' => '🏠 صفحه اصلی', 'callback_data' => 'main_menu']],
        [['text' => '🔙 بازگشت', 'callback_data' => 'back:receive']]
    ];
    
    $text = "🎁 کانفیگ‌های اهدایی\n\n";
    $text .= "صفحه $page از {$pagedData['totalPages']}\n\n";
    
    if (empty($pagedData['configs'])) {
        $text .= "هیچ کانفیگ اهدایی یافت نشد!";
    } else {
        $offset = ($page - 1) * 5;
        foreach ($pagedData['configs'] as $localIndex => $config) {
            $globalIndex = $offset + $localIndex;
            $configWithRemark = renameConfigRemark($config);
            $escapedConfig = htmlspecialchars($configWithRemark);
            $text .= "<b>کانفیگ " . ($globalIndex + 1) . ":</b>\n<code>$escapedConfig</code>\n\n";
        }
        
        if ($pagedData['totalConfigs'] > 5) {
            $navButtons = [];
            if ($page > 1) {
                $navButtons[] = ['text' => '◀️ قبلی', 'callback_data' => "donated_configs:" . ($page - 1)];
            }
            if ($page < $pagedData['totalPages']) {
                $navButtons[] = ['text' => 'بعدی ▶️', 'callback_data' => "donated_configs:" . ($page + 1)];
            }
            if (!empty($navButtons)) {
                $keyboard[] = $navButtons;
            }
        }
    }
    
    editMessageText($chatId, $messageId, $text, json_encode(['inline_keyboard' => $keyboard]));
}

function showAdminSendConfig($chatId, $messageId) {
    $keyboard = [
        [['text' => '🏠 صفحه اصلی', 'callback_data' => 'main_menu']],
        [['text' => '🔙 بازگشت', 'callback_data' => 'back:manage']]
    ];
    $text = "📤 ارسال کانفیگ اختصاصی\n\nلطفاً کانفیگ خود را ارسال کنید:";
    editMessageText($chatId, $messageId, $text, json_encode(['inline_keyboard' => $keyboard]));
}

function showAdminClearList($chatId, $messageId) {
    $keyboard = json_encode([
        'inline_keyboard' => [
            [['text' => '🗑️ خالی کردن کانفیگ‌های اختصاصی', 'callback_data' => 'clear_custom']],
            [['text' => '🗑️ خالی کردن کانفیگ‌های اهدایی', 'callback_data' => 'clear_donated']],
            [['text' => '🏠 صفحه اصلی', 'callback_data' => 'main_menu']],
            [['text' => '🔙 بازگشت', 'callback_data' => 'back:manage']]
        ]
    ]);
    editMessageText($chatId, $messageId, "🗑️ حذف لیست\n\nکدام لیست را می‌خواهید خالی کنید؟", $keyboard);
}

function showAdminStats($chatId, $messageId) {
    global $adminConfigPath, $donatedConfigPath;
    $customCount = count(getConfigsFromFile($adminConfigPath));
    $donatedCount = count(getConfigsFromFile($donatedConfigPath));
    
    $keyboard = json_encode([
        'inline_keyboard' => [
            [['text' => '🏠 صفحه اصلی', 'callback_data' => 'main_menu']],
            [['text' => '🔙 بازگشت', 'callback_data' => 'back:manage']]
        ]
    ]);
    
    $text = "📊 آمار کلی ربات\n\n📌 کانفیگ‌های اختصاصی: $customCount\n🎁 کانفیگ‌های اهدایی: $donatedCount";
    editMessageText($chatId, $messageId, $text, $keyboard);
}
?>