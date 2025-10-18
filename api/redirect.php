<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$code = trim($path, '/');
if (empty($code) || in_array($code, $reserved_codes)) {
    header('Location: /');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM short_links WHERE shortcode = ?");
$stmt->execute([$code]);
$link = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$link) {
    header('Location: /');
    exit;
}

if ($link['expiration_date'] && strtotime($link['expiration_date']) < time()) {
    // 显示已过期页面
?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>链接已过期 - Zuz.Asia</title>
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
    </head>
    <body class="bg-background text-foreground min-h-screen flex flex-col">
        <?php include 'includes/header.php'; ?>
        <div class="flex-grow flex items-center justify-center">
            <div class="container mx-auto p-4 text-center">
                <h2 class="text-2xl font-bold mb-4 text-destructive">链接已过期</h2>
                <p class="text-muted-foreground mb-4">此短链接已过期，无法访问。</p>
                <a href="/" class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90">返回首页</a>
            </div>
        </div>
        <?php include 'includes/footer.php'; ?>
    </body>
    </html>
<?php
    exit;
}

if (!empty($link['link_password'])) {
    $input_password = $_POST['password'] ?? '';
    if (empty($input_password)) {
?>
        <!DOCTYPE html>
        <html lang="zh-CN">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>密码保护 - Zuz.Asia</title>
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
        </head>
        <body class="bg-background text-foreground min-h-screen flex flex-col">
            <?php include 'includes/header.php'; ?>
            <div class="flex-grow flex items-center justify-center">
                <div class="max-w-md w-full p-6 bg-card rounded-lg border">
                    <h2 class="text-2xl font-bold mb-6 text-center">密码保护</h2>
                    <form method="post">
                        <input type="hidden" name="code" value="<?php echo htmlspecialchars($code); ?>">
                        <div class="mb-6">
                            <label class="block text-sm font-medium mb-2">请输入密码</label>
                            <input type="password" name="password" class="w-full px-3 py-2 border border-input rounded-md" placeholder="密码" required>
                        </div>
                        <button type="submit" class="w-full bg-primary text-primary-foreground py-2 rounded-md hover:bg-primary/90">验证并跳转</button>
                    </form>
                </div>
            </div>
            <?php include 'includes/footer.php'; ?>
        </body>
        </html>
<?php
        exit;
    } elseif (!password_verify($input_password, $link['link_password'])) {
        http_response_code(403);
        die('密码错误。');
    }
}

$stmt = $pdo->prepare("UPDATE short_links SET clicks = clicks + 1 WHERE shortcode = ?");
$stmt->execute([$code]);

if ($link['enable_intermediate_page']) {
    $delay = (int)$link['redirect_delay'] * 1000;
?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>正在跳转 - Zuz.Asia</title>
        <meta http-equiv="refresh" content="<?php echo $link['redirect_delay']; ?>;url=<?php echo htmlspecialchars($link['longurl']); ?>">
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
    </head>
    <body class="bg-background text-foreground min-h-screen flex flex-col">
        <?php include 'includes/header.php'; ?>
        <div class="flex-grow flex items-center justify-center">
            <div class="container mx-auto p-4">
                <div class="max-w-lg mx-auto bg-card rounded-lg border p-6 text-center">
                    <h2 class="text-2xl font-bold mb-4">正在跳转...</h2>
                    <p class="text-muted-foreground mb-4">您将被重定向到以下链接:</p>
                    <a href="<?php echo htmlspecialchars($link['longurl']); ?>" class="text-primary font-mono break-all hover:underline"><?php echo htmlspecialchars($link['longurl']); ?></a>
                    <p class="text-sm text-muted-foreground mt-4"><?php echo $link['redirect_delay']; ?>秒后自动跳转，或点击上面的链接立即跳转。</p>
                    <div class="mt-6">
                        <a href="<?php echo htmlspecialchars($link['longurl']); ?>" class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90">立即跳转</a>
                    </div>
                </div>
            </div>
        </div>
        <?php include 'includes/footer.php'; ?>
    </body>
    </html>
<?php
    exit;
}

header('Location: ' . $link['longurl']);
exit;
?>