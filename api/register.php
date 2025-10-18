<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (!get_setting($pdo, 'allow_register')) {
    header('Location: /admin');
    exit;
}

$csrf_token = generate_csrf_token();
$error = '';
$success = '';

if ($method === 'POST') {
    if (!validate_csrf_token($_POST['csrf'] ?? '')) {
        $error = 'CSRF令牌无效。';
    } elseif (!validate_captcha($_POST['cf-turnstile-response'] ?? '')) {
        $error = 'CAPTCHA验证失败。';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        if (empty($username) || empty($password) || empty($confirm_password)) {
            $error = '所有字段均为必填。';
        } elseif ($password !== $confirm_password) {
            $error = '密码不匹配。';
        } elseif (strlen($username) < 3 || strlen($username) > 50) {
            $error = '用户名长度3-50位。';
        } elseif (strlen($password) < 6) {
            $error = '密码至少6位。';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = '用户名已存在。';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                $stmt->execute([$username, $hashed]);
                $success = '注册成功，请登录。';
                header('Location: /login');
                exit;
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
    <title>注册 - Zuz.Asia</title>
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
<body class="bg-background text-foreground min-h-screen flex flex-col">
    <?php include 'includes/header.php'; ?>
    <div class="flex-grow flex items-center justify-center pt-16">
        <div class="max-w-md w-full p-4 bg-card rounded-lg border">
            <h2 class="text-2xl font-bold mb-4 text-center inline-flex items-center justify-center">
                <svg class="h-6 w-6 mr-2 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9V7a2 2 0 00-2-2H8a2 2 0 00-2 2v2m6 4v4m-6-4h12m-6 4v4m0-4h.01" />
                </svg>
                注册
            </h2>
            <?php if ($error): ?>
                <div class="bg-destructive/10 border border-destructive/30 text-destructive px-3 py-2 rounded-md mb-3"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="bg-secondary/50 border border-secondary/30 text-secondary-foreground px-3 py-2 rounded-md mb-3"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="mb-3">
                    <label class="block text-sm font-medium mb-1">用户名</label>
                    <input type="text" name="username" class="w-full px-2 py-1 border border-input rounded-md" required>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium mb-1">密码</label>
                    <input type="password" name="password" class="w-full px-2 py-1 border border-input rounded-md" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">确认密码</label>
                    <input type="password" name="confirm_password" class="w-full px-2 py-1 border border-input rounded-md" required>
                </div>
                <div class="cf-turnstile mb-3" data-sitekey="0x4AAAAAAB7QXdHctr-rc-Yf"></div>
                <button type="submit" class="w-full bg-primary text-primary-foreground py-1 rounded-md hover:bg-primary/90 mt-3 text-sm">注册</button>
            </form>
            <p class="mt-3 text-center text-sm">已有账号？<a href="/login" class="text-primary hover:underline">登录</a></p>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>