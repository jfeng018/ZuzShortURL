<?php
require_once 'config.php';

$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Routing
if ($path === '/' || $path === '') {
    require_once 'home.php';
} elseif ($path === '/create') {
    require_once 'create.php';
} elseif ($path === '/admin') {
    require_once 'admin.php';
} elseif ($path === '/login' || $path === '/register' || $path === '/logout') {
    require_once 'auth.php';
} elseif ($path === '/dashboard') {
    require_once 'user.php';
} elseif (preg_match('/^\/([A-Za-z0-9]{5,10})$/', $path, $matches)) {
    require_once 'redirect.php';
} else {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>404 - 未找到</title><script src="https://cdn.tailwindcss.com"></script></head><body class="bg-background text-foreground min-h-screen flex items-center justify-center"><div class="text-center"><h1 class="text-6xl font-bold text-muted-foreground mb-4">404</h1><p class="text-xl text-muted-foreground mb-6">页面未找到</p><a href="/" class="px-6 py-3 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors">返回首页</a></div></body></html>';
    exit;
}

// Optional GC
$handler->gc(1440);
?>