<?php
require_once 'config.php';

if (get_setting($pdo, 'private_mode') && !require_admin_auth()) {
    header('Location: /admin');
    exit;
}

if (!get_setting($pdo, 'allow_guest') && !is_logged_in()) {
    header('Location: /login');
    exit;
}

$csrf_token = generate_csrf_token();
$error = '';
$success = '';
$short_url = '';
$code = '';
$user_id = is_logged_in() ? get_current_user_id() : null;
$is_logged_in = is_logged_in();

if ($method === 'POST') {
    check_rate_limit($pdo);
    if (!validate_csrf_token($_POST['csrf'] ?? '')) {
        $error = 'CSRF令牌无效。';
    } else {
        $longurl = trim($_POST['url'] ?? '');
        $custom_code = trim($_POST['custom_code'] ?? '');
        $enable_intermediate = isset($_POST['enable_intermediate']) && $is_logged_in;
        $redirect_delay = $is_logged_in && is_numeric($_POST['redirect_delay']) ? (int)$_POST['redirect_delay'] : 0;
        $link_password = $is_logged_in ? trim($_POST['link_password'] ?? '') : '';
        $password_hash = !empty($link_password) ? password_hash($link_password, PASSWORD_DEFAULT) : null;
        $expiration = $_POST['expiration'] ?? null;
        if (!filter_var($longurl, FILTER_VALIDATE_URL)) {
            $error = '无效的URL。';
        } else {
            $code = '';
            if (!empty($custom_code)) {
                $validate = validate_custom_code($custom_code, $pdo, $reserved_codes);
                if ($validate === true) {
                    $code = $custom_code;
                } else {
                    $error = $validate;
                }
            }
            if (empty($code)) {
                try {
                    $code = generate_random_code($pdo, $reserved_codes);
                } catch (Exception $e) {
                    $error = '生成短码失败。';
                }
            }
            if (empty($error)) {
                $enable_str = $enable_intermediate ? 'true' : 'false';
                $stmt = $pdo->prepare("INSERT INTO short_links (shortcode, longurl, user_id, enable_intermediate_page, redirect_delay, link_password, expiration_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$code, $longurl, $user_id, $enable_str, $redirect_delay, $password_hash, $expiration ?: null]);
                $short_url = $base_url . '/' . $code;
                $success = '短链接创建成功！';
                $history = isset($_COOKIE['short_history']) ? json_decode($_COOKIE['short_history'], true) : [];
                $history[] = ['code' => $code, 'longurl' => $longurl, 'shorturl' => $short_url, 'created_at' => time()];
                $history = array_slice($history, -5);
                setcookie('short_history', json_encode($history), time() + (30 * 24 * 3600), '/');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>创建短链接 - Zuz.Asia</title>
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
    <style>
        :root {
            --background: 0 0% 100%;
            --foreground: 222.2 84% 4.9%;
            --card: 0 0% 100%;
            --card-foreground: 222.2 84% 4.9%;
            --popover: 0 0% 100%;
            --popover-foreground: 222.2 84% 4.9%;
            --primary: 222.2 47.4% 11.2%;
            --primary-foreground: 210 40% 98%;
            --secondary: 210 40% 96%;
            --secondary-foreground: 222.2 47.4% 11.2%;
            --muted: 210 40% 96%;
            --muted-foreground: 215.4 16.3% 46.9%;
            --accent: 210 40% 96%;
            --accent-foreground: 222.2 47.4% 11.2%;
            --destructive: 0 84.2% 60.2%;
            --destructive-foreground: 210 40% 98%;
            --border: 214.3 31.8% 91.4%;
            --input: 214.3 31.8% 91.4%;
            --ring: 222.2 84% 4.9%;
            --radius: 0.5rem;
        }

        .dark {
            --background: 222.2 84% 4.9%;
            --foreground: 210 40% 98%;
            --card: 222.2 84% 4.9%;
            --card-foreground: 210 40% 98%;
            --popover: 222.2 84% 4.9%;
            --popover-foreground: 210 40% 98%;
            --primary: 210 40% 98%;
            --primary-foreground: 222.2 47.4% 11.2%;
            --secondary: 217.2 32.6% 17.5%;
            --secondary-foreground: 210 40% 98%;
            --muted: 217.2 32.6% 17.5%;
            --muted-foreground: 215 20.2% 65.1%;
            --accent: 217.2 32.6% 17.5%;
            --accent-foreground: 210 40% 98%;
            --destructive: 0 62.8% 30.6%;
            --destructive-foreground: 210 40% 98%;
            --border: 217.2 32.6% 17.5%;
            --input: 217.2 32.6% 17.5%;
            --ring: 212.7 26.8% 83.9%;
        }

        body {
            font-size: 0.875rem;
        }

        body {
            background-color: hsl(var(--background));
            background-image: 
                repeating-linear-gradient(90deg, transparent, transparent 19px, hsl(var(--muted-foreground)/0.1) 20px, hsl(var(--muted-foreground)/0.1) 21px),
                repeating-linear-gradient(0deg, transparent, transparent 19px, hsl(var(--muted-foreground)/0.1) 20px, hsl(var(--muted-foreground)/0.1) 21px);
            background-size: 20px 20px;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: inherit;
            z-index: -1;
            backdrop-filter: blur(1px);
            pointer-events: none;
        }

        .bg-card {
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            background: hsl(var(--card)/0.8);
            border: 1px solid hsl(var(--border)/0.5);
        }

        .dark .bg-card {
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            background: hsl(var(--card)/0.6);
            border: 1px solid hsl(var(--border)/0.5);
        }

        .container {
            position: relative;
            z-index: 1;
        }

        .bg-card {
            box-shadow: 0 4px 6px -1px hsl(var(--ring)/0.1), 0 2px 4px -1px hsl(var(--ring)/0.06);
        }

        .dark .bg-card {
            box-shadow: 0 4px 6px -1px hsl(var(--ring)/0.2), 0 2px 4px -1px hsl(var(--ring)/0.1);
        }

        .pricing-card {
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
        }

        .hero-section {
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
        }

        .mobile-menu {
            display: none;
            z-index: 50;
        }

        @media (max-width: 768px) {
            .desktop-menu {
                display: none;
            }
            .mobile-menu {
                display: block;
            }
        }

        .mobile-menu {
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 40px;
            height: 20px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 20px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 2px;
            bottom: 2px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #2196F3;
        }

        input:checked + .slider:before {
            transform: translateX(20px);
        }
    </style>
</head>
<body class="bg-background text-foreground min-h-screen">
    <nav class="bg-card border-b border-border px-4 py-4 fixed top-0 w-full z-40">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold">Zuz.Asia</h1>
            <button onclick="toggleMobileMenu()" class="md:hidden px-4 py-2 bg-primary text-primary-foreground rounded-md">菜单</button>
            <div class="hidden md:flex space-x-4 desktop-menu">
                <?php if (is_logged_in()): ?>
                    <span class="text-muted-foreground">欢迎，<?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
                    <a href="/dashboard" class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90">控制台</a>
                    <a href="/logout" class="px-4 py-2 bg-destructive text-destructive-foreground rounded-md hover:bg-destructive/90">登出</a>
                <?php else: ?>
                    <a href="/login" class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90">登录</a>
                    <a href="/register" class="px-4 py-2 bg-secondary text-secondary-foreground rounded-md hover:bg-secondary/80">注册</a>
                <?php endif; ?>
                <a href="/api/docs" class="px-4 py-2 bg-secondary text-secondary-foreground rounded-md hover:bg-secondary/80">API文档</a>
            </div>
            <div id="mobileMenu" class="hidden absolute top-16 right-4 md:hidden bg-card rounded-lg border p-4 space-y-2 mobile-menu">
                <?php if (is_logged_in()): ?>
                    <span class="text-muted-foreground block">欢迎，<?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
                    <a href="/dashboard" class="block px-4 py-2 bg-primary text-primary-foreground rounded-md">控制台</a>
                    <a href="/logout" class="block px-4 py-2 bg-destructive text-destructive-foreground rounded-md">登出</a>
                <?php else: ?>
                    <a href="/login" class="block px-4 py-2 bg-primary text-primary-foreground rounded-md">登录</a>
                    <a href="/register" class="block px-4 py-2 bg-secondary text-secondary-foreground rounded-md">注册</a>
                <?php endif; ?>
                <a href="/api/docs" class="block px-4 py-2 bg-secondary text-secondary-foreground rounded-md">API文档</a>
            </div>
        </div>
    </nav>
    <div class="container mx-auto p-4 pt-20">
        <div class="max-w-lg mx-auto bg-card rounded-lg border p-6">
            <h2 class="text-2xl font-bold mb-6 text-center">创建短链接</h2>
            <?php if ($error): ?>
                <div class="bg-destructive/10 border border-destructive/30 text-destructive px-4 py-3 rounded-md mb-4"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">长链接</label>
                    <input type="url" name="url" class="w-full px-3 py-2 border border-input rounded-md" placeholder="https://example.com" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">自定义短码（可选）</label>
                    <input type="text" name="custom_code" class="w-full px-3 py-2 border border-input rounded-md" placeholder="自定义短码" maxlength="10">
                </div>
                <?php if ($is_logged_in): ?>
                <div class="mb-4">
                    <div class="flex items-center justify-between">
                        <label class="text-sm font-medium">启用转跳中继页</label>
                        <label class="switch">
                            <input type="checkbox" name="enable_intermediate">
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">转跳延迟（秒，可选）</label>
                    <input type="number" name="redirect_delay" class="w-full px-3 py-2 border border-input rounded-md" min="0" value="0">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">链接密码（可选）</label>
                    <input type="password" name="link_password" class="w-full px-3 py-2 border border-input rounded-md" placeholder="设置密码以加密链接">
                </div>
                <?php endif; ?>
                <div class="mb-6">
                    <label class="block text-sm font-medium mb-2">过期日期（可选）</label>
                    <input type="date" name="expiration" class="w-full px-3 py-2 border border-input rounded-md">
                </div>
                <button type="submit" class="w-full bg-primary text-primary-foreground py-2 rounded-md hover:bg-primary/90">创建短链接</button>
            </form>
            <?php if ($success): ?>
                <div class="mt-6 bg-secondary/50 border border-secondary/30 rounded-md p-4">
                    <p class="text-sm text-muted-foreground mb-2">您的短链接:</p>
                    <div class="flex items-center space-x-2">
                        <input type="text" id="shortUrl" value="<?php echo htmlspecialchars($short_url); ?>" readonly class="flex-1 px-3 py-2 border border-input rounded-md bg-background">
                        <button onclick="copyToClipboard('shortUrl')" class="px-2 py-2 bg-secondary text-secondary-foreground rounded">复制</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php if (isset($_COOKIE['short_history'])): ?>
            <div class="mt-6 max-w-lg mx-auto bg-card rounded-lg border p-6">
                <h3 class="text-lg font-bold mb-4">最近创建的链接</h3>
                <ul class="space-y-2">
                    <?php $history = json_decode($_COOKIE['short_history'], true); foreach ($history as $item): ?>
                        <li class="flex justify-between items-center text-sm">
                            <div class="flex-1">
                                <a href="<?php echo htmlspecialchars($item['shorturl']); ?>" class="text-primary font-mono" target="_blank"><?php echo htmlspecialchars($item['shorturl']); ?></a>
                                <p class="text-muted-foreground truncate" title="<?php echo htmlspecialchars($item['longurl']); ?>"><?php echo htmlspecialchars($item['longurl']); ?></p>
                            </div>
                            <span class="text-muted-foreground text-xs"><?php echo date('Y-m-d', $item['created_at']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
    <footer class="mt-12 pt-8 border-t border-border text-center text-sm text-muted-foreground">
        <p>&copy; 2025 Zuz.Asia. All rights reserved. | <a href="https://github.com/JanePHPDev/ZuzShortURL" target="_blank" class="text-primary hover:underline">GitHub</a></p>
    </footer>
    <script>
        function toggleMobileMenu() {
            document.getElementById('mobileMenu').classList.toggle('hidden');
        }

        function copyToClipboard(id) {
            const el = document.getElementById(id);
            navigator.clipboard.writeText(el.value).then(() => {
                alert('已复制');
            });
        }
    </script>
</body>
</html>
<?php
exit;
?>