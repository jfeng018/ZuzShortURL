<?php
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

$clean_path = ltrim($path, '/');
$file_path = __DIR__ . DIRECTORY_SEPARATOR . $clean_path;

if (strpos($path, '.') !== false && file_exists($file_path) && is_file($file_path)) {
    $ext = pathinfo($clean_path, PATHINFO_EXTENSION);
    $mime = match($ext) {
        'css' => 'text/css',
        'js' => 'application/javascript',
        'png', 'jpg', 'jpeg', 'gif' => 'image/' . $ext,
        default => 'application/octet-stream'
    };
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=3600');
    header('ETag: "' . md5_file($file_path) . '"');
    readfile($file_path);
    exit;
}

require_once 'includes/config.php';
require_once 'includes/functions.php';

$method = $_SERVER['REQUEST_METHOD'];

$private_mode = ($settings['private_mode'] ?? 'false') === 'true';
$require_admin = $private_mode && !require_admin_auth();

if ($require_admin && in_array($path, ['/', '/create', '/login', '/register', '/dashboard'])) {
    header('Location: /admin');
    exit;
}

if ($path === '/' || $path === '') {
    // home 逻辑
    $history = [];
    if (isset($_COOKIE['short_history'])) {
        $history = json_decode($_COOKIE['short_history'], true) ?: [];
    }
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Zuz.Asia - 即时缩短链接</title>
        <link rel="stylesheet" href="./includes/styles.css">
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        colors: {
                            border: "hsl(var(--border))",
                            input: "hsl(var(--input))",
                            ring: "hsl(var(--ring))",
                            background: "hsl(var(--background))",
                            foreground: "hsl(var(--foreground))",
                            primary: {
                                DEFAULT: "hsl(var(--primary))",
                                foreground: "hsl(var(--primary-foreground))",
                            },
                            secondary: {
                                DEFAULT: "hsl(var(--secondary))",
                                foreground: "hsl(var(--secondary-foreground))",
                            },
                            destructive: {
                                DEFAULT: "hsl(var(--destructive))",
                                foreground: "hsl(var(--destructive-foreground))",
                            },
                            muted: {
                                DEFAULT: "hsl(var(--muted))",
                                foreground: "hsl(var(--muted-foreground))",
                            },
                            accent: {
                                DEFAULT: "hsl(var(--accent))",
                                foreground: "hsl(var(--accent-foreground))",
                            },
                            popover: {
                                DEFAULT: "hsl(var(--popover))",
                                foreground: "hsl(var(--popover-foreground))",
                            },
                            card: {
                                DEFAULT: "hsl(var(--card))",
                                foreground: "hsl(var(--card-foreground))",
                            },
                        },
                        borderRadius: {
                            lg: "var(--radius)",
                            md: "calc(var(--radius) - 2px)",
                            sm: "calc(var(--radius) - 4px)",
                        },
                    },
                }
            }
        </script>
    </head>
    <body class="bg-background text-foreground min-h-screen">
        <?php include 'includes/header.php'; ?>
        <div class="container mx-auto px-4 py-4 pt-20">
            <section class="hero-section mb-8 bg-card/50 rounded-xl p-4 md:p-8 md:flex md:items-center md:space-x-8">
                <div class="md:w-1/2 mb-4 md:mb-0">
                    <h1 class="text-4xl md:text-6xl font-bold mb-4">Zuz.Asia</h1>
                    <p class="text-lg md:text-xl text-muted-foreground max-w-md">Zuz.Asia是一个免费、开源的短链接服务，旨在为用户提供简单、高效、安全的链接缩短体验。无需注册即可使用；我们的系统基于PostgreSQL数据库，数据安全有保障。加入数千用户，享受无限短链接创建的便利。</p>
                    <div class="space-x-4 mt-6">
                        <?php if (is_logged_in()): ?>
                            <a href="/dashboard" class="inline-flex items-center px-6 py-3 bg-primary hover:bg-primary/90 text-primary-foreground rounded-lg transition-colors font-semibold text-lg">前往控制台</a>
                        <?php else: ?>
                            <a href="/create" class="inline-flex items-center px-6 py-3 bg-primary hover:bg-primary/90 text-primary-foreground rounded-lg transition-colors font-semibold text-lg">免费开始</a>
                        <?php endif; ?>
                        <a href="/api/docs" class="inline-flex items-center px-6 py-3 bg-secondary text-secondary-foreground rounded-lg transition-colors font-semibold text-lg">API文档</a>
                    </div>
                </div>
                <div class="md:w-1/2">
                    <img src="https://cdn.mengze.vip/gh/JanePHPDev/Blog-Static-Resource@main/images/d2fc9d8ee03eb8a8.jpg" alt="UI预览" class="mx-auto max-w-full md:max-w-md rounded-lg shadow-lg">
                </div>
            </section>

            <section class="grid md:grid-cols-3 gap-4 md:gap-8 mb-8 md:mb-16">
                <div class="bg-card rounded-lg border p-4 md:p-8 pricing-card">
                    <h3 class="text-xl md:text-2xl font-bold mb-4">即时缩短</h3>
                    <p class="text-muted-foreground">输入长连接，一键生成短链接，无需等待、立即分享、极速加载</p>
                </div>
                <div class="bg-card rounded-lg border p-4 md:p-8 pricing-card">
                    <h3 class="text-xl md:text-2xl font-bold mb-4">无限使用</h3>
                    <p class="text-muted-foreground">免费计划支持无限量地创建短链接，也可以Fork仓库源码自己搭建本系统。</p>
                </div>
                <div class="bg-card rounded-lg border p-4 md:p-8 pricing-card">
                    <h3 class="text-xl md:text-2xl font-bold mb-4">安全可靠</h3>
                    <p class="text-muted-foreground">基于PostgreSQL数据库加密存储，性能极致优化，安全可靠。</p>
                </div>
            </section>

            <section class="text-center mb-8 md:mb-16">
                <h2 class="text-2xl md:text-3xl font-bold mb-8">选择您的计划</h2>
                <div class="grid md:grid-cols-3 gap-4 md:gap-8 max-w-5xl mx-auto">
                    <div class="bg-card rounded-lg border p-4 md:p-8 pricing-card">
                        <div class="text-center mb-4">
                            <div class="w-12 h-12 mx-auto mb-3 bg-purple-100 rounded-full dark:bg-purple-900/20 flex items-center justify-center">
                                <span class="text-purple-600 text-xl">⭐</span>
                            </div>
                            <h3 class="text-xl md:text-2xl font-bold">Pro</h3>
                        </div>
                        <ul class="space-y-2 text-left mb-6">
                            <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> 免费无限量Pages</li>
                            <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> 自定义域名</li>
                            <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> 新功能体验</li>
                            <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> 高级支持</li>
                        </ul>
                        <div class="border-t border-border pt-4 text-center">
                            <p class="text-xl md:text-2xl font-bold text-green-600 mb-4">$22 / 月</p>
                            <button class="w-full bg-primary text-primary-foreground py-3 px-6 rounded-md transition-colors font-semibold">套餐暂未上线</button>
                        </div>
                    </div>
                    <div class="bg-card rounded-lg border p-4 md:p-8 pricing-card popular">
                        <div class="text-center mb-4">
                            <div class="w-12 h-12 mx-auto mb-3 bg-purple-100 rounded-full dark:bg-purple-900/20 flex items-center justify-center">
                                <span class="text-purple-600 text-xl">👤</span>
                            </div>
                            <h3 class="text-xl md:text-2xl font-bold">注册用户套餐</h3>
                        </div>
                        <ul class="space-y-2 text-left mb-6">
                            <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> 个人链接管理面板</li>
                            <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> 详细访问统计</li>
                            <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> 无限自定义短码</li>
                            <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> 自定义中继页设计</li>
                        </ul>
                        <div class="border-t border-border pt-4 text-center">
                            <p class="text-xl md:text-2xl font-bold text-green-600 mb-4">$0 / 月</p>
                            <button onclick="window.location.href='/register'" class="w-full bg-primary text-primary-foreground py-3 px-6 rounded-md hover:bg-primary/90 transition-colors font-semibold">
  注册使用
