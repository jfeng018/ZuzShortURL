<?php
require_once __DIR__ . '/config.php';

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function validate_captcha($token) {
    global $cf_secret_key; // 定义在底部
    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $data = [
        'secret' => $cf_secret_key,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);
    return $result['success'] ?? false;
}

function check_rate_limit($pdo) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $max_requests = 120;
    $window_seconds = 60;

    $stmt_check_recent = $pdo->prepare("
        SELECT request_count 
        FROM rate_limits 
        WHERE ip = ? 
        AND window_start > NOW() - INTERVAL '1 minute'
    ");
    $stmt_check_recent->execute([$ip]);
    $row_recent = $stmt_check_recent->fetch(PDO::FETCH_ASSOC);

    if ($row_recent && (int)$row_recent['request_count'] >= $max_requests) {
        http_response_code(429);
        die('请求过于频繁，请稍后重试。');
    }

    $stmt_check_any = $pdo->prepare("SELECT 1 FROM rate_limits WHERE ip = ?");
    $stmt_check_any->execute([$ip]);
    $row_any = $stmt_check_any->fetch();

    if ($row_any) {
        if ($row_recent) {
            $stmt = $pdo->prepare("UPDATE rate_limits SET request_count = request_count + 1 WHERE ip = ?");
            $stmt->execute([$ip]);
        } else {
            $stmt = $pdo->prepare("UPDATE rate_limits SET request_count = 1, window_start = NOW() WHERE ip = ?");
            $stmt->execute([$ip]);
        }
    } else {
        $stmt = $pdo->prepare("INSERT INTO rate_limits (ip, request_count, window_start) VALUES (?, 1, NOW())");
        $stmt->execute([$ip]);
    }
}

function generate_random_code($pdo, $reserved_codes) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890';
    $max_attempts = 100;
    $attempts = 0;
    do {
        $code = substr(str_shuffle($chars), 0, 5);
        if (in_array(strtolower($code), $reserved_codes)) continue;
        $stmt = $pdo->prepare("SELECT id FROM short_links WHERE shortcode = ?");
        $stmt->execute([$code]);
        $attempts++;
    } while ($stmt->fetch() && $attempts < $max_attempts);
    if ($attempts >= $max_attempts) {
        throw new Exception('无法生成唯一短码');
    }
    return $code;
}

function validate_custom_code($code, $pdo, $reserved_codes) {
    if (strlen($code) < 5 || strlen($code) > 10) return '短码长度为5-10位';
    if (!preg_match('/^[A-Za-z0-9]+$/', $code)) return '短码仅限字母数字';
    if (in_array(strtolower($code), $reserved_codes)) return '短码被保留';
    $stmt = $pdo->prepare("SELECT id FROM short_links WHERE shortcode = ?");
    $stmt->execute([$code]);
    if ($stmt->fetch()) return '短码已存在';
    return true;
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function get_current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function require_admin_auth() {
    global $admin_token;
    if (!isset($_SESSION['admin_auth']) || !hash_equals($_SESSION['admin_auth'], hash_hmac('sha256', $admin_token, session_id()))) {
        return false;
    }
    return true;
}

function get_setting($pdo, $key) {
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE \"key\" = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['value'] : ($key === 'allow_guest' ? false : true);
}

function set_setting($pdo, $key, $value) {
    $bool_str = $value ? 'true' : 'false';
    $stmt = $pdo->prepare("INSERT INTO settings (\"key\", value) VALUES (?, ?) ON CONFLICT (\"key\") DO UPDATE SET value = ?");
    $stmt->execute([$key, $bool_str, $bool_str]);
}

function logout() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    global $pdo;
    $handler = new DatabaseSessionHandler($pdo);
    $handler->destroy(session_id());
}

$cf_secret_key = '0x4AAAAAAB7QXaEc_7LsZeqHJWHfb7BsDVI';
?>