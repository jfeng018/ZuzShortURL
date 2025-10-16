<?php
require_once 'config.php';

$csrf_token = generate_csrf_token();
$error = '';
$success = '';
$short_url = '';
$user_id = is_logged_in() ? get_current_user_id() : null;

if ($method === 'POST') {
    check_rate_limit($pdo);
    if (!validate_csrf_token($_POST['csrf'] ?? '')) {
        $error = 'CSRF令牌无效。';
    } else {
        $longurl = trim($_POST['url'] ?? '');
        $custom_code = trim($_POST['custom_code'] ?? '');
        $enable_intermediate = isset($_POST['enable_intermediate']);
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
                $stmt = $pdo->prepare("INSERT INTO short_links (shortcode, longurl, user_id, enable_intermediate_page, expiration_date) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$code, $longurl, $user_id, $enable_intermediate ? 'true' : 'false', $expiration ?: null]);
                $short_url = get_base_url() . '/' . $code;
                $success = '短链接创建成功！';
                // Update cookie history
                $history = isset($_COOKIE['short_history']) ? json_decode($_COOKIE['short_history'], true) : [];
                $history[] = ['code' => $code, 'longurl' => $longurl, 'shorturl' => $short_url, 'created_at' => time()];
                $history = array_slice($history, -5); // Keep last 5 entries
                setcookie('short_history', json_encode($history), time() + (30 * 24 * 3600), '/');
            }
        }
    }
}

// Render create page
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
    </style>
</head>
<body class="bg-background text-foreground min-h-screen">
    <nav class="bg-card border-b border-border px-4 py-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold">Zuz.Asia</h1>
            <?php if (is_logged_in()): ?>
                <div class="space-x-4">
                    <span class="text-muted-foreground">欢迎，<?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
                    <a href="/dashboard" class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90">控制台</a>
                    <a href="/logout" class="px-4 py-2 bg-destructive text-destructive-foreground rounded-md hover:bg-destructive/90">登出</a>
                </div>
            <?php else: ?>
                <div class="space-x-4">
                    <a href="/login" class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90">登录</a>
                    <a href="/register" class="px-4 py-2 bg-secondary text-secondary-foreground rounded-md hover:bg-secondary/80">注册</a>
                </div>
            <?php endif; ?>
        </div>
    </nav>
    <div class="container mx-auto p-8">
        <div class="max-w-lg mx-auto bg-card rounded-lg border p-6">
            <h2 class="text-2xl font-bold mb-6 text-center">创建短链接</h2>
            <?php if ($error): ?>
                <div class="bg-destructive/10 border border-destructive/30 text-destructive px-4 py-3 rounded-md mb-4"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="bg-secondary/50 border border-secondary/30 text-secondary-foreground px-4 py-3 rounded-md mb-4"><?php echo htmlspecialchars($success); ?></div>
                <div class="mb-4">
                    <p class="text-sm text-muted-foreground">您的短链接:</p>
                    <a href="<?php echo htmlspecialchars($short_url); ?>" class="text-primary font-mono break-all" target="_blank"><?php echo htmlspecialchars($short_url); ?></a>
                </div>
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
                <div class="mb-4">
                    <label class="flex items-center space-x-2">
                        <input type="checkbox" name="enable_intermediate" class="rounded border-input">
                        <span class="text-sm">启用转跳中继页</span>
                    </label>
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-medium mb-2">过期日期（可选）</label>
                    <input type="date" name="expiration" class="w-full px-3 py-2 border border-input rounded-md">
                </div>
                <button type="submit" class="w-full bg-primary text-primary-foreground py-2 rounded-md hover:bg-primary/90">创建短链接</button>
            </form>
        </div>
        <?php if (isset($_COOKIE['short_history'])): ?>
            <div class="mt-8 max-w-lg mx-auto bg-card rounded-lg border p-6">
                <h3 class="text-lg font-bold mb-4">最近创建的链接</h3>
                <ul class="space-y-2">
                    <?php foreach (json_decode($_COOKIE['short_history'], true) as $item): ?>
                        <li class="flex justify-between items-center text-sm">
                            <div>
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
</body>
</html>
<?php
exit;
?>