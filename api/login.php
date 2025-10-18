<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$csrf_token = generate_csrf_token();
$error = '';
if ($method === 'POST') {
    if (!validate_csrf_token($_POST['csrf'] ?? '')) {
        $error = 'CSRF令牌无效。';
    } elseif (!validate_captcha($_POST['cf-turnstile-response'] ?? '')) {
        $error = 'CAPTCHA验证失败。';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if (empty($username) || empty($password)) {
            $error = '用户名或密码为空。';
        } else {
            $stmt = $pdo->prepare("SELECT id, password FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $username;
                header('Location: /dashboard');
                exit;
            } else {
                $error = '用户名或密码错误。';
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
    <title>登录 - Zuz.Asia</title>
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
    <div class="flex-grow flex items-center justify-center pt-16"> <!-- 添加 pt-16 以补偿 Turnstile 可能的影响 -->
        <div class="max-w-md w-full p-4 bg-card rounded-lg border"> <!-- p-6 -> p-4 -->
            <h2 class="text-2xl font-bold mb-4 text-center inline-flex items-center justify-center"> <!-- mb-6 -> mb-4 -->
                <svg class="h-6 w-6 mr-2 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                </svg>
                登录
            </h2>
            <?php if ($error): ?>
                <div class="bg-destructive/10 border border-destructive/30 text-destructive px-3 py-2 rounded-md mb-3"><?php echo htmlspecialchars($error); ?></div> <!-- px-4 py-3 mb-4 -> px-3 py-2 mb-3 -->
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="mb-3"> <!-- mb-4 -> mb-3 -->
                    <label class="block text-sm font-medium mb-1">用户名</label> <!-- mb-2 -> mb-1 -->
                    <input type="text" name="username" class="w-full px-2 py-1 border border-input rounded-md" required> <!-- px-3 py-2 -> px-2 py-1 -->
                </div>
                <div class="mb-4"> <!-- mb-6 -> mb-4 -->
                    <label class="block text-sm font-medium mb-1">密码</label>
                    <input type="password" name="password" class="w-full px-2 py-1 border border-input rounded-md" required>
                </div>
                <div class="cf-turnstile mb-3" data-sitekey="0x4AAAAAAB7QXdHctr-rc-Yf"></div> <!-- 添加 mb-3 -->
                <button type="submit" class="w-full bg-primary text-primary-foreground py-2 rounded-md hover:bg-primary/90 mt-3 text-sm">登录</button> <!-- py-2 mt-4 -> py-1 mt-3 -->
            </form>
            <?php if (get_setting($pdo, 'allow_register')): ?>
                <p class="mt-3 text-center text-sm">没有账号？<a href="/register" class="text-primary hover:underline">注册</a></p> <!-- mt-4 -> mt-3 -->
            <?php endif; ?>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>