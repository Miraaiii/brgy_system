<?php

if (!defined('BMS_PASSWORD_RULE_MESSAGE')) {
    define('BMS_PASSWORD_RULE_MESSAGE', 'Password must be at least 8 characters with at least one uppercase letter and one number.');
}

function bms_password_errors($password) {
    $errors = [];

    if (strlen((string)$password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Z]/', (string)$password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    }
    if (!preg_match('/\d/', (string)$password)) {
        $errors[] = 'Password must contain at least one number.';
    }

    return $errors;
}

function bms_password_is_valid($password) {
    return count(bms_password_errors($password)) === 0;
}

function bms_csrf_token($key = 'auth_csrf_token') {
    if (empty($_SESSION[$key])) {
        $_SESSION[$key] = bin2hex(random_bytes(32));
    }

    return $_SESSION[$key];
}

function bms_verify_csrf_token($token, $key = 'auth_csrf_token') {
    return isset($_SESSION[$key])
        && is_string($token)
        && hash_equals($_SESSION[$key], $token);
}

function bms_reset_code_secret() {
    $secret = getenv('RESET_CODE_SECRET');
    if ($secret !== false && $secret !== '') {
        return $secret;
    }

    $appKey = getenv('APP_KEY');
    if ($appKey !== false && $appKey !== '') {
        return $appKey;
    }

    return 'barangay_sta_rosa_1_reset_code_secret';
}

function bms_reset_code_hash($code, $context = '') {
    return hash_hmac('sha256', (string)$context . '|' . (string)$code, bms_reset_code_secret());
}
