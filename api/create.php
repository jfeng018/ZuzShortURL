<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (get_setting($pdo, 'private_mode') === 'true' && !require_admin_auth()) {
    header('Location: /admin');
    exit;
}

if (get_setting($pdo, 'allow_guest') === 'false' && !is_logged_in()) {
    header('Location: /login');
    exit;
}

$reserved_codes = ['admin', 'help', 'about', 'api', 'login', 'register', 'logout', 'dashboard'];
$csrf_token = generate_csrf_token();
$error = '';
$success = '';
$short_url = '';
$code = '';
$user_id = is_logged_in() ? get_current_user_id() : null;
$is_logged_in = is_logged_in();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_rate_limit($pdo);
    if (!validate_csrf_token($_POST['csrf'] ?? '')) {
        $error = 'CSRF令牌无效。';
    } elseif (get_setting($pdo, 'turnstile_enabled') === 'true' && !validate_captcha($_POST['cf-turnstile-response'] ?? '', $pdo)) {
        $error = 'CAPTCHA验证失败。';
    } else {
        $longurl = trim($_POST['url'] ?? '');
        $custom_code = trim($_POST['custom_code'] ?? '');
        $enable_intermediate = isset($_POST['enable_intermediate']) && $is_logged_in;
        $redirect_delay = $is_logged_in && is_numeric($_POST['redirect_delay']) ? (int)$_POST['redirect_delay'] : 0;
        $link_password = $is_logged_in ? trim($_POST['link_password'] ?? '') : '';
        $password_hash = !empty($link_password) ? password_hash($link_password, PASSWORD_DEFAULT) : null;
        $expiration = $_POST['expiration'] ?? null;
        
        if ($expiration && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiration)) {
            $error = '无效的过期日期格式。';
        }
        
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
                $short_url = $short_url . '/' . $code;
                $success = '短链接创建成功！';
                $history = isset($_COOKIE['short_history']) ? json_decode($_COOKIE['short_history'], true) : [];
                $history[] = ['code' => $code, 'longurl' => $longurl, 'shorturl' => $short_url, 'created_at' => time()];
                $history = array_slice($history, -5);
                setcookie('short_history', json_encode($history), [
                    'expires' => time() + (30 * 24 * 3600),
                    'path' => '/',
                    'httponly' => true,
                    'secure' => true,
                    'samesite' => 'Lax'
                ]);
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
    <title>创建短链接 - <?php echo htmlspecialchars(get_setting($pdo, 'site_title') ?? 'Zuz.Asia'); ?></title>
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
    <link rel="stylesheet" href="includes/styles.css">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>
<body class="bg-background text-foreground min-h-screen">
    <?php include 'includes/header.php'; ?>
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
                <?php if (get_setting($pdo, 'turnstile_enabled') === 'true'): ?>
                <div class="cf-turnstile" data-sitekey="<?php echo htmlspecialchars(get_setting($pdo, 'turnstile_site_key')); ?>"></div>
                <?php endif; ?>
                <button type="submit" class="w-full bg-primary text-primary-foreground py-2 rounded-md hover:bg-primary/90 mt-4">创建短链接</button>
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
    <?php include 'includes/footer.php'; ?>
    <script>
        function copyToClipboard(id) {
            const el = document.getElementById(id);
            navigator.clipboard.writeText(el.value).then(() => {
                alert('已复制');
            });
        }
    </script>
</body>
</html>