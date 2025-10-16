<?php
require_once 'config.php';

$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if (get_setting($pdo, 'private_mode')) {
    if ($path === '/' || $path === '/create') {
        if (!require_admin_auth()) {
            header('Location: /admin');
            exit;
        }
    }
}

if ($path === '/' || $path === '') {
    require_once 'home.php';
} elseif ($path === '/create') {
    if (!get_setting($pdo, 'allow_guest') && !is_logged_in()) {
        header('Location: /login');
        exit;
    }
    require_once 'create.php';
} elseif ($path === '/admin') {
    require_once 'admin.php';
} elseif ($path === '/login' || $path === '/register' || $path === '/logout') {
    if ($path === '/register' && !get_setting($pdo, 'allow_register')) {
        header('Location: /admin');
        exit;
    }
    require_once 'auth.php';
} elseif ($path === '/dashboard') {
    if (!is_logged_in()) {
        header('Location: /login');
        exit;
    }
    require_once 'user.php';
} elseif ($path === '/api/docs') {
    require_once 'api.php';
} elseif ($path === '/api/create' && $method === 'POST') {
    header('Content-Type: application/json');
    check_rate_limit($pdo);
    $input = json_decode(file_get_contents('php://input'), true);
    $longurl = trim($input['url'] ?? '');
    $custom_code = trim($input['custom_code'] ?? '');
    $enable_intermediate = $input['enable_intermediate'] ?? false;
    $expiration = $input['expiration'] ?? null;
    $user_id = is_logged_in() ? get_current_user_id() : null;
    $response = ['success' => false, 'message' => ''];
    if (!filter_var($longurl, FILTER_VALIDATE_URL)) {
        $response['message'] = '无效的URL。';
        echo json_encode($response);
        exit;
    }
    $code = '';
    if (!empty($custom_code)) {
        $validate = validate_custom_code($custom_code, $pdo, $reserved_codes);
        if ($validate === true) {
            $code = $custom_code;
        } else {
            $response['message'] = $validate;
            echo json_encode($response);
            exit;
        }
    }
    if (empty($code)) {
        try {
            $code = generate_random_code($pdo, $reserved_codes);
        } catch (Exception $e) {
            $response['message'] = '生成短码失败。';
            echo json_encode($response);
            exit;
        }
    }
    $enable_str = $enable_intermediate ? 'true' : 'false';
    $stmt = $pdo->prepare("INSERT INTO short_links (shortcode, longurl, user_id, enable_intermediate_page, expiration_date) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$code, $longurl, $user_id, $enable_str, $expiration ?: null]);
    $short_url = $base_url . '/' . $code;
    $response['success'] = true;
    $response['short_url'] = $short_url;
    echo json_encode($response);
    exit;
} elseif (preg_match('/^\/([A-Za-z0-9]{5,10})$/', $path, $matches)) {
    require_once 'redirect.php';
} else {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>404 - 未找到</title><script src="https://cdn.tailwindcss.com"></script></head><body class="bg-background text-foreground min-h-screen flex items-center justify-center"><div class="text-center"><h1 class="text-6xl font-bold text-muted-foreground mb-4">404</h1><p class="text-xl text-muted-foreground mb-6">页面未找到</p><a href="/" class="px-6 py-3 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors">返回首页</a></div></body></html>';
    exit;
}

$handler->gc(1440);
?>