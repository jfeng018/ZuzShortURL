<?php
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($requestUri, PHP_URL_PATH);
if ($path === '/migrate') {
    require __DIR__ . '/migrate.php';
    exit;
}

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

            <!-- 新增图文混排板块 -->
            <section class="mb-8 md:mb-16 bg-card rounded-xl overflow-hidden">
                <div class="grid md:grid-cols-2 gap-0">
                    <div class="p-8 md:p-12 flex flex-col justify-center">
                        <h2 class="text-3xl md:text-4xl font-bold mb-6">为什么选择 Zuz.Asia？</h2>
                        <div class="space-y-6">
                            <div class="flex items-start space-x-4">
                                <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-xl font-semibold mb-2">极速响应</h3>
                                    <p class="text-muted-foreground">全球 CDN 加速，平均响应时间仅需 1.3 秒，让您的链接跳转快如闪电</p>
                                </div>
                            </div>
                            <div class="flex items-start space-x-4">
                                <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-xl font-semibold mb-2">安全可靠</h3>
                                    <p class="text-muted-foreground">企业级安全防护，99.9% 正常运行时间保障，让您的链接永不下线</p>
                                </div>
                            </div>
                            <div class="flex items-start space-x-4">
                                <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-xl font-semibold mb-2">完全免费</h3>
                                    <p class="text-muted-foreground">无隐藏费用，无功能限制，真正永久免费的短链接服务</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="relative h-64 md:h-auto">
                        <img src="https://cdn.mengze.vip/gh/JanePHPDev/Blog-Static-Resource@main/images/b6ff251d07560b6a.jpeg" alt="技术优势" class="absolute inset-0 w-full h-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent md:hidden"></div>
                    </div>
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
            
                        <!-- CEO发言板块 -->
            <section class="mb-8 md:mb-16 bg-gradient-to-r from-purple-600 to-blue-600 rounded-xl p-8 md:p-12 text-white relative overflow-hidden">
                <div class="absolute inset-0 opacity-10">
                    <svg width="100%" height="100%" viewBox="0 0 100 100" preserveAspectRatio="none">
                        <path d="M0,0 L100,100 L100,0 Z" fill="white"></path>
                        <path d="M0,100 L100,0 L0,0 Z" fill="white"></path>
                    </svg>
                </div>
                <div class="relative z-10">
                    <div class="flex items-center mb-6">
                        <img src="https://cdn.mengze.vip/gh/JanePHPDev/Blog-Static-Resource@main/images/98625d409de6929e.jpg" alt="Vercel CEO" class="w-16 h-16 rounded-full mr-4 border-2 border-white/30">
                        <div>
                            <h3 class="text-xl font-bold">JanePHPDev</h3>
                            <p class="text-purple-100">Zuz.Asia CEO ＆ 开发者</p>
                        </div>
                    </div>
                    <blockquote class="text-xl md:text-2xl font-light mb-6 italic">
                        "Zuz.Asia 展现了现代 Web 开发的精髓——简洁、高效、用户至上。这个项目完美诠释了如何用最新的技术栈打造出真正解决用户痛点的工具。"
                    </blockquote>
                    <div class="flex items-center space-x-6 text-sm">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                            </svg>
                            推荐使用
                        </div>
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            技术领先
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
