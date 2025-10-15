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
    // Create rate limiting table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS rate_limits (
            ip INET PRIMARY KEY,
            request_count INT DEFAULT 0,
            window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    // Reset sequence properly
    $pdo->exec("SELECT setval('short_links_id_seq', COALESCE((SELECT MAX(id) FROM short_links), 0), true);");
} catch (PDOException $e) {
    http_response_code(500);
    die('数据库连接失败: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Helper function to generate CSRF token
function generate_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Helper function to validate CSRF token
function validate_csrf_token($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

// Rate limiting: 10 requests per minute per IP
function check_rate_limit($pdo) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $max_requests = 10;
    $window_seconds = 60;

    // Check for recent requests
    $stmt_check_recent = $pdo->prepare("
        SELECT request_count 
        FROM rate_limits 
        WHERE ip = ? 
        AND window_start > NOW() - INTERVAL '1 minute'
    ");
    $stmt_check_recent->execute([$ip]);
    $row_recent = $stmt_check_recent->fetch(PDO::FETCH_ASSOC);

    if ($row_recent && (int)$row_recent['request_count'] >= $max_requests) {
        http_response_code(429);
        die('请求过于频繁，请稍后重试。');
    }

    // Check if any row exists (for old entries)
    $stmt_check_any = $pdo->prepare("SELECT 1 FROM rate_limits WHERE ip = ?");
    $stmt_check_any->execute([$ip]);
    $row_any = $stmt_check_any->fetch();

    if ($row_any) {
        if ($row_recent) {
            // Recent, increment
            $stmt = $pdo->prepare("UPDATE rate_limits SET request_count = request_count + 1 WHERE ip = ?");
            $stmt->execute([$ip]);
        } else {
            // Old, reset to 1
            $stmt = $pdo->prepare("UPDATE rate_limits SET request_count = 1, window_start = NOW() WHERE ip = ?");
            $stmt->execute([$ip]);
        }
    } else {
        // No row, insert new
        $stmt = $pdo->prepare("INSERT INTO rate_limits (ip, request_count, window_start) VALUES (?, 1, NOW())");
        $stmt->execute([$ip]);
    }
}

// Apply rate limit to POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_rate_limit($pdo);
}

// Reserved shortcodes
$reserved_codes = ['admin', 'help', 'about', 'api'];

// Generate random 5-char from specified charset
function generate_random_code($pdo, $reserved_codes) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890';
    $max_attempts = 100; // Prevent infinite loop
    $attempts = 0;
    do {
        $code = substr(str_shuffle($chars), 0, 5);
        if (in_array(strtolower($code), $reserved_codes)) continue;
        $stmt = $pdo->prepare("SELECT id FROM short_links WHERE shortcode = ?");
        $stmt->execute([$code]);
        $attempts++;
    } while ($stmt->fetch() && $attempts < $max_attempts);
    if ($attempts >= $max_attempts) {
        throw new Exception('无法生成唯一短码');
    }
    return $code;
}

// Validate custom code
function validate_custom_code($code, $pdo, $reserved_codes) {
    if (strlen($code) < 5 || strlen($code) > 10) return '短码长度为5-10位';
    if (!preg_match('/^[A-Za-z0-9]+$/', $code)) return '短码仅限字母数字';
    if (in_array(strtolower($code), $reserved_codes)) return '短码被保留';
    $stmt = $pdo->prepare("SELECT id FROM short_links WHERE shortcode = ?");
    $stmt->execute([$code]);
    if ($stmt->fetch()) return '短码已存在';
    return true;
}

// Admin authentication: Use session after token validation
function require_admin_auth() {
    global $admin_token;
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['admin_auth']) || !hash_equals($_SESSION['admin_auth'], hash_hmac('sha256', $admin_token, session_id()))) {
        return false;
    }
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
    $csrf_token = generate_csrf_token();
    $error = '';
    $success = '';
    $links = [];
    $show_list = false;
    $input_token = $_POST['token'] ?? '';
    $valid_token = hash_equals($admin_token, $input_token); // Secure comparison

    if ($method === 'POST') {
        if (!validate_csrf_token($_POST['csrf'] ?? '')) {
            $error = 'CSRF令牌无效。';
        } elseif (!$valid_token && !require_admin_auth()) {
            $error = '无效的管理令牌。';
        } else {
            $action = $_POST['action'] ?? '';
            switch ($action) {
                case 'login':
                    if ($valid_token) {
                        $_SESSION['admin_auth'] = hash_hmac('sha256', $admin_token, session_id());
                        $show_list = true;
                    }
                    break;
                case 'add':
                    if (!require_admin_auth()) break;
                    $longurl = trim($_POST['url'] ?? '');
                    $custom_code = trim($_POST['custom_code'] ?? '');
                    if (!filter_var($longurl, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)) {
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
                            $stmt = $pdo->prepare("INSERT INTO short_links (shortcode, longurl) VALUES (?, ?)");
                            $stmt->execute([$code, $longurl]);
                            $success = '链接添加成功。';
                        }
                    }
                    break;
                case 'edit':
                    if (!require_admin_auth()) break;
                    $code = $_POST['code'] ?? '';
                    $newurl = trim($_POST['newurl'] ?? '');
                    if (!filter_var($newurl, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)) {
                        $error = '无效的新URL。';
                    } else {
                        $stmt = $pdo->prepare("UPDATE short_links SET longurl = ? WHERE shortcode = ?");
                        $stmt->execute([$newurl, $code]);
                        $success = '链接更新成功。';
                    }
                    break;
                case 'delete':
                    if (!require_admin_auth()) break;
                    $code = $_POST['code'] ?? '';
                    $stmt = $pdo->prepare("DELETE FROM short_links WHERE shortcode = ?");
                    $stmt->execute([$code]);
                    $success = '链接删除成功。';
                    break;
                case 'logout':
                    unset($_SESSION['admin_auth']);
                    $show_list = false;
                    $success = '已登出。';
                    break;
            }
            // Refresh list after action if authenticated
            if (require_admin_auth()) {
                $show_list = true;
            }
        }
    }

    if ($show_list && require_admin_auth()) {
        $stmt = $pdo->query("SELECT * FROM short_links ORDER BY created_at DESC");
        $links = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif (!require_admin_auth() && $method !== 'POST') {
        $show_list = false;
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
                            <div class="bg-destructive/10 border border-destructive/30 text-destructive px-4 py-3 rounded-md mb-4"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                        <form method="post">
                            <input type="hidden" name="action" value="login">
                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="mb-4">
                                <input type="password" name="token" class="w-full px-3 py-2 border border-input rounded-md focus:ring-2 focus:ring-ring" placeholder="管理令牌" required value="<?php echo htmlspecialchars($input_token, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <button type="submit" class="w-full bg-primary text-primary-foreground py-2 px-4 rounded-md hover:bg-primary/90 transition-colors">访问面板</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="max-w-md mx-auto mb-6">
                        <div class="bg-destructive/10 border border-destructive/30 text-destructive px-4 py-3 rounded-md"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="max-w-md mx-auto mb-6">
                        <div class="bg-secondary/50 border border-secondary/30 text-secondary-foreground px-4 py-3 rounded-md"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                <?php endif; ?>
                <!-- Add New Link -->
                <div class="max-w-md mx-auto mb-8">
                    <div class="bg-card rounded-lg border p-6">
                        <h3 class="text-lg font-semibold mb-4">添加新短链接</h3>
                        <form method="post">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="space-y-3">
                                <input type="url" name="url" class="w-full px-3 py-2 border border-input rounded-md focus:ring-2 focus:ring-ring" placeholder="https://example.com" required>
                                <input type="text" name="custom_code" class="w-full px-3 py-2 border border-input rounded-md focus:ring-2 focus:ring-ring" placeholder="自定义短码（可选）" maxlength="10">
                                <button type="submit" class="w-full bg-primary text-primary-foreground py-2 px-4 rounded-md hover:bg-primary/90 transition-colors">添加</button>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- List Links as Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($links as $link): ?>
                        <div class="bg-card rounded-lg border p-6">
                            <div class="font-mono text-primary text-lg font-semibold mb-2"><?php echo htmlspecialchars($link['shortcode'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <p class="text-muted-foreground text-sm mb-4 truncate max-w-full" title="<?php echo htmlspecialchars($link['longurl'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($link['longurl'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <div class="space-y-2 text-xs text-muted-foreground mb-4">
                                <p>点击: <?php echo htmlspecialchars($link['clicks'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <p>创建: <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($link['created_at'])), ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <div class="space-y-2">
                                <!-- Edit Form -->
                                <form method="post" class="flex gap-2">
                                    <input type="hidden" name="action" value="edit">
                                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="code" value="<?php echo htmlspecialchars($link['shortcode'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="url" name="newurl" class="flex-1 px-2 py-1 border border-input rounded text-xs" value="<?php echo htmlspecialchars($link['longurl'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                    <button type="submit" class="px-3 py-1 bg-primary text-primary-foreground rounded text-xs hover:bg-primary/90">编辑</button>
                                </form>
                                <!-- Delete Form -->
                                <form method="post" class="flex gap-2" onsubmit="return confirm('删除此链接？');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="code" value="<?php echo htmlspecialchars($link['shortcode'], ENT_QUOTES, 'UTF-8'); ?>">
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
                        <input type="hidden" name="action" value="logout">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="px-6 py-3 bg-destructive hover:bg-destructive/80 text-destructive-foreground rounded-md transition-colors">登出</button>
                    </form>
                    <form method="post" class="inline">
                        <input type="hidden" name="action" value="list">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="px-6 py-3 bg-secondary hover:bg-secondary/80 text-secondary-foreground rounded-md transition-colors">刷新</button>
                    </form>
                    <a href="/" class="px-6 py-3 bg-primary hover:bg-primary/90 text-primary-foreground rounded-md transition-colors">返回首页</a>
                </div>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    if (session_status() !== PHP_SESSION_NONE) {
        session_write_close();
    }
    exit;
}

// Handle short URL redirection (exclude reserved)
elseif (preg_match('/^\/([A-Za-z0-9]{5,10})$/', $path, $matches)) {
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
    $csrf_token = generate_csrf_token();
    $error = '';
    $success = '';
    $short_url = '';
    $post_url = $_POST['url'] ?? '';
    $post_custom = $_POST['custom_code'] ?? '';
    if ($method === 'POST') {
        if (!validate_csrf_token($_POST['csrf'] ?? '')) {
            $error = 'CSRF令牌无效。';
        } else {
            $longurl = trim($post_url);
            $custom_code = trim($post_custom);
            if (!filter_var($longurl, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)) {
                $error = '无效的URL，请输入有效链接。';
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
                    $stmt = $pdo->prepare("INSERT INTO short_links (shortcode, longurl) VALUES (?, ?)");
                    $stmt->execute([$code, $longurl]);
                    $short_url = $base_url . '/' . $code;
                    $success = '短链接创建成功！';
                }
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
                        <div class="bg-destructive/10 border border-destructive/30 text-destructive px-4 py-3 rounded-md mb-4"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="bg-secondary/50 border border-secondary/30 text-secondary-foreground px-4 py-3 rounded-md mb-4"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>
                    <form method="post" class="space-y-4">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                        <div>
                            <label class="block text-sm font-medium mb-2">原始链接</label>
                            <input type="url" name="url" class="w-full px-3 py-2 border border-input rounded-md focus:ring-2 focus:ring-ring focus:border-transparent" placeholder="https://example.com" required value="<?php echo htmlspecialchars($post_url, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2">自定义短码（可选）</label>
                            <input type="text" name="custom_code" class="w-full px-3 py-2 border border-input rounded-md focus:ring-2 focus:ring-ring focus:border-transparent" placeholder="至少5位字母数字" value="<?php echo htmlspecialchars($post_custom, ENT_QUOTES, 'UTF-8'); ?>" maxlength="10">
                            <p class="text-xs text-muted-foreground mt-1">留空自动生成5位随机码。避免使用 'admin' 等保留词。</p>
                        </div>
                        <button type="submit" class="w-full bg-primary text-primary-foreground py-2 px-4 rounded-md hover:bg-primary/90 transition-colors font-medium">缩短链接</button>
                    </form>
                </div>
                <?php if ($short_url): ?>
                    <div class="mt-6 bg-card rounded-lg border p-6">
                        <h3 class="text-lg font-semibold mb-2">您的短链接：</h3>
                        <a href="<?php echo htmlspecialchars($short_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="text-primary hover:text-primary/80 font-mono text-sm break-all"><?php echo htmlspecialchars($short_url, ENT_QUOTES, 'UTF-8'); ?></a>
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
    if (session_status() !== PHP_SESSION_NONE) {
        session_write_close();
    }
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