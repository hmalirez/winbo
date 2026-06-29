<?php
define('BOT_TOKEN', '8933092110:AAE1-gF6Jw0aLfS0SR-aaOxZ6V-kKHECnvE');
define('ADMIN_ID', '5229414557');
define('DATA_DIR', __DIR__ . '/files');
define('CONFIG_FILE', DATA_DIR . '/configs.txt');
define('CONFIG_FREE_FILE', DATA_DIR . '/configs_free.txt');
define('IP_FILE', DATA_DIR . '/ips.txt');
define('IP_FREE_FILE', DATA_DIR . '/ips_free.txt');
define('USER_CONFIG_FILE', DATA_DIR . '/user-configs.txt');
define('GIFT_FILE', DATA_DIR . '/gift.txt');
define('USERS_FILE', DATA_DIR . '/users.txt');
define('STATS_FILE', DATA_DIR . '/stats.txt');
define('GIFT_USAGE_FILE', DATA_DIR . '/gift_usage.txt');

function ensureDataDir() {
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }
}