<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$csrf_token = generate_csrf_token();
$error = '';
$success = '';
$links = [];
$users = [];
$show_list = false;
$input_token = $_POST['token'] ?? '';
$valid_token = hash_equals($admin_token, $input_token);
$active_tab = $_GET['tab'] ?? 'dashboard';
$user_links = [];
$edit_user_id = $_GET['edit_user'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                $enable_intermediate = isset($_POST['enable_intermediate']) ? true : false;
                $redirect_delay = is_numeric($_POST['redirect_delay']) ? (int)$_POST['redirect_delay'] : 0;
                $link_password = trim($_POST['link_password'] ?? '');
                $password_hash = !empty($link_password) ? password_hash($link_password, PASSWORD_DEFAULT) : null;
                $expiration = $_POST['expiration'] ?? null;
                $user_id = $_POST['user_id'] ?? null;
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
                        $stmt->execute([$code, $longurl, $user_id ?: null, $enable_str, $redirect_delay, $password_hash, $expiration ?: null]);
                        $success = '链接添加成功。';
                    }
                }
                break;
            case 'edit':
                if (!require_admin_auth()) break;
                $code = $_POST['code'] ?? '';
                $newurl = trim($_POST['newurl'] ?? '');
                $enable_intermediate = isset($_POST['enable_intermediate']) ? true : false;
                $redirect_delay = is_numeric($_POST['redirect_delay']) ? (int)$_POST['redirect_delay'] : 0;
                $link_password = trim($_POST['link_password'] ?? '');
                $password_hash = !empty($link_password) ? password_hash($link_password, PASSWORD_DEFAULT) : null;
                $expiration = $_POST['expiration'] ?? null;
                $user_id = $_POST['user_id'] ?? null;
                if (!filter_var($newurl, FILTER_VALIDATE_URL)) {
                    $error = '无效的新URL。';
                } else {
                    $enable_str = $enable_intermediate ? 'true' : 'false';
                    $stmt = $pdo->prepare("UPDATE short_links SET longurl = ?, enable_intermediate_page = ?, redirect_delay = ?, link_password = ?, expiration_date = ?, user_id = ? WHERE shortcode = ?");
                    $stmt->execute([$newurl, $enable_str, $redirect_delay, $password_hash, $expiration ?: null, $user_id ?: null, $code]);
                    $success = '链接更新成功。';
                }
                break;
            case 'delete_link':
                if (!require_admin_auth()) break;
                $code = $_POST['code'] ?? '';
                $stmt = $pdo->prepare("DELETE FROM short_links WHERE shortcode = ?");
                $stmt->execute([$code]);
                $success = '链接删除成功。';
                break;
            case 'delete_user':
                if (!require_admin_auth()) break;
                $user_id = $_POST['user_id'] ?? '';
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $stmt = $pdo->prepare("DELETE FROM short_links WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $success = '用户删除成功。';
                break;
            case 'edit_user':
                if (!require_admin_auth()) break;
                $user_id = $_POST['user_id'] ?? '';
                $new_username = trim($_POST['new_username'] ?? '');
                $new_password = trim($_POST['new_password'] ?? '');
                if (empty($new_username)) {
                    $error = '用户名不能为空。';
                } else {
                    $update_fields = ['username = ?'];
                    $params = [$new_username];
                    if (!empty($new_password)) {
                        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                        $update_fields[] = 'password = ?';
                        $params[] = $password_hash;
                    }
                    $params[] = $user_id;
                    $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = ?");
                    $stmt->execute($params);
                    $success = '用户更新成功。';
                }
                break;
            case 'settings':
                if (!require_admin_auth()) break;
                $private_mode = isset($_POST['private_mode']) ? 'true' : 'false';
                $allow_guest = isset($_POST['allow_guest']) ? 'true' : 'false';
                $allow_register = isset($_POST['allow_register']) ? 'true' : 'false';
                $turnstile_enabled = isset($_POST['turnstile_enabled']) ? 'true' : 'false';
                $enable_dual_domain = isset($_POST['enable_dual_domain']) ? 'true' : 'false';
                $turnstile_site_key = trim($_POST['turnstile_site_key'] ?? '');
                $turnstile_secret_key = trim($_POST['turnstile_secret_key'] ?? '');
                $official_domain = trim($_POST['official_domain'] ?? '');
                $short_domain = trim($_POST['short_domain'] ?? '');
                $site_title = trim($_POST['site_title'] ?? '');
                $header_title = trim($_POST['header_title'] ?? '');
                $home_description = trim($_POST['home_description'] ?? '');
                $home_image_url = trim($_POST['home_image_url'] ?? '');
                $intermediate_logo_url = trim($_POST['intermediate_logo_url'] ?? '');
                $intermediate_text = trim($_POST['intermediate_text'] ?? '');
                set_setting($pdo, 'private_mode', $private_mode);
                set_setting($pdo, 'allow_guest', $allow_guest);
                set_setting($pdo, 'allow_register', $allow_register);
                set_setting($pdo, 'turnstile_enabled', $turnstile_enabled);
                set_setting($pdo, 'enable_dual_domain', $enable_dual_domain);
                set_setting($pdo, 'turnstile_site_key', $turnstile_site_key);
                set_setting($pdo, 'turnstile_secret_key', $turnstile_secret_key);
                set_setting($pdo, 'official_domain', $official_domain);
                set_setting($pdo, 'short_domain', $short_domain);
                set_setting($pdo, 'site_title', $site_title);
                set_setting($pdo, 'header_title', $header_title);
                set_setting($pdo, 'home_description', $home_description);
                set_setting($pdo, 'home_image_url', $home_image_url);
                set_setting($pdo, 'intermediate_logo_url', $intermediate_logo_url);
                set_setting($pdo, 'intermediate_text', $intermediate_text);
                $success = '设置更新成功。';
                break;
            case 'logout':
                unset($_SESSION['admin_auth']);
                $show_list = false;
                $success = '已登出。';
                break;
            case 'delete_expired':
                if (!require_admin_auth()) break;
                $stmt = $pdo->prepare("DELETE FROM short_links WHERE expiration_date < NOW()");
                $stmt->execute();
                $success = '已过期链接删除成功。';
                break;
        }
    }
}

