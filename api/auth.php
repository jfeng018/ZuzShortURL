<?php
require_once 'config.php';

if ($method === 'POST') {
    check_rate_limit($pdo);
}

$csrf_token = generate_csrf_token();
$error = '';
$success = '';

if ($path === '/login') {
    if ($method === 'POST') {
        if (!validate_csrf_token($_POST['csrf'] ?? '')) {
            $error = 'CSRF令牌无效。';
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
                    $_SESSION['username'] = $username; // Store username for display
                    header('Location: /dashboard');
                    exit;
                } else {
                    $error = '用户名或密码错误。';
                }
            }
        }
    }
    // Render login page
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
    <body class="bg-background text-foreground min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full p-6 bg-card rounded-lg border">
            <h2 class="text-2xl font-bold mb-6 text-center">登录</h2>
            <?php if ($error): ?>
                <div class="bg-destructive/10 border border-destructive/30 text-destructive px-4 py-3 rounded-md mb-4"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">用户名</label>
                    <input type="text" name="username" class="w-full px-3 py-2 border border-input rounded-md" required>
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-medium mb-2">密码</label>
                    <input type="password" name="password" class="w-full px-3 py-2 border border-input rounded-md" required>
                </div>
                <button type="submit" class="w-full bg-primary text-primary-foreground py-2 rounded-md hover:bg-primary/90">登录</button>
            </form>
            <p class="mt-4 text-center text-sm">没有账号？<a href="/register" class="text-primary hover:underline">注册</a></p>
        </div>
    </body>
    </html>
    <?php
    exit;
} elseif ($path === '/register') {
    if ($method === 'POST') {
        if (!validate_csrf_token($_POST['csrf'] ?? '')) {
            $error = 'CSRF令牌无效。';
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
    // Render register page
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
    <body class="bg-background text-foreground min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full p-6 bg-card rounded-lg border">
            <h2 class="text-2xl font-bold mb-6 text-center">注册</h2>
            <?php if ($error): ?>
                <div class="bg-destructive/10 border border-destructive/30 text-destructive px-4 py-3 rounded-md mb-4"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="bg-secondary/50 border border-secondary/30 text-secondary-foreground px-4 py-3 rounded-md mb-4"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">用户名</label>
                    <input type="text" name="username" class="w-full px-3 py-2 border border-input rounded-md" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">密码</label>
                    <input type="password" name="password" class="w-full px-3 py-2 border border-input rounded-md" required>
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-medium mb-2">确认密码</label>
                    <input type="password" name="confirm_password" class="w-full px-3 py-2 border border-input rounded-md" required>
                </div>
                <button type="submit" class="w-full bg-primary text-primary-foreground py-2 rounded-md hover:bg-primary/90">注册</button>
            </form>
            <p class="mt-4 text-center text-sm">已有账号？<a href="/login" class="text-primary hover:underline">登录</a></p>
        </div>
    </body>
    </html>
    <?php
    exit;
} elseif ($path === '/logout') {
    session_destroy();
    header('Location: /');
    exit;
}
?>