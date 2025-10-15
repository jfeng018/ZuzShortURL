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
    $pdo->exec("SELECT setval('short_links_id_seq', COALESCE((SELECT MAX(id) FROM short_links), 1), true);");
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

// Rate limiting: 120 requests per minute per IP
function check_rate_limit($pdo) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $max_requests = 120;
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

// Admin panel (merged single page)
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
                    if (!filter_var($longurl, FILTER_VALIDATE_URL)) { // Removed FILTER_FLAG_PATH_REQUIRED
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
                    if (!filter_var($newurl, FILTER_VALIDATE_URL)) { // Removed FILTER_FLAG_PATH_REQUIRED
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

        // Prepare chart data without changing DB structure
        // Total links and clicks
        $total_links = $pdo->query("SELECT COUNT(*) as count FROM short_links")->fetch(PDO::FETCH_ASSOC)['count'];
        $total_clicks = $pdo->query("SELECT SUM(clicks) as sum FROM short_links")->fetch(PDO::FETCH_ASSOC)['sum'] ?? 0;

        // Top 5 links by clicks for pie chart
        $top_clicks = $pdo->query("SELECT shortcode, clicks FROM short_links ORDER BY clicks DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        $pie_labels = array_column($top_clicks, 'shortcode');
        $pie_data = array_column($top_clicks, 'clicks');

        // Daily creations for line chart (last 7 days)
        $daily_data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $count = $pdo->query("SELECT COUNT(*) as count FROM short_links WHERE DATE(created_at) = '$date'")->fetch(PDO::FETCH_ASSOC)['count'];
            $daily_data[] = (int)$count;
        }
        $line_labels = [];
        for ($i = 6; $i >= 0; $i--) {
            $line_labels[] = date('M d', strtotime("-$i days"));
        }

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
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

            /* SaaS-style grid background */
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
                backdrop-filter: blur(0.5px);
                pointer-events: none;
            }

            /* Enhanced card with blur */
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

            /* Additional spacing and shadows for SaaS feel */
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

            /* Chart container for PC size */
            .chart-container {
                max-width: 800px;
                margin: 0 auto;
            }

            /* Sidebar for admin UX */
            .sidebar {
                height: calc(100vh - 4rem);
                position: sticky;
                top: 4rem;
            }

            /* Modal backdrop blur */
            .modal-backdrop {
                backdrop-filter: blur(8px);
                -webkit-backdrop-filter: blur(8px);
            }
        </style>
    </head>
    <body class="bg-background text-foreground min-h-screen">
        <nav class="bg-card border-b border-border px-4 py-4">
            <div class="container mx-auto flex justify-between items-center">
                <h1 class="text-2xl font-bold">Zuz.Asia Admin</h1>
                <?php if ($show_list): ?>
                    <form method="post" class="inline">
                        <input type="hidden" name="action" value="logout">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="px-4 py-2 bg-destructive text-destructive-foreground rounded-md hover:bg-destructive/90">登出</button>
                    </form>
                <?php endif; ?>
            </div>
        </nav>
        <div class="flex">
            <?php if ($show_list): ?>
            <aside class="sidebar w-64 bg-card border-r border-border p-4 hidden md:block">
                <nav class="space-y-2">
                    <a href="/admin" class="flex items-center px-3 py-2 rounded-md bg-primary text-primary-foreground">
                        <span class="mr-3">📊</span> 管理面板
                    </a>
                </nav>
            </aside>
            <?php endif; ?>
            <main class="flex-1 p-8">
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

                    <!-- Merged Dashboard & Links Management -->
                    <div class="space-y-6">
                        <!-- Stats Overview -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div class="bg-card rounded-lg border p-6 text-center">
                                <h3 class="text-lg font-semibold text-muted-foreground">总链接数</h3>
                                <p class="text-3xl font-bold"><?php echo htmlspecialchars($total_links, ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <div class="bg-card rounded-lg border p-6 text-center">
                                <h3 class="text-lg font-semibold text-muted-foreground">总点击量</h3>
                                <p class="text-3xl font-bold"><?php echo htmlspecialchars($total_clicks, ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <div class="bg-card rounded-lg border p-6 text-center">
                                <h3 class="text-lg font-semibold text-muted-foreground">平均点击</h3>
                                <p class="text-3xl font-bold"><?php echo $total_links > 0 ? round($total_clicks / $total_links, 1) : 0; ?></p>
                            </div>
                        </div>

                        <!-- Add New Link Button -->
                        <div class="max-w-md mx-auto">
                            <button onclick="openAddModal()" class="w-full bg-primary text-primary-foreground py-2 px-4 rounded-md hover:bg-primary/90 transition-colors">+ 添加新短链接</button>
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
                                        <button onclick="openEditModal('<?php echo htmlspecialchars($link['shortcode'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars(addslashes($link['longurl']), ENT_QUOTES, 'UTF-8'); ?>')" class="w-full px-3 py-1 bg-primary text-primary-foreground rounded text-xs hover:bg-primary/90">编辑</button>
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
                    </div>

                    <!-- Add Modal -->
                    <div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 modal-backdrop">
                        <div class="bg-card rounded-lg border p-6 max-w-md w-full mx-4">
                            <h3 class="text-lg font-semibold mb-4">添加新短链接</h3>
                            <form method="post" id="addForm">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="space-y-3">
                                    <input type="url" name="url" class="w-full px-3 py-2 border border-input rounded-md focus:ring-2 focus:ring-ring" placeholder="https://example.com" required>
                                    <input type="text" name="custom_code" class="w-full px-3 py-2 border border-input rounded-md focus:ring-2 focus:ring-ring" placeholder="自定义短码（可选）" maxlength="10">
                                    <div class="flex gap-2">
                                        <button type="button" onclick="closeAddModal()" class="flex-1 bg-secondary text-secondary-foreground py-2 px-4 rounded-md hover:bg-secondary/80">取消</button>
                                        <button type="submit" class="flex-1 bg-primary text-primary-foreground py-2 px-4 rounded-md hover:bg-primary/90">添加</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Edit Modal -->
                    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 modal-backdrop">
                        <div class="bg-card rounded-lg border p-6 max-w-md w-full mx-4">
                            <h3 class="text-lg font-semibold mb-4">编辑短链接</h3>
                            <form method="post" id="editForm">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="code" id="editCode">
                                <div class="space-y-3">
                                    <input type="url" name="newurl" id="editUrl" class="w-full px-3 py-2 border border-input rounded-md focus:ring-2 focus:ring-ring" required>
                                    <div class="flex gap-2">
                                        <button type="button" onclick="closeEditModal()" class="flex-1 bg-secondary text-secondary-foreground py-2 px-4 rounded-md hover:bg-secondary/80">取消</button>
                                        <button type="submit" class="flex-1 bg-primary text-primary-foreground py-2 px-4 rounded-md hover:bg-primary/90">保存</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>

        <!-- Footer -->
        <footer class="mt-12 pt-8 border-t border-border text-center text-sm text-muted-foreground">
            <p>&copy; 2025 Zuz.Asia. All rights reserved.</p>
        </footer>

        <script>
            function openAddModal() {
                document.getElementById('addModal').classList.remove('hidden');
            }

            function closeAddModal() {
                document.getElementById('addModal').classList.add('hidden');
                document.getElementById('addForm').reset();
            }

            function openEditModal(code, url) {
                document.getElementById('editCode').value = code;
                document.getElementById('editUrl').value = url;
                document.getElementById('editModal').classList.remove('hidden');
            }

            function closeEditModal() {
                document.getElementById('editModal').classList.add('hidden');
                document.getElementById('editForm').reset();
            }

            // Close modals on outside click
            window.onclick = function(event) {
                const addModal = document.getElementById('addModal');
                const editModal = document.getElementById('editModal');
                if (event.target === addModal) {
                    closeAddModal();
                }
                if (event.target === editModal) {
                    closeEditModal();
                }
            }
        </script>
    </body>
    </html>
    <?php
    if (session_status() !== PHP_SESSION_NONE) {
        session_write_close();
    }
    exit;
}

// Create page
elseif ($path === '/create') {
    $csrf_token = generate_csrf_token();
    $error = '';
    $success = '';
    $short_url = '';
    $post_url = $_POST['url'] ?? '';
    $post_custom = $_POST['custom_code'] ?? '';
    $history = [];
    if (isset($_COOKIE['short_history'])) {
        $history = json_decode($_COOKIE['short_history'], true) ?: [];
    }
    if ($method === 'POST') {
        if (!validate_csrf_token($_POST['csrf'] ?? '')) {
            $error = 'CSRF令牌无效。';
        } else {
            $longurl = trim($post_url);
            $custom_code = trim($post_custom);
            if (!filter_var($longurl, FILTER_VALIDATE_URL)) { // Removed FILTER_FLAG_PATH_REQUIRED
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
                    // Update history cookie
                    $new_history = array_slice(array_merge([$short_url], $history), 0, 10); // Keep last 10
                    setcookie('short_history', json_encode($new_history), time() + (86400 * 30), '/', '', true, true); // 30 days, secure, httponly
                    $history = $new_history;
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

            /* SaaS-style grid background */
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

            /* Enhanced card with blur */
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

            /* Additional spacing and shadows for SaaS feel */
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

            /* History list */
            .history-item {
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
            }

            /* Copy button blur */
            .copy-btn {
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
            }
        </style>
    </head>
    <body class="bg-background text-foreground min-h-screen">
        <div class="container mx-auto px-4 py-8">
            <div class="text-center mb-12">
                <h1 class="text-5xl md:text-6xl font-bold mb-4">创建短链接</h1>
                <p class="text-xl text-muted-foreground max-w-2xl mx-auto">即时生成您的短链接。简单、高效、安全。</p>
            </div>
            <div class="max-w-md mx-auto mb-8">
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
                        <button type="submit" class="w-full bg-primary text-primary-foreground py-2 px-4 rounded-md hover:bg-primary/90 transition-colors font-medium">+ 新建短链接</button>
                    </form>
                </div>
                <?php if ($short_url): ?>
                    <div class="mt-6 bg-card rounded-lg border p-6">
                        <h3 class="text-lg font-semibold mb-2">您的短链接：</h3>
                        <div class="flex gap-2 items-center">
                            <a href="<?php echo htmlspecialchars($short_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="flex-1 text-primary hover:text-primary/80 font-mono text-sm break-all"><?php echo htmlspecialchars($short_url, ENT_QUOTES, 'UTF-8'); ?></a>
                            <button onclick="copyToClipboard('<?php echo htmlspecialchars($short_url, ENT_QUOTES, 'UTF-8'); ?>')" class="copy-btn bg-secondary text-secondary-foreground px-3 py-2 rounded-md hover:bg-secondary/80 text-sm">复制</button>
                        </div>
                        <p class="text-sm text-muted-foreground mt-2">随时分享！</p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($history)): ?>
                    <div class="mt-8 bg-card rounded-lg border p-6">
                        <h3 class="text-lg font-semibold mb-4">历史记录（本地）</h3>
                        <div class="grid grid-cols-1 gap-3">
                            <?php foreach (array_slice(array_reverse($history), 0, 10) as $hist): ?>
                                <div class="history-item bg-secondary/20 rounded p-3 text-sm border border-secondary/20 flex gap-2 items-center">
                                    <a href="<?php echo htmlspecialchars($hist, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="flex-1 text-primary hover:text-primary/80 font-mono break-all"><?php echo htmlspecialchars($hist, ENT_QUOTES, 'UTF-8'); ?></a>
                                    <button onclick="copyToClipboard('<?php echo htmlspecialchars($hist, ENT_QUOTES, 'UTF-8'); ?>')" class="copy-btn bg-secondary text-secondary-foreground px-2 py-1 rounded text-xs hover:bg-secondary/80">复制</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="text-center">
                <a href="/" class="px-6 py-3 bg-secondary hover:bg-secondary/80 text-secondary-foreground rounded-md transition-colors mr-4">返回首页</a>
                <a href="/admin" class="px-6 py-3 bg-primary hover:bg-primary/90 text-primary-foreground rounded-md transition-colors">管理面板</a>
            </div>

            <!-- Footer -->
            <footer class="mt-12 pt-8 border-t border-border text-center text-sm text-muted-foreground">
                <p>&copy; 2025 Zuz.Asia. All rights reserved.</p>
            </footer>
        </div>

        <script>
            function copyToClipboard(text) {
                navigator.clipboard.writeText(text).then(function() {
                    // Optional: Show a toast or alert
                    alert('已复制到剪贴板！');
                }, function(err) {
                    console.error('复制失败: ', err);
                });
            }
        </script>
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

// Home / Welcome page (optimized with enhanced blur and layout)
elseif ($path === '/' || $path === '') {
    $history = [];
    if (isset($_COOKIE['short_history'])) {
        $history = json_decode($_COOKIE['short_history'], true) ?: [];
    }
    // Render home page - SaaS style welcome
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

            /* SaaS-style grid background */
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

            /* Enhanced card with blur */
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

            /* Additional spacing and shadows for SaaS feel */
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

            /* Pricing card blur */
            .pricing-card {
                backdrop-filter: blur(15px);
                -webkit-backdrop-filter: blur(15px);
            }

            /* Hero blur enhancement */
            .hero-section {
                backdrop-filter: blur(2px);
                -webkit-backdrop-filter: blur(2px);
            }
        </style>
    </head>
    <body class="bg-background text-foreground min-h-screen">
        <div class="container mx-auto px-4 py-8">
            <!-- Hero Section (optimized with enhanced blur) -->
            <section class="hero-section text-center mb-16 bg-card/50 rounded-xl p-8">
                <h1 class="text-5xl md:text-7xl font-bold mb-6">Zuz.Asia</h1>
                <p class="text-xl text-muted-foreground max-w-3xl mx-auto mb-8">无需注册，即时创建短链接。简单、高效、安全。享受无缝的链接管理体验。我们的免费计划让您轻松开始。</p>
                <div class="space-x-4">
                    <a href="/create" class="inline-flex items-center px-4 py-2 bg-primary hover:bg-primary/90 text-primary-foreground rounded-lg transition-colors font-semibold text-base">免费开始</a>
                </div>
            </section>

            <!-- Features Section -->
            <section class="grid md:grid-cols-3 gap-8 mb-16">
                <div class="bg-card rounded-lg border p-8 pricing-card">
                    <h3 class="text-2xl font-bold mb-4">即时缩短</h3>
                    <p class="text-muted-foreground">输入长连接，一键生成短链接，无需等待、立即分享、极速加载</p>
                </div>
                <div class="bg-card rounded-lg border p-8 pricing-card">
                    <h3 class="text-2xl font-bold mb-4">无限使用</h3>
                    <p class="text-muted-foreground">免费计划支持无限量地创建短链接，也可以Fork仓库源码自己搭建本系统。</p>
                </div>
                <div class="bg-card rounded-lg border p-8 pricing-card">
                    <h3 class="text-2xl font-bold mb-4">安全可靠</h3>
                    <p class="text-muted-foreground">基于PostgreSQL数据库加密存储，性能极致优化，安全可靠。</p>
                </div>
            </section>

            <!-- Pricing Section - Only Free Plan -->
            <section class="text-center mb-16">
                <h2 class="text-3xl font-bold mb-8">选择您的计划</h2>
                <div class="max-w-md mx-auto bg-card rounded-lg border p-8 pricing-card">
                    <h3 class="text-2xl font-bold mb-4">免费版</h3>
                    <ul class="space-y-2 text-left mb-6">
                        <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> 无限短链接</li>
                        <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> 基本统计</li>
                        <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> 自定义短码</li>
                        <li class="flex items-center"><span class="text-yellow-500 mr-2">⚠</span> 速率限制</li>
                    </ul>
                    <p class="text-3xl font-bold text-green-600 mb-4">$0 / 月</p>
                    <a href="/create" class="w-full bg-primary text-primary-foreground py-3 px-6 rounded-md hover:bg-primary/90 transition-colors font-semibold">立即开始</a>
                </div>
            </section>

            <!-- Stats Section -->
            <section class="grid md:grid-cols-3 gap-8 mb-16">
                <div class="bg-card rounded-lg border p-8 text-center pricing-card">
                    <h3 class="text-4xl font-bold text-primary">10k+</h3>
                    <p class="text-muted-foreground">链接已创建</p>
                </div>
                <div class="bg-card rounded-lg border p-8 text-center pricing-card">
                    <h3 class="text-4xl font-bold text-primary">99.9%</h3>
                    <p class="text-muted-foreground">正常运行时间</p>
                </div>
                <div class="bg-card rounded-lg border p-8 text-center pricing-card">
                    <h3 class="text-4xl font-bold text-primary">1.3s</h3>
                    <p class="text-muted-foreground">平均响应时间</p>
                </div>
            </section>

            <!-- CTA Section -->
            <section class="text-center mb-16">
                <h2 class="text-3xl font-bold mb-4">准备好缩短您的第一个链接了吗？</h2>
                <a href="/create" class="inline-flex items-center px-8 py-4 bg-primary hover:bg-primary/90 text-primary-foreground rounded-lg transition-colors font-semibold text-lg">Get Started 免费</a>
            </section>

            <!-- Footer -->
            <footer class="pt-8 border-t border-border text-center text-sm text-muted-foreground">
                <p>&copy; 2025 Zuz.Asia. All rights reserved. | <a href="https://github.com/JanePHPDev/ZuzShortURL" target="_blank" class="text-primary hover:underline">GitHub</a></p>
            </footer>
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