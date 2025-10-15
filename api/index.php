<?php
// Parse environment variables
$db_url = parse_url(getenv('DATABASE_URL'));
$host = $db_url['host'];
$port = $db_url['port'] ?? 5432;
$dbname = ltrim($db_url['path'], '/');
$user = $db_url['user'];
$pass = $db_url['pass'];
$admin_token = getenv('ADMIN_TOKEN');

// Database connection
try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Create table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS short_links (
            id SERIAL PRIMARY KEY,
            shortcode VARCHAR(10) UNIQUE NOT NULL,
            longurl TEXT NOT NULL,
            clicks INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    // Reset sequence with is_called=false to handle empty table
    $pdo->exec("SELECT setval('short_links_id_seq', COALESCE((SELECT MAX(id) FROM short_links), 1), false);");
} catch (PDOException $e) {
    http_response_code(500);
    die('数据库连接失败: ' . $e->getMessage());
}

// Reserved shortcodes
$reserved_codes = ['admin', 'help', 'about', 'api'];

// Generate random 5-char from specified charset
function generate_random_code() {
    global $pdo, $reserved_codes;
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890';
    do {
        $code = substr(str_shuffle($chars), 0, 5);
        if (in_array(strtolower($code), $reserved_codes)) continue;
        $stmt = $pdo->prepare("SELECT id FROM short_links WHERE shortcode = ?");
        $stmt->execute([$code]);
    } while ($stmt->fetch());
    return $code;
}

// Validate custom code
function validate_custom_code($code) {
    global $pdo, $reserved_codes;
    if (strlen($code) < 5) return '短码至少5位';
    if (!preg_match('/^[A-Za-z0-9]+$/', $code)) return '短码仅限字母数字';
    if (in_array(strtolower($code), $reserved_codes)) return '短码被保留';
    $stmt = $pdo->prepare("SELECT id FROM short_links WHERE shortcode = ?");
    $stmt->execute([$code]);
    if ($stmt->fetch()) return '短码已存在';
    return true;
}

// Routing
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$domain = $_SERVER['HTTP_HOST'];
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base_url = $protocol . '://' . $domain;