</button>
                        </div>
                    </div>
                    <div class="bg-card rounded-lg border p-4 md:p-8 pricing-card">
                        <div class="text-center mb-4">
                            <div class="w-12 h-12 mx-auto mb-3 bg-purple-100 rounded-full dark:bg-purple-900/20 flex items-center justify-center">
                                <span class="text-purple-600 text-xl">⚙️</span>
                            </div>
                            <h3 class="text-xl md:text-2xl font-bold">自建用户套餐</h3>
                        </div>
                        <ul class="space-y-2 text-left mb-6">
                            <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> 完全数据控制权</li>
                            <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> 一键自托管部署</li>
                            <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> 自由扩展功能</li>
                            <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> 100% 开源免费</li>
                        </ul>
                        <div class="border-t border-border pt-4 text-center">
                            <p class="text-xl md:text-2xl font-bold text-green-600 mb-4">$0 / 月</p>
                            <button onclick="window.location.href='https://github.com/JanePHPDev/ZuzShortURL'" class="w-full bg-primary text-primary-foreground py-3 px-6 rounded-md hover:bg-primary/90 transition-colors font-semibold">
  Fork本项目
</button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="text-center mb-8 md:mb-16">
                <h2 class="text-2xl md:text-3xl font-bold mb-8">用户评价</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-8 max-w-5xl mx-auto">
                    <div class="bg-card rounded-lg border p-4 md:p-6">
                        <img src="https://cdn.mengze.vip/gh/JanePHPDev/Blog-Static-Resource@main/images/3974b5accbd063ba.png" alt="用户头像" class="w-12 h-12 rounded-full mx-auto mb-4">
                        <h4 class="font-semibold mb-2">大白萝卜</h4>
                        <p class="text-sm text-muted-foreground">"不错不错，很棒的项目"</p>
                    </div>
                    <div class="bg-card rounded-lg border p-4 md:p-6">
                        <img src="https://cdn.mengze.vip/gh/JanePHPDev/Blog-Static-Resource@main/images/f2f846d91a1c14d8.jpg" alt="用户头像" class="w-12 h-12 rounded-full mx-auto mb-4">
                        <h4 class="font-semibold mb-2">柠枺</h4>
                        <p class="text-sm text-muted-foreground">"很不错的，光看UI不够，中继页设计和账号下管理链接功能都很出色。"</p>
                    </div>
                    <div class="bg-card rounded-lg border p-4 md:p-6">
                        <img src="https://cdn.mengze.vip/gh/JanePHPDev/Blog-Static-Resource@main/images/575821d3f5cfc966.jpg" alt="用户头像" class="w-12 h-12 rounded-full mx-auto mb-4">
                        <h4 class="font-semibold mb-2">一只西西</h4>
                        <p class="text-sm text-muted-foreground">"少见的公益服务，作者是救世主"</p>
                    </div>
                </div>
            </section>

            <section class="grid md:grid-cols-3 gap-4 md:gap-8 mb-8 md:mb-16">
                <div class="bg-card rounded-lg border p-4 md:p-8 text-center pricing-card">
                    <h3 class="text-3xl md:text-4xl font-bold text-primary">10k+</h3>
                    <p class="text-muted-foreground">链接已创建</p>
                </div>
                <div class="bg-card rounded-lg border p-4 md:p-8 text-center pricing-card">
                    <h3 class="text-3xl md:text-4xl font-bold text-primary">99.9%</h3>
                    <p class="text-muted-foreground">正常运行时间</p>
                </div>
                <div class="bg-card rounded-lg border p-4 md:p-8 text-center pricing-card">
                    <h3 class="text-3xl md:text-4xl font-bold text-primary">1.3s</h3>
                    <p class="text-muted-foreground">平均响应时间</p>
                </div>
            </section>

            <section class="text-center mb-8 md:mb-16">
                <h2 class="text-2xl md:text-3xl font-bold mb-4">准备好缩短您的第一个链接了吗？</h2>
                <?php if (is_logged_in()): ?>
                    <a href="/dashboard" class="inline-flex items-center px-8 py-4 bg-primary hover:bg-primary/90 text-primary-foreground rounded-lg transition-colors font-semibold text-lg">前往控制台</a>
                <?php else: ?>
                    <a href="/create" class="inline-flex items-center px-8 py-4 bg-primary hover:bg-primary/90 text-primary-foreground rounded-lg transition-colors font-semibold text-lg">免费开始</a>
                <?php endif; ?>
                <a href="/api/docs" class="inline-flex items-center px-8 py-4 bg-secondary text-secondary-foreground rounded-lg transition-colors font-semibold text-lg ml-4">API文档</a>
            </section>
        </div>
        <?php include 'includes/footer.php'; ?>
    </body>
    </html>
    <?php
    exit;
} elseif ($path === '/create') {
    require 'create.php';
} elseif ($path === '/admin') {
    require 'admin.php';
} elseif ($path === '/login') {
    require 'login.php';
} elseif ($path === '/register') {
    require 'register.php';
} elseif ($path === '/migrate') {
    require 'migrate.php';
} elseif ($path === '/dashboard') {
    require 'dashboard.php';
} elseif ($path === '/logout') {
    logout();
    header('Location: /');
    exit;
} elseif ($path === '/api/docs') {
    require 'api.php';
} elseif ($path === '/api/create' && $method === 'POST') {
    header('Content-Type: application/json');
    check_rate_limit($pdo);
    $input = json_decode(file_get_contents('php://input'), true);
    $longurl = trim($input['url'] ?? '');
    $custom_code = trim($input['custom_code'] ?? '');
    $enable_intermediate = $input['enable_intermediate'] ?? false;
    $redirect_delay = is_numeric($input['redirect_delay']) ? (int)$input['redirect_delay'] : 0;
    $expiration = $input['expiration'] ?? null;
    $response = ['success' => false];
    if (!filter_var($longurl, FILTER_VALIDATE_URL)) {
        $response['error'] = '无效的URL';
        echo json_encode($response);
        exit;
    }
    $code = '';
    if (!empty($custom_code)) {
        $validate = validate_custom_code($custom_code, $pdo, $reserved_codes);
        if ($validate !== true) {
            $response['error'] = $validate;
            echo json_encode($response);
            exit;
        }
        $code = $custom_code;
    }
    if (empty($code)) {
        try {
            $code = generate_random_code($pdo, $reserved_codes);
        } catch (Exception $e) {
            $response['error'] = '生成短码失败';
            echo json_encode($response);
            exit;
        }
    }
    $enable_str = $enable_intermediate ? 'true' : 'false';
    $stmt = $pdo->prepare("INSERT INTO short_links (shortcode, longurl, enable_intermediate_page, redirect_delay, expiration_date) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$code, $longurl, $enable_str, $redirect_delay, $expiration ?: null]);
    $short_url = $base_url . '/' . $code;
    $response['success'] = true;
    $response['short_url'] = $short_url;
    echo json_encode($response);
    exit;
} elseif (preg_match('/^\/([A-Za-z0-9]{5,10})$/', $path, $matches)) {
    $code = $matches[1];
    require 'redirect.php';
} else {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>404 - 未找到</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-background text-foreground min-h-screen flex items-center justify-center">
        <div class="text-center">
            <h1 class="text-6xl font-bold text-muted-foreground mb-4">404</h1>
            <p class="text-xl text-muted-foreground mb-6">页面未找到</p>
            <a href="/" class="px-6 py-3 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors">返回首页</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$handler->gc(1440);
?>