$show_list = require_admin_auth();

if ($show_list) {
    $stmt = $pdo->query("SELECT * FROM short_links ORDER BY created_at DESC");
    $links = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_users = count($users);
    $total_links = $pdo->query("SELECT COUNT(*) as count FROM short_links")->fetch()['count'];
    $total_clicks = $pdo->query("SELECT SUM(clicks) as sum FROM short_links")->fetch()['sum'] ?? 0;
    $click_rate = $total_links > 0 ? round(($total_clicks / $total_links) * 100, 2) : 0;

    $sources = $pdo->query("
SELECT 
  CASE 
    WHEN referrer IS NULL OR referrer = '' THEN 'Direct'
    ELSE regexp_replace(referrer, '^(https?://)?([^/]+).*', '\\2')
  END AS domain,
  COUNT(*) as count 
FROM click_logs 
GROUP BY domain 
ORDER BY count DESC
LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

    $top_links = $pdo->query("SELECT shortcode, clicks FROM short_links ORDER BY clicks DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

    $daily_clicks_raw = $pdo->query("
SELECT date(clicked_at) as day, COUNT(*) as count 
FROM click_logs 
WHERE clicked_at >= NOW() - INTERVAL '30 days'
GROUP BY day 
ORDER BY day ASC
")->fetchAll(PDO::FETCH_ASSOC);

    $daily_clicks = [];
    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $daily_clicks[$date] = 0;
    }
    foreach ($daily_clicks_raw as $row) {
        $daily_clicks[$row['day']] = (int)$row['count'];
    }

    $reg_raw = $pdo->query("SELECT date(created_at) as day, COUNT(*) as count FROM users WHERE created_at >= NOW() - INTERVAL '7 days' GROUP BY day ORDER BY day ASC")->fetchAll(PDO::FETCH_ASSOC);
    $registrations = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $registrations[$date] = 0;
    }
    foreach ($reg_raw as $row) {
        $registrations[$row['day']] = (int)$row['count'];
    }

    $new_links_raw = $pdo->query("SELECT date(created_at) as day, COUNT(*) as count FROM short_links WHERE created_at >= NOW() - INTERVAL '7 days' GROUP BY day ORDER BY day ASC")->fetchAll(PDO::FETCH_ASSOC);
    $new_links = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $new_links[$date] = 0;
    }
    foreach ($new_links_raw as $row) {
        $new_links[$row['day']] = (int)$row['count'];
    }

    $last7_reg = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= NOW() - INTERVAL '7 days' AND created_at < NOW()")->fetchColumn();
    $prev7_reg = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= NOW() - INTERVAL '14 days' AND created_at < NOW() - INTERVAL '7 days'")->fetchColumn();
    if ($prev7_reg > 0) {
        $growth_reg = round(($last7_reg - $prev7_reg) / $prev7_reg * 100, 2) . '%';
    } else {
        $growth_reg = $last7_reg > 0 ? 'New' : '0%';
    }

    $last7_links = $pdo->query("SELECT COUNT(*) FROM short_links WHERE created_at >= NOW() - INTERVAL '7 days' AND created_at < NOW()")->fetchColumn();
    $prev7_links = $pdo->query("SELECT COUNT(*) FROM short_links WHERE created_at >= NOW() - INTERVAL '14 days' AND created_at < NOW() - INTERVAL '7 days'")->fetchColumn();
    if ($prev7_links > 0) {
        $growth_links = round(($last7_links - $prev7_links) / $prev7_links * 100, 2) . '%';
    } else {
        $growth_links = $last7_links > 0 ? 'New' : '0%';
    }

    if ($active_tab === 'users' && $edit_user_id) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$edit_user_id]);
        $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT * FROM short_links WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$edit_user_id]);
        $user_links = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理面板 - <?php echo htmlspecialchars(get_setting($pdo, 'site_title') ?? 'Zuz.Asia'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="includes/script.js"></script>
    <link rel="stylesheet" href="includes/styles.css">
    <script src="https://cdn.mengze.vip/npm/chart.js"></script>
    <style>
    .chart-container {
        position: relative;
        height: 300px;
        width: 100%;
    }
    @media (max-width: 768px) {
        .chart-container {
            height: 200px;
        }
    }
    .truncate-max {
        max-width: 200px; /* 调整最大宽度以防止超宽 */
    }
</style>
</head>
<body class="bg-background text-foreground min-h-screen">
    <?php if (!$show_list): ?>
        <div class="flex items-center justify-center min-h-screen">
            <div class="max-w-md mx-auto bg-card rounded-lg border p-6">
                <h2 class="text-xl font-semibold mb-4">输入管理令牌</h2>
                <form method="post">
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <div class="mb-4">
                        <input type="password" name="token" class="w-full px-3 py-2 border border-input rounded-md" placeholder="管理令牌" required>
                    </div>
                    <button type="submit" class="w-full bg-primary text-primary-foreground shadow-sm hover:bg-primary/90 h-9 px-4 py-2 rounded-md transition-colors">访问面板</button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="flex min-h-screen">
            <!-- 左侧导航栏 -->
            <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 w-64 bg-card border-r border-border transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out">
                <div class="flex h-full flex-col">
                    <div class="flex h-14 items-center border-b border-border px-4 lg:h-[60px] lg:px-6">
                        <h1 class="text-lg font-semibold">Zuz.Asia Admin</h1>
                    </div>
                    <nav class="flex-1 overflow-y-auto py-4">
                        <ul class="space-y-1 px-2">
                            <li>
                                <a href="?tab=dashboard" class="flex items-center rounded-md px-3 py-2 text-sm font-medium <?php echo $active_tab === 'dashboard' ? 'bg-accent text-accent-foreground' : 'hover:bg-accent hover:text-accent-foreground'; ?>">
                                    <svg class="mr-3 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z" />
                                    </svg>
                                    数据看板
                                </a>
                            </li>
                            <li>
                                <a href="?tab=links" class="flex items-center rounded-md px-3 py-2 text-sm font-medium <?php echo $active_tab === 'links' ? 'bg-accent text-accent-foreground' : 'hover:bg-accent hover:text-accent-foreground'; ?>">
                                    <svg class="mr-3 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.102 1.101" />
                                    </svg>
                                    链接管理
                                </a>
                            </li>
                            <li>
                                <a href="?tab=users" class="flex items-center rounded-md px-3 py-2 text-sm font-medium <?php echo $active_tab === 'users' ? 'bg-accent text-accent-foreground' : 'hover:bg-accent hover:text-accent-foreground'; ?>">
                                    <svg class="mr-3 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    用户管理
                                </a>
                            </li>
                            <li>
                                <a href="?tab=settings" class="flex items-center rounded-md px-3 py-2 text-sm font-medium <?php echo $active_tab === 'settings' ? 'bg-accent text-accent-foreground' : 'hover:bg-accent hover:text-accent-foreground'; ?>">
                                    <svg class="mr-3 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    系统设置
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <div class="border-t border-border p-4">
                        <form method="post">
                            <input type="hidden" name="action" value="logout">
                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <button type="submit" class="w-full flex items-center rounded-md px-3 py-2 text-sm font-medium hover:bg-accent hover:text-accent-foreground">
                                <svg class="mr-3 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                                登出
                            </button>
                        </form>
                    </div>
                </div>
            </aside>

            <!-- 主内容区 -->
            <div class="flex-1 md:ml-64">
                <!-- 顶部栏（移动端汉堡按钮） -->
                <header class="bg-card border-b border-border px-4 py-4 md:hidden flex items-center">
                    <button onclick="toggleSidebar()" class="p-2 rounded-md hover:bg-accent">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <h1 class="ml-4 text-xl font-bold">管理面板</h1>
                </header>

                <!-- 面包屑导航 -->
                <nav class="bg-card border-b border-border px-4 py-3 hidden md:block">
                    <ol class="list-reset flex text-sm font-medium">
                        <li>
                            <a href="/admin" class="text-primary transition duration-150 ease-in-out hover:text-primary-600 focus:text-primary-600 active:text-primary-700">管理员面板</a>
                        </li>
                        <li>
                            <span class="mx-2 text-neutral-500">/</span>
                        </li>
                        <li class="text-neutral-500">
                                <?php
                                $tab_names = [
                                    'dashboard' => '数据看板',
                                    'links' => '链接管理',
                                    'users' => '用户管理',
                                    'settings' => '系统设置'
                                ];
                                echo $tab_names[$active_tab] ?? '未知';
                                ?>
                        </li>
                    </ol>
                </nav>

                <!-- 页面内容 -->
                <main class="p-4 md:p-6 space-y-6">
                    <?php if ($error): ?>
                        <div class="relative rounded-md border-l-4 border-red-500 bg-red-100 p-4 text-red-700" role="alert">
                            <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="relative rounded-md border-l-4 border-green-500 bg-green-100 p-4 text-green-700" role="alert">
                            <span class="block sm:inline"><?php echo htmlspecialchars($success); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($active_tab === 'dashboard'): ?>
                        <section>
                            <h2 class="text-2xl font-bold mb-4">数据看板</h2>
                            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                                <div class="rounded-lg border bg-card p-4 shadow-sm">
                                    <div class="flex flex-row items-center justify-between space-y-0 pb-2">
                                        <h3 class="text-sm font-medium">总用户</h3>
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-muted-foreground">
                                            <path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"></path>
                                            <circle cx="9" cy="7" r="4"></circle>
                                            <path d="M22 21v-2a4 4 0 00-3-3.87m-3-12a4 4 0 010 7.75"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-2xl font-bold"><?php echo $total_users; ?></div>
                                    </div>
                                </div>
                                <div class="rounded-lg border bg-card p-4 shadow-sm">
                                    <div class="flex flex-row items-center justify-between space-y-0 pb-2">
                                        <h3 class="text-sm font-medium">总链接</h3>
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-muted-foreground">
                                            <path d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.102 1.101"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-2xl font-bold"><?php echo $total_links; ?></div>
                                    </div>
                                </div>
                                <div class="rounded-lg border bg-card p-4 shadow-sm">
                                    <div class="flex flex-row items-center justify-between space-y-0 pb-2">
                                        <h3 class="text-sm font-medium">总点击</h3>
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-muted-foreground">
                                            <path d="M3.5 13.1c.8 0 1.5.7 1.5 1.5v4.9c0 .6.4 1.1 1 1.1s1-.5 1-1.1v-4.9c0-.8.7-1.5 1.5-1.5s1.5.7 1.5 1.5v4.9c0 .6.4 1.1 1 1.1s1-.5 1-1.1v-4.9c0-.8.7-1.5 1.5-1.5s1.5.7 1.5 1.5v4.9c0 .6.4 1.1 1 1.1h.5m-16 4h17"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-2xl font-bold"><?php echo $total_clicks; ?></div>
                                    </div>
                                </div>
                                <div class="rounded-lg border bg-card p-4 shadow-sm">
                                    <div class="flex flex-row items-center justify-between space-y-0 pb-2">
                                        <h3 class="text-sm font-medium">点击率</h3>
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-muted-foreground">
                                            <path d="M2 2v20"></path>
                                            <path d="M5.5 17h2.25v2.25"></path>
                                            <path d="M5.5 17h2.25v2.25"></path>
                                            <path d="M9 11h2.25v2.25"></path>
                                            <path d="M12.5 5h2.25v2.25"></path>
                                            <path d="M16 14h2.25v2.25"></path>
                                            <path d="M19.5 8h2.25v2.25"></path>
                                            <path d="M2 2h20"></path>
                                            <path d="M5.5 17l3-6 3 12 4-15 3 9 3-6"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-2xl font-bold"><?php echo $click_rate; ?>%</div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-card rounded-lg border p-6 shadow-sm">
                                    <h3 class="text-lg font-semibold mb-4">每日点击趋势 (折线图, 过去30天)</h3>
                                    <div class="chart-container">
                                        <canvas id="dailyLine"></canvas>
                                    </div>
                                </div>
                                <div class="bg-card rounded-lg border p-6 shadow-sm">
                                    <h3 class="text-lg font-semibold mb-4">Top 点击短码 (柱状图)</h3>
                                    <div class="chart-container">
                                        <canvas id="topBar"></canvas>
                                    </div>
                                </div>
                                <div class="bg-card rounded-lg border p-6 shadow-sm">
                                    <h3 class="text-lg font-semibold mb-4">近7日注册量 (折线图)</h3>
                                    <div class="chart-container">
                                        <canvas id="regLine"></canvas>
                                    </div>
                                </div>
                                <div class="bg-card rounded-lg border p-6 shadow-sm">
                                    <h3 class="text-lg font-semibold mb-4">近7日新建链接量 (折线图)</h3>
                                    <div class="chart-container">
                                        <canvas id="newLinksLine"></canvas>
                                    </div>
                                </div>
                            </div>
                        </section>
                    <?php elseif ($active_tab === 'links'): ?>
                        <section>
                            <h2 class="text-2xl font-bold mb-4">链接管理</h2>
                            <div class="flex space-x-4 mb-4">
                                <button onclick="openAddLinkModal()" class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground hover:bg-primary/90 h-10 px-4 py-2">+ 添加链接</button>
                                <form method="post" class="inline">
                                    <input type="hidden" name="action" value="delete_expired">
                                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <button type="submit" class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 bg-destructive text-destructive-foreground hover:bg-destructive/90 h-10 px-4 py-2" onclick="return confirm('确定删除所有已过期链接?');">删除过期链接</button>
                                </form>
                            </div>
                            <div class="md:hidden space-y-4">
                                <?php foreach ($links as $link): ?>
                                    <div class="bg-card rounded-lg border p-4 shadow-sm">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-sm font-medium truncate max-w-[calc(100%-120px)]"><?php echo htmlspecialchars($short_domain_url . '/' . $link['shortcode']); ?></span>
                                            <div class="flex space-x-3 items-center">
                                                <button onclick="openEditLinkModal('<?php echo htmlspecialchars($link['shortcode']); ?>', '<?php echo htmlspecialchars(addslashes($link['longurl'])); ?>', <?php echo $link['enable_intermediate_page'] ? 'true' : 'false'; ?>, <?php echo $link['redirect_delay']; ?>, '<?php echo $link['link_password'] ? '***' : ''; ?>', '<?php echo $link['expiration_date'] ? htmlspecialchars($link['expiration_date']) : ''; ?>', '<?php echo $link['user_id'] ?: ''; ?>')" class="text-black hover:text-gray-700">
                                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                    </svg>
                                                </button>
                                                <button onclick="copyToClipboardText('<?php echo htmlspecialchars($short_domain_url . '/' . $link['shortcode']); ?>')" class="text-black hover:text-gray-700">
                                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.102 1.101" />
                                                    </svg>
                                                </button>
                                                <button onclick="openLinkInfoModal(<?php echo json_encode($link); ?>)" class="text-black hover:text-gray-700">
                                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </button>
                                                <form method="post" class="inline" onsubmit="return confirm('删除?');">
                                                    <input type="hidden" name="action" value="delete_link">
                                                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                    <input type="hidden" name="code" value="<?php echo htmlspecialchars($link['shortcode']); ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-900">
                                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <svg class="h-5 w-5 text-muted-foreground flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.102 1.101" />
                                            </svg>
                                            <span class="text-sm truncate max-w-[calc(100%-28px)]" title="<?php echo htmlspecialchars($link['longurl']); ?>"><?php echo htmlspecialchars($link['longurl']); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="hidden md:block overflow-x-auto">
                                <div class="inline-block min-w-full align-middle">
                                    <div class="overflow-hidden border border-border rounded-lg shadow-sm">
                                        <table class="min-w-full divide-y divide-border">
                                            <thead>
                                                <tr>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">短链接</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">长链接</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">用户ID</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">点击量</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">创建时间</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">过期时间</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">中继页</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">延迟</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">密码</th>
                                                    <th scope="col" class="relative px-6 py-3">
                                                        <span class="sr-only">操作</span>
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-card divide-y divide-border">
                                                <?php foreach ($links as $link): ?>
                                                    <tr>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-foreground">
                                                            <div class="flex items-center space-x-2">
                                                                <span class="truncate max-w-[200px]"><?php echo htmlspecialchars($short_domain_url . '/' . $link['shortcode']); ?></span>
                                                                <button onclick="copyToClipboardText('<?php echo htmlspecialchars($short_domain_url . '/' . $link['shortcode']); ?>')" class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-9 px-3">复制</button>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-4 text-sm text-foreground">
                                                            <div class="truncate max-w-[300px]" title="<?php echo htmlspecialchars($link['longurl']); ?>"><?php echo htmlspecialchars($link['longurl']); ?></div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-muted-foreground"><?php echo $link['user_id'] ?: '匿名'; ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-muted-foreground"><?php echo $link['clicks']; ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-muted-foreground"><?php echo date('Y-m-d', strtotime($link['created_at'])); ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-muted-foreground"><?php echo $link['expiration_date'] ? date('Y-m-d', strtotime($link['expiration_date'])) : '永不过期'; ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-muted-foreground"><?php echo $link['enable_intermediate_page'] ? '开启' : '关闭'; ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-muted-foreground"><?php echo $link['redirect_delay']; ?>s</td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-muted-foreground"><?php echo $link['link_password'] ? '是' : '否'; ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                            <button onclick="openEditLinkModal('<?php echo htmlspecialchars($link['shortcode']); ?>', '<?php echo htmlspecialchars(addslashes($link['longurl'])); ?>', <?php echo $link['enable_intermediate_page'] ? 'true' : 'false'; ?>, <?php echo $link['redirect_delay']; ?>, '<?php echo $link['link_password'] ? '***' : ''; ?>', '<?php echo $link['expiration_date'] ? htmlspecialchars($link['expiration_date']) : ''; ?>', '<?php echo $link['user_id'] ?: ''; ?>')" class="text-indigo-600 hover:text-indigo-900">编辑</button>
                                                            <form method="post" class="inline ml-2" onsubmit="return confirm('删除?');">
                                                                <input type="hidden" name="action" value="delete_link">
                                                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                                <input type="hidden" name="code" value="<?php echo htmlspecialchars($link['shortcode']); ?>">
                                                                <button type="submit" class="text-red-600 hover:text-red-900">删除</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </section>
                    <?php elseif ($active_tab === 'users'): ?>
                        <section>
                            <h2 class="text-2xl font-bold mb-4">用户管理</h2>
                            <div class="md:hidden space-y-4">
                                <?php foreach ($users as $user): ?>
                                    <div class="bg-card rounded-lg border p-4 shadow-sm">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-sm font-medium truncate max-w-[calc(100%-80px)]"><?php echo htmlspecialchars($user['username']); ?></span>
                                            <div class="flex space-x-3 items-center">
                                                <a href="?tab=users&edit_user=<?php echo $user['id']; ?>" class="text-indigo-600 hover:text-indigo-900">
                                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                    </svg>
                                                </a>
                                                <form method="post" class="inline" onsubmit="return confirm('删除用户及其链接?');">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-900">
                                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <svg class="h-5 w-5 text-muted-foreground flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            <span class="text-sm truncate max-w-[calc(100%-28px)]"><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="hidden md:block overflow-x-auto">
                                <div class="inline-block min-w-full align-middle">
                                    <div class="overflow-hidden border border-border rounded-lg shadow-sm">
                                        <table class="min-w-full divide-y divide-border">
                                            <thead>
                                                <tr>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">用户名</th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">创建时间</th>
                                                    <th scope="col" class="relative px-6 py-3">
                                                        <span class="sr-only">操作</span>
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-card divide-y divide-border">
                                                <?php foreach ($users as $user): ?>
                                                    <tr>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-foreground"><?php echo htmlspecialchars($user['username']); ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-muted-foreground"><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                            <a href="?tab=users&edit_user=<?php echo $user['id']; ?>" class="text-indigo-600 hover:text-indigo-900">编辑</a>
                                                            <form method="post" class="inline ml-2" onsubmit="return confirm('删除用户及其链接?');">
                                                                <input type="hidden" name="action" value="delete_user">
                                                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                                <button type="submit" class="text-red-600 hover:text-red-900">删除</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <?php if ($edit_user_id && $edit_user): ?>
                                <div class="mt-8">
                                    <h3 class="text-xl font-bold mb-4">编辑用户: <?php echo htmlspecialchars($edit_user['username']); ?></h3>
                                    <form method="post">
                                        <input type="hidden" name="action" value="edit_user">
                                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $edit_user_id; ?>">
                                        <div class="space-y-4">
                                            <div>
                                                <label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">新用户名</label>
                                                <input type="text" name="new_username" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" value="<?php echo htmlspecialchars($edit_user['username']); ?>" required>
                                            </div>
                                            <div>
                                                <label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">新密码（可选，留空不修改）</label>
                                                <input type="password" name="new_password" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" placeholder="新密码">
                                            </div>
                                            <button type="submit" class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground hover:bg-primary/90 h-10 px-4 py-2">保存更改</button>
                                        </div>
                                    </form>
                                    <h4 class="text-lg font-semibold mt-6 mb-4">用户链接列表</h4>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full bg-card rounded-lg border border-border shadow-sm">
                                            <thead>
                                                <tr class="border-b border-border">
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">短链接</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">长链接</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">点击量</th>
                                                    <th class="px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase tracking-wider">操作</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-border">
                                                <?php foreach ($user_links as $link): ?>
                                                    <tr>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="text-sm font-medium"><?php echo htmlspecialchars($short_domain_url . '/' . $link['shortcode']); ?></div>
                                                        </td>
                                                        <td class="px-6 py-4">
                                                            <div class="text-sm text-muted-foreground truncate max-w-xs" title="<?php echo htmlspecialchars($link['longurl']); ?>"><?php echo htmlspecialchars($link['longurl']); ?></div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-muted-foreground"><?php echo $link['clicks']; ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                            <button onclick="openEditLinkModal('<?php echo htmlspecialchars($link['shortcode']); ?>', '<?php echo htmlspecialchars(addslashes($link['longurl'])); ?>', <?php echo $link['enable_intermediate_page'] ? 'true' : 'false'; ?>, <?php echo $link['redirect_delay']; ?>, '<?php echo $link['link_password'] ? '***' : ''; ?>', '<?php echo $link['expiration_date'] ? htmlspecialchars($link['expiration_date']) : ''; ?>', '<?php echo $link['user_id'] ?: ''; ?>')" class="text-indigo-600 hover:text-indigo-900">编辑</button>
                                                            <form method="post" class="inline ml-2" onsubmit="return confirm('删除?');">
                                                                <input type="hidden" name="action" value="delete_link">
                                                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                                <input type="hidden" name="code" value="<?php echo htmlspecialchars($link['shortcode']); ?>">
                                                                <button type="submit" class="text-red-600 hover:text-red-900">删除</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </section>
                    <?php elseif ($active_tab === 'settings'): ?>
                        <section>
                            <h2 class="text-2xl font-bold mb-4">系统设置</h2>
                            <form method="post">
                                <input type="hidden" name="action" value="settings">
                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <div class="space-y-6">
                                    <div class="rounded-md border bg-card p-6 shadow-sm">
                                        <h3 class="text-lg font-semibold mb-4">通用设置</h3>
                                        <div class="space-y-4">
                                            <div class="flex items-center justify-between">
                                                <div class="space-y-0.5">
                                                    <label class="text-sm font-medium">允许未注册用户使用</label>
                                                    <p class="text-xs text-muted-foreground">允许未登录用户创建短链接，默认开启。</p>
                                                </div>
                                                <div class="flex items-center space-x-2">
                                                    <label class="switch">
                                                        <input type="checkbox" name="allow_guest" <?php echo ($settings['allow_guest'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                                                        <span class="slider"></span>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <div class="space-y-0.5">
                                                    <label class="text-sm font-medium">允许用户注册</label>
                                                    <p class="text-xs text-muted-foreground">允许新用户注册账号，默认开启。</p>
                                                </div>
                                                <div class="flex items-center space-x-2">
                                                    <label class="switch">
                                                        <input type="checkbox" name="allow_register" <?php echo ($settings['allow_register'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                                                        <span class="slider"></span>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <div class="space-y-0.5">
                                                    <label class="text-sm font-medium">私有模式（仅管理员）</label>
                                                    <p class="text-xs text-muted-foreground">仅允许管理员访问和创建短链接，默认关闭。</p>
                                                </div>
                                                <div class="flex items-center space-x-2">
                                                    <label class="switch">
                                                        <input type="checkbox" name="private_mode" <?php echo ($settings['private_mode'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
                                                        <span class="slider"></span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="rounded-md border bg-card p-6 shadow-sm">
                                        <h3 class="text-lg font-semibold mb-4">安全设置</h3>
                                        <div class="space-y-4">
                                            <div class="flex items-center justify-between">
                                                <div class="space-y-0.5">
                                                    <label class="text-sm font-medium">启用Cloudflare Turnstile</label>
                                                    <p class="text-xs text-muted-foreground">启用CAPTCHA验证以防止机器人滥用，默认关闭。</p>
                                                </div>
                                                <div class="flex items-center space-x-2">
                                                    <label class="switch">
                                                        <input type="checkbox" name="turnstile_enabled" <?php echo ($settings['turnstile_enabled'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
                                                        <span class="slider"></span>
                                                    </label>
                                                </div>
                                            </div>
                                            <div>
                                                <label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">Turnstile Site Key</label>
                                                <p class="text-xs text-muted-foreground mb-2">Cloudflare Turnstile的站点密钥，用于CAPTCHA验证。</p>
                                                <input type="text" name="turnstile_site_key" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" value="<?php echo htmlspecialchars($settings['turnstile_site_key'] ?? ''); ?>">
                                            </div>
                                            <div>
                                                <label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">Turnstile Secret Key</label>
                                                <p class="text-xs text-muted-foreground mb-2">Cloudflare Turnstile的密钥，用于服务器端验证。</p>
                                                <input type="text" name="turnstile_secret_key" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" value="<?php echo htmlspecialchars($settings['turnstile_secret_key'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="rounded-md border bg-card p-6 shadow-sm">
                                        <h3 class="text-lg font-semibold mb-4">域名设置</h3>
                                        <div class="space-y-4">
                                            <div class="flex items-center justify-between">
                                                <div class="space-y-0.5">
                                                    <label class="text-sm font-medium">启用双域名模式</label>
                                                    <p class="text-xs text-muted-foreground">使用单独的短链接域名，关闭则使用官网域名，默认关闭。</p>
                                                </div>
                                                <div class="flex items-center space-x-2">
                                                    <label class="switch">
                                                        <input type="checkbox" name="enable_dual_domain" <?php echo ($settings['enable_dual_domain'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
                                                        <span class="slider"></span>
                                                    </label>
                                                </div>
                                            </div>
                                            <div>
                                                <label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">官网域名</label>
                                                <p class="text-xs text-muted-foreground mb-2">网站主域名，例如example.com，用于页面显示。</p>
                                                <input type="text" name="official_domain" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" value="<?php echo htmlspecialchars($settings['official_domain'] ?? ''); ?>">
                                            </div>
                                            <div>
                                                <label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">短链接域名</label>
                                                <p class="text-xs text-muted-foreground mb-2">短链接使用的域名，例如zuz.asia，仅在双域名模式启用时生效。</p>
                                                <input type="text" name="short_domain" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" value="<?php echo htmlspecialchars($settings['short_domain'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="rounded-md border bg-card p-6 shadow-sm">
                                        <h3 class="text-lg font-semibold mb-4">站点外观</h3>
                                        <div class="space-y-4">
                                            <div>
                                                <label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">站点标题</label>
                                                <p class="text-xs text-muted-foreground mb-2">网站的标题，显示在页面标题栏和SEO标签中。</p>
                                                <input type="text" name="site_title" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" value="<?php echo htmlspecialchars($settings['site_title'] ?? ''); ?>">
                                            </div>
                                            <div>
                                                <label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">Header标题</label>
                                                <p class="text-xs text-muted-foreground mb-2">导航栏显示的标题，默认为站点标题。</p>
                                                <input type="text" name="header_title" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" value="<?php echo htmlspecialchars($settings['header_title'] ?? ''); ?>">
                                            </div>
                                            <div>
                                                <label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">首页简介</label>
                                                <p class="text-xs text-muted-foreground mb-2">首页显示的网站描述，用于介绍服务内容。</p>
                                                <textarea name="home_description" class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"><?php echo htmlspecialchars($settings['home_description'] ?? ''); ?></textarea>
                                            </div>
                                            <div>
                                                <label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">首页展示图URL</label>
                                                <p class="text-xs text-muted-foreground mb-2">首页显示的图片URL，用于美化首页。</p>
                                                <input type="text" name="home_image_url" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" value="<?php echo htmlspecialchars($settings['home_image_url'] ?? ''); ?>">
                                            </div>
                                            <div>
                                                <label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">中继页Logo URL</label>
                                                <p class="text-xs text-muted-foreground mb-2">中继页面显示的Logo图片URL，留空则不显示。</p>
                                                <input type="text" name="intermediate_logo_url" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" value="<?php echo htmlspecialchars($settings['intermediate_logo_url'] ?? ''); ?>">
                                            </div>
                                            <div>
                                                <label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">中继页文案</label>
                                                <p class="text-xs text-muted-foreground mb-2">中继页面显示的提示文本，默认为“您将被重定向到以下链接:”。</p>
                                                <textarea name="intermediate_text" class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"><?php echo htmlspecialchars($settings['intermediate_text'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex justify-end">
                                        <button type="submit" class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground hover:bg-primary/90 h-10 px-4 py-2">保存设置</button>
                                    </div>
                                </div>
                            </form>
                        </section>
                    <?php endif; ?>
                </main>
            </div>
        </div>
    <?php endif; ?>

    <div id="addLinkModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 transition-opacity duration-300">
        <div class="bg-card rounded-lg border p-6 max-w-md w-full mx-4 transform scale-95 opacity-0 transition-all duration-300 shadow-xl" id="addLinkModalContent">
            <h3 class="text-lg font-semibold mb-4">添加新链接</h3>
            <form method="post">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="space-y-4">
                    <div>
                        <label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">长链接</label>
                        <input type="url" name="url" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" placeholder="https://example.com" required>
                    </div>
                    <div>
                        <label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">自定义短码（可选）</label>
                        <input type="text" name="custom_code" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" placeholder="自定义短码" maxlength="10">
                    </div>
                    <div>
                        <label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">用户ID（可选）</label>
                        <input type="number" name="user_id" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" placeholder="用户ID">
                    </div>
                    <div class="flex items-center space-x-2">
                        <label class="switch">
                            <input type="checkbox" name="enable_intermediate">
                            <span class="slider"></span>
                        </label>
                        <div class="space-y-0.5">
                            <label class="text-sm font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70">开启转跳中继页</label>
                        </div>
                    </div>
                    <div>
                        <label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">转跳延迟（秒，可选）</label>
                        <input type="number" name="redirect_delay" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" min="0" value="0">
                    </div>
                    <div>
                        <label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">链接密码（可选）</label>
                        <input type="password" name="link_password" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" placeholder="设置密码以加密链接">
                    </div>
                    <div>
                        <label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">过期日期（可选）</label>
                        <input type="date" name="expiration" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                    </div>
                    <div class="flex gap-2">
                        <button type="button" onclick="closeAddLinkModal()" class="flex h-10 items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium shadow-sm transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 flex-1">取消</button>
                        <button type="submit" class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground hover:bg-primary/90 h-10 px-4 py-2 flex-1">添加</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div id="editLinkModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 transition-opacity duration-300">
        <div class="bg-card rounded-lg border p-6 max-w-md w-full mx-4 transform scale-95 opacity-0 transition-all duration-300 shadow-xl" id="editLinkModalContent">
            <h3 class="text-lg font-semibold mb-4">编辑链接</h3>
            <form method="post">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="code" id="editLinkCode">
                <div class="space-y-4">
                    <div>
                        <label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">长链接</label>
                        <input type="url" name="newurl" id="editLinkUrl" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" required>
                    </div>
                    <div>
                        <label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">用户ID</label>
                        <input type="number" name="user_id" id="editLinkUserId" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" placeholder="用户ID">
                    </div>
                    <div class="flex items-center space-x-2">
                        <label class="switch">
                            <input type="checkbox" name="enable_intermediate" id="editLinkIntermediate">
                            <span class="slider"></span>
                        </label>
                        <div class="space-y-0.5">
                            <label class="text-sm font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70">开启转跳中继页</label>
                        </div>
                    </div>
                    <div>
                        <label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">转跳延迟（秒，可选）</label>
                        <input type="number" name="redirect_delay" id="editLinkDelay" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" min="0">
                    </div>
                    <div>
                        <label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">链接密码（可选，留空不修改）</label>
                        <input type="password" name="link_password" id="editLinkPassword" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" placeholder="新密码">
                    </div>
                    <div>
                        <label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">过期日期（可选）</label>
                        <input type="date" name="expiration" id="editLinkExpiration" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                    </div>
                    <div class="flex gap-2">
                        <button type="button" onclick="closeEditLinkModal()" class="flex h-10 items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium shadow-sm transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 flex-1">取消</button>
                        <button type="submit" class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground hover:bg-primary/90 h-10 px-4 py-2 flex-1">保存</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div id="linkInfoModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 transition-opacity duration-300">
        <div class="bg-card rounded-lg border p-6 max-w-md w-full mx-4 transform scale-95 opacity-0 transition-all duration-300 shadow-xl" id="linkInfoModalContent">
            <h3 class="text-lg font-semibold mb-4">链接详情</h3>
            <div class="space-y-2 text-sm">
                <p><span class="font-medium">点击量:</span> <span id="infoClicks"></span></p>
                <p><span class="font-medium">创建时间:</span> <span id="infoCreated"></span></p>
                <p><span class="font-medium">过期时间:</span> <span id="infoExpiration"></span></p>
                <p><span class="font-medium">中继页:</span> <span id="infoIntermediate"></span></p>
                <p><span class="font-medium">延迟:</span> <span id="infoDelay"></span>s</p>
                <p><span class="font-medium">密码保护:</span> <span id="infoPassword"></span></p>
            </div>
            <div class="mt-4 flex justify-end">
                <button onclick="closeLinkInfoModal()" class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 bg-secondary text-secondary-foreground hover:bg-secondary/80 h-10 px-4 py-2">关闭</button>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
        }

        function openAddLinkModal() {
            const modal = document.getElementById('addLinkModal');
            const content = document.getElementById('addLinkModalContent');
            modal.classList.remove('hidden');
            setTimeout(() => {
                content.classList.remove('scale-95', 'opacity-0');
            }, 10);
        }

        function closeAddLinkModal() {
            const modal = document.getElementById('addLinkModal');
            const content = document.getElementById('addLinkModalContent');
            content.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        function openEditLinkModal(code, url, enableIntermediate, delay, password, expiration, userId) {
            document.getElementById('editLinkCode').value = code;
            document.getElementById('editLinkUrl').value = url;
            document.getElementById('editLinkIntermediate').checked = enableIntermediate;
            document.getElementById('editLinkDelay').value = delay;
            document.getElementById('editLinkPassword').value = password;
            document.getElementById('editLinkExpiration').value = expiration ? expiration.split(' ')[0] : '';
            document.getElementById('editLinkUserId').value = userId;
            const modal = document.getElementById('editLinkModal');
            const content = document.getElementById('editLinkModalContent');
            modal.classList.remove('hidden');
            setTimeout(() => {
                content.classList.remove('scale-95', 'opacity-0');
            }, 10);
        }

        function closeEditLinkModal() {
            const modal = document.getElementById('editLinkModal');
            const content = document.getElementById('editLinkModalContent');
            content.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        function copyToClipboardText(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('已复制');
            });
        }

        function openLinkInfoModal(link) {
            document.getElementById('infoClicks').textContent = link.clicks;
            document.getElementById('infoCreated').textContent = new Date(link.created_at).toLocaleString();
            document.getElementById('infoExpiration').textContent = link.expiration_date ? new Date(link.expiration_date).toLocaleDateString() : '永不过期';
            document.getElementById('infoIntermediate').textContent = link.enable_intermediate_page ? '开启' : '关闭';
            document.getElementById('infoDelay').textContent = link.redirect_delay;
            document.getElementById('infoPassword').textContent = link.link_password ? '是' : '否';
            const modal = document.getElementById('linkInfoModal');
            const content = document.getElementById('linkInfoModalContent');
            modal.classList.remove('hidden');
            setTimeout(() => {
                content.classList.remove('scale-95', 'opacity-0');
            }, 10);
        }

        function closeLinkInfoModal() {
            const modal = document.getElementById('linkInfoModal');
            const content = document.getElementById('linkInfoModalContent');
            content.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        window.onclick = function(event) {
            const addLinkModal = document.getElementById('addLinkModal');
            const editLinkModal = document.getElementById('editLinkModal');
            const linkInfoModal = document.getElementById('linkInfoModal');
            if (event.target === addLinkModal) closeAddLinkModal();
            if (event.target === editLinkModal) closeEditLinkModal();
            if (event.target === linkInfoModal) closeLinkInfoModal();
        }

        // 图表渲染 (仅 dashboard tab)
        <?php if ($active_tab === 'dashboard'): ?>
        const colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#E7E9ED', '#C9CBCF', '#ADFF2F', '#20B2AA'];

        // Top 短码柱状图
        const topLabels = <?php echo json_encode(array_column($top_links, 'shortcode')); ?>;
        const topData = <?php echo json_encode(array_column($top_links, 'clicks')); ?>;
        new Chart(document.getElementById('topBar'), {
            type: 'bar',
            data: {
                labels: topLabels,
                datasets: [{
                    label: '点击量',
                    data: topData,
                    backgroundColor: colors[0]
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // 每日点击折线图
        const dailyLabels = <?php echo json_encode(array_keys($daily_clicks)); ?>;
        const dailyData = <?php echo json_encode(array_values($daily_clicks)); ?>;
        new Chart(document.getElementById('dailyLine'), {
            type: 'line',
            data: {
                labels: dailyLabels,
                datasets: [{
                    label: '每日点击',
                    data: dailyData,
                    borderColor: colors[1],
                    fill: false
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // 近7日注册折线图
        const regLabels = <?php echo json_encode(array_keys($registrations)); ?>;
        const regData = <?php echo json_encode(array_values($registrations)); ?>;
        new Chart(document.getElementById('regLine'), {
            type: 'line',
            data: {
                labels: regLabels,
                datasets: [{
                    label: '每日注册',
                    data: regData,
                    borderColor: colors[2],
                    fill: false
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // 近7日新建链接折线图
        const newLinksLabels = <?php echo json_encode(array_keys($new_links)); ?>;
        const newLinksData = <?php echo json_encode(array_values($new_links)); ?>;
        new Chart(document.getElementById('newLinksLine'), {
            type: 'line',
            data: {
                labels: newLinksLabels,
                datasets: [{
                    label: '每日新建链接',
                    data: newLinksData,
                    borderColor: colors[3],
                    fill: false
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>