// Admin panel (moved BEFORE shortcode to avoid hijack)
if ($path === '/admin') {
    $error = '';
    $success = '';
    $links = [];
    $show_list = false;
    $input_token = $_POST['token'] ?? '';
    $valid_token = ($input_token === $admin_token);

    if ($method === 'POST') {
        if (!$valid_token) {
            $error = '无效的管理令牌。';
        } else {
            $action = $_POST['action'] ?? '';
            switch ($action) {
                case 'add':
                    $longurl = trim($_POST['url'] ?? '');
                    $custom_code = trim($_POST['custom_code'] ?? '');
                    if (!filter_var($longurl, FILTER_VALIDATE_URL)) {
                        $error = '无效的URL。';
                    } else {
                        $code = '';
                        if (!empty($custom_code)) {
                            $validate = validate_custom_code($custom_code);
                            if ($validate === true) {
                                $code = $custom_code;
                            } else {
                                $error = $validate;
                            }
                        }
                        if (empty($code)) {
                            $code = generate_random_code();
                        }
                        if (empty($error)) {
                            $stmt = $pdo->prepare("INSERT INTO short_links (shortcode, longurl, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)");
                            $stmt->execute([$code, $longurl]);
                            $success = '链接添加成功。';
                        }
                    }
                    break;
                case 'edit':
                    $code = $_POST['code'] ?? '';
                    $newurl = trim($_POST['newurl'] ?? '');
                    if (!filter_var($newurl, FILTER_VALIDATE_URL)) {
                        $error = '无效的新URL。';
                    } else {
                        $stmt = $pdo->prepare("UPDATE short_links SET longurl = ? WHERE shortcode = ?");
                        $stmt->execute([$newurl, $code]);
                        $success = '链接更新成功。';
                    }
                    break;
                case 'delete':
                    $code = $_POST['code'] ?? '';
                    $stmt = $pdo->prepare("DELETE FROM short_links WHERE shortcode = ?");
                    $stmt->execute([$code]);
                    $success = '链接删除成功。';
                    break;
                case 'login':
                case 'list':
                    $show_list = true;
                    break;
            }
            // Refresh list after action
            if ($valid_token) {
                $show_list = true;
            }
        }
    }

    if ($show_list && $valid_token) {
        $stmt = $pdo->query("SELECT * FROM short_links ORDER BY created_at DESC");
        $links = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Render admin page
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>管理面板 - Zuz.Asia</title>
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
                },
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
        </style>
    </head>
    <body class="bg-background text-foreground min-h-screen">
        <div class="container mx-auto px-4 py-8">
            <h1 class="text-3xl font-bold text-center mb-8">管理面板</h1>
            <?php if (!$show_list): ?>
                <div class="max-w-md mx-auto">
                    <div class="bg-card rounded-lg border p-6">
                        <h2 class="text-xl font-semibold mb-4">输入管理令牌</h2>
                        <?php if ($error): ?>
                            <div class="bg-destructive/10 border border-destructive/30 text-destructive px-4 py-3 rounded-md mb-4"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <form method="post">
                            <input type="hidden" name="action" value="login">
                            <div class="mb-4">
                                <input type="password" name="token" class="w-full px-3 py-2 border border-input rounded-md focus:ring-2 focus:ring-ring" placeholder="管理令牌" required value="<?php echo htmlspecialchars($input_token); ?>">
                            </div>
                            <button type="submit" class="w-full bg-primary text-primary-foreground py-2 px-4 rounded-md hover:bg-primary/90 transition-colors">访问面板</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="max-w-md mx-auto mb-6">
                        <div class="bg-destructive/10 border border-destructive/30 text-destructive px-4 py-3 rounded-md"><?php echo htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="max-w-md mx-auto mb-6">
                        <div class="bg-secondary/50 border border-secondary/30 text-secondary-foreground px-4 py-3 rounded-md"><?php echo htmlspecialchars($success); ?></div>
                    </div>
                <?php endif; ?>
                <!-- Add New Link -->
                <div class="max-w-md mx-auto mb-8">
                    <div class="bg-card rounded-lg border p-6">
                        <h3 class="text-lg font-semibold mb-4">添加新短链接</h3>
                        <form method="post">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($input_token); ?>">
                            <div class="space-y-3">
                                <input type="url" name="url" class="w-full px-3 py-2 border border-input rounded-md focus:ring-2 focus:ring-ring" placeholder="https://example.com" required>
                                <input type="text" name="custom_code" class="w-full px-3 py-2 border border-input rounded-md focus:ring-2 focus:ring-ring" placeholder="自定义短码（可选）">
                                <button type="submit" class="w-full bg-primary text-primary-foreground py-2 px-4 rounded-md hover:bg-primary/90 transition-colors">添加</button>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- List Links as Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($links as $link): ?>
                        <div class="bg-card rounded-lg border p-6">
                            <div class="font-mono text-primary text-lg font-semibold mb-2"><?php echo htmlspecialchars($link['shortcode']); ?></div>
                            <p class="text-muted-foreground text-sm mb-4 truncate max-w-full" title="<?php echo htmlspecialchars($link['longurl']); ?>"><?php echo htmlspecialchars($link['longurl']); ?></p>
                            <div class="space-y-2 text-xs text-muted-foreground mb-4">
                                <p>点击: <?php echo $link['clicks']; ?></p>
                                <p>创建: <?php echo date('Y-m-d H:i', strtotime($link['created_at'])); ?></p>
                            </div>
                            <div class="space-y-2">
                                <!-- Edit Form -->
                                <form method="post" class="flex gap-2">
                                    <input type="hidden" name="action" value="edit">
                                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($input_token); ?>">
                                    <input type="hidden" name="code" value="<?php echo htmlspecialchars($link['shortcode']); ?>">
                                    <input type="url" name="newurl" class="flex-1 px-2 py-1 border border-input rounded text-xs" value="<?php echo htmlspecialchars($link['longurl']); ?>" required>
                                    <button type="submit" class="px-3 py-1 bg-primary text-primary-foreground rounded text-xs hover:bg-primary/90">编辑</button>
                                </form>
                                <!-- Delete Form -->
                                <form method="post" class="flex gap-2" onsubmit="return confirm('删除此链接？');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($input_token); ?>">
                                    <input type="hidden" name="code" value="<?php echo htmlspecialchars($link['shortcode']); ?>">
                                    <button type="submit" class="w-full px-3 py-1 bg-destructive text-destructive-foreground rounded text-xs hover:bg-destructive/90">删除</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($links)): ?>
                        <div class="col-span-full text-center py-12 text-muted-foreground">暂无链接。</div>
                    <?php endif; ?>
                </div>
                <div class="text-center mt-8 space-x-4">
                    <form method="post" class="inline">
                        <input type="hidden" name="action" value="list">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($input_token); ?>">
                        <button type="submit" class="px-6 py-3 bg-secondary hover:bg-secondary/80 text-secondary-foreground rounded-md transition-colors">刷新</button>
                    </form>
                    <a href="/" class="px-6 py-3 bg-primary hover:bg-primary/90 text-primary-foreground rounded-md transition-colors">返回首页</a>
                </div>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Handle short URL redirection (exclude reserved)
elseif (preg_match('/^\/([A-Za-z0-9]{5})$/', $path, $matches)) {
    $code = $matches[1];
    // Skip if reserved (extra safety)
    if (in_array(strtolower($code), $reserved_codes)) {
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
                <p class="text-xl text-muted-foreground mb-6">无效路径</p>
                <a href="/" class="px-6 py-3 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors">返回首页</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    $stmt = $pdo->prepare("SELECT longurl FROM short_links WHERE shortcode = ?");
    $stmt->execute([$code]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $longurl = $row['longurl'];
        // Increment clicks
        $stmt = $pdo->prepare("UPDATE short_links SET clicks = clicks + 1 WHERE shortcode = ?");
        $stmt->execute([$code]);
        header('Location: ' . $longurl, true, 301);
        exit;
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
                <p class="text-xl text-muted-foreground mb-6">短链接不存在</p>
                <a href="/" class="px-6 py-3 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors">返回首页</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Home / Create short URL
elseif ($path === '/' || $path === '') {
    $error = '';
    $success = '';
    $short_url = '';
    if ($method === 'POST') {
        $longurl = trim($_POST['url'] ?? '');
        $custom_code = trim($_POST['custom_code'] ?? '');
        if (!filter_var($longurl, FILTER_VALIDATE_URL)) {
            $error = '无效的URL，请输入有效链接。';
        } else {
            $code = '';
            if (!empty($custom_code)) {
                $validate = validate_custom_code($custom_code);
                if ($validate === true) {
                    $code = $custom_code;
                } else {
                    $error = $validate;
                }
            }
            if (empty($code)) {
                $code = generate_random_code();
            }
            if (empty($error)) {
                // Insert without specifying id (let SERIAL handle)
                $stmt = $pdo->prepare("INSERT INTO short_links (shortcode, longurl, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)");
                $stmt->execute([$code, $longurl]);
                $short_url = $base_url . '/' . $code;
                $success = '短链接创建成功！';
            }
        }
    }
    // Render home page
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Zuz.Asia - 即时缩短链接</title>
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
                },
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
        </style>
    </head>
    <body class="bg-background text-foreground min-h-screen">
        <div class="container mx-auto px-4 py-8">
            <div class="text-center mb-12">
                <h1 class="text-5xl md:text-6xl font-bold mb-4">Zuz.Asia</h1>
                <p class="text-xl text-muted-foreground">无需注册，即时创建短链接。简单、高效、安全。</p>
            </div>
            <div class="max-w-md mx-auto">
                <div class="bg-card rounded-lg border p-6 md:p-8">
                    <?php if ($error): ?>
                        <div class="bg-destructive/10 border border-destructive/30 text-destructive px-4 py-3 rounded-md mb-4"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="bg-secondary/50 border border-secondary/30 text-secondary-foreground px-4 py-3 rounded-md mb-4"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                    <form method="post" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">原始链接</label>
                            <input type="url" name="url" class="w-full px-3 py-2 border border-input rounded-md focus:ring-2 focus:ring-ring focus:border-transparent" placeholder="https://example.com" required value="<?php echo htmlspecialchars($_POST['url'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2">自定义短码（可选）</label>
                            <input type="text" name="custom_code" class="w-full px-3 py-2 border border-input rounded-md focus:ring-2 focus:ring-ring focus:border-transparent" placeholder="至少5位字母数字" value="<?php echo htmlspecialchars($_POST['custom_code'] ?? ''); ?>" maxlength="10">
                            <p class="text-xs text-muted-foreground mt-1">留空自动生成5位随机码。避免使用 'admin' 等保留词。</p>
                        </div>
                        <button type="submit" class="w-full bg-primary text-primary-foreground py-2 px-4 rounded-md hover:bg-primary/90 transition-colors font-medium">缩短链接</button>
                    </form>
                </div>
                <?php if ($short_url): ?>
                    <div class="mt-6 bg-card rounded-lg border p-6">
                        <h3 class="text-lg font-semibold mb-2">您的短链接：</h3>
                        <a href="<?php echo htmlspecialchars($short_url); ?>" target="_blank" class="text-primary hover:text-primary/80 font-mono text-sm break-all"><?php echo htmlspecialchars($short_url); ?></a>
                        <p class="text-sm text-muted-foreground mt-2">随时分享！</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="text-center mt-12">
                <a href="/admin" class="inline-flex items-center px-6 py-3 bg-secondary hover:bg-secondary/80 text-secondary-foreground rounded-md transition-colors">管理员点击这里进入管理面板</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 404
else {
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
?>