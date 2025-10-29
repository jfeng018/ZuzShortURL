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
</style>
</head>
<body class="bg-background text-foreground min-h-screen">
    <nav class="bg-card border-b border-border px-4 py-4 fixed top-0 w-full z-40 backdrop-filter backdrop-blur-md transition-all duration-300">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold">Zuz.Asia - 管理面板</h1>
            <button onclick="toggleMobileMenu()" class="md:hidden px-4 py-2 bg-primary text-primary-foreground rounded-md">菜单</button>
            <div class="hidden md:flex space-x-4 desktop-menu">
                <a href="?tab=dashboard" class="px-4 py-2 rounded-md <?php echo $active_tab === 'dashboard' ? 'bg-primary text-primary-foreground' : 'bg-secondary'; ?>">数据看板</a>
                <a href="?tab=links" class="px-4 py-2 rounded-md <?php echo $active_tab === 'links' ? 'bg-primary text-primary-foreground' : 'bg-secondary'; ?>">链接管理</a>
                <a href="?tab=users" class="px-4 py-2 rounded-md <?php echo $active_tab === 'users' ? 'bg-primary text-primary-foreground' : 'bg-secondary'; ?>">用户管理</a>
                <a href="?tab=settings" class="px-4 py-2 rounded-md <?php echo $active_tab === 'settings' ? 'bg-primary text-primary-foreground' : 'bg-secondary'; ?>">系统设置</a>
                <form method="post" class="inline">
                    <input type="hidden" name="action" value="logout">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <button type="submit" class="px-4 py-2 bg-destructive text-destructive-foreground rounded-md">登出</button>
                </form>
            </div>
            <div id="mobileMenu" class="hidden absolute top-16 right-4 md:hidden bg-card rounded-lg border p-4 space-y-2 mobile-menu">
                <a href="?tab=dashboard" class="block px-4 py-2 rounded-md <?php echo $active_tab === 'dashboard' ? 'bg-primary text-primary-foreground' : 'bg-secondary'; ?>">数据看板</a>
                <a href="?tab=links" class="block px-4 py-2 rounded-md <?php echo $active_tab === 'links' ? 'bg-primary text-primary-foreground' : 'bg-secondary'; ?>">链接管理</a>
                <a href="?tab=users" class="block px-4 py-2 rounded-md <?php echo $active_tab === 'users' ? 'bg-primary text-primary-foreground' : 'bg-secondary'; ?>">用户管理</a>
                <a href="?tab=settings" class="block px-4 py-2 rounded-md <?php echo $active_tab === 'settings' ? 'bg-primary text-primary-foreground' : 'bg-secondary'; ?>">系统设置</a>
                <form method="post" class="block">
                    <input type="hidden" name="action" value="logout">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <button type="submit" class="w-full px-4 py-2 bg-destructive text-destructive-foreground rounded-md">登出</button>
                </form>
            </div>
        </div>
    </nav>
    <main class="container mx-auto p-4 pt-20">
        <?php if ($error): ?>
            <div class="bg-destructive/10 border border-destructive/30 text-destructive px-4 py-3 rounded-md mb-4 backdrop-filter backdrop-blur-sm transition-all duration-300"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-secondary/50 border border-secondary/30 text-secondary-foreground px-4 py-3 rounded-md mb-4 backdrop-filter backdrop-blur-sm transition-all duration-300"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (!$show_list): ?>
            <div class="max-w-md mx-auto">
                <div class="bg-card rounded-lg border p-6">
                    <h2 class="text-xl font-semibold mb-4">输入管理令牌</h2>
                    <form method="post">
                        <input type="hidden" name="action" value="login">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <div class="mb-4">
                            <input type="password" name="token" class="w-full px-3 py-2 border border-input rounded-md" placeholder="管理令牌" required>
                        </div>
                        <button type="submit" class="w-full bg-primary text-primary-foreground py-2 rounded-md hover:bg-primary/90">访问面板</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="mb-8">
                <div class="tabs flex space-x-4 border-b border-border">
                    <a href="?tab=dashboard" class="px-4 py-2 -mb-px <?php echo $active_tab === 'dashboard' ? 'border-primary text-primary' : 'text-muted-foreground'; ?>">数据</a>
                    <a href="?tab=links" class="px-4 py-2 -mb-px <?php echo $active_tab === 'links' ? 'border-primary text-primary' : 'text-muted-foreground'; ?>">链接</a>
                    <a href="?tab=users" class="px-4 py-2 -mb-px <?php echo $active_tab === 'users' ? 'border-primary text-primary' : 'text-muted-foreground'; ?>">用户</a>
                    <a href="?tab=settings" class="px-4 py-2 -mb-px <?php echo $active_tab === 'settings' ? 'border-primary text-primary' : 'text-muted-foreground'; ?>">系统</a>
                </div>
            </div>
            <?php if ($active_tab === 'dashboard'): ?>
                <section>
                    <h2 class="text-2xl font-bold mb-4">数据看板</h2>
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-lg border bg-card p-4">
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
                        <div class="rounded-lg border bg-card p-4">
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
                        <div class="rounded-lg border bg-card p-4">
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
                        <div class="rounded-lg border bg-card p-4">
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
                        <div class="bg-card rounded-lg border p-6">
                            <h3 class="text-lg font-semibold mb-4">每日点击趋势 (折线图, 过去30天)</h3>
                            <div class="chart-container">
                                <canvas id="dailyLine"></canvas>
                            </div>
                        </div>
                        <div class="bg-card rounded-lg border p-6">
                            <h3 class="text-lg font-semibold mb-4">Top 点击短码 (柱状图)</h3>
                            <div class="chart-container">
                                <canvas id="topBar"></canvas>
                            </div>
                        </div>
                        <div class="bg-card rounded-lg border p-6">
                            <h3 class="text-lg font-semibold mb-4">近7日注册量 (折线图)</h3>
                            <div class="chart-container">
                                <canvas id="regLine"></canvas>
                            </div>
                        </div>
                        <div class="bg-card rounded-lg border p-6">
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
                        <button onclick="openAddLinkModal()" class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90">+ 添加链接</button>
                        <form method="post" class="inline">
                            <input type="hidden" name="action" value="delete_expired">
                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <button type="submit" class="px-4 py-2 bg-destructive text-destructive-foreground rounded-md hover:bg-destructive/90" onclick="return confirm('确定删除所有已过期链接?');">删除过期链接</button>
                        </form>
                    </div>
                    <div class="md:hidden space-y-4">
                        <?php foreach ($links as $link): ?>
                            <div class="bg-card rounded-lg border p-4">
                                <div class="flex items-center space-x-2 mb-2">
                                    <input type="text" value="<?php echo htmlspecialchars($short_domain_url . '/' . $link['shortcode']); ?>" readonly class="flex-1 px-3 py-1 border border-input rounded-md bg-background text-sm font-mono" id="short_<?php echo htmlspecialchars($link['shortcode']); ?>">
                                    <button onclick="copyToClipboard('short_<?php echo htmlspecialchars($link['shortcode']); ?>')" class="px-2 py-2 bg-secondary text-secondary-foreground rounded text-xs">复制</button>
                                </div>
                                <p class="text-sm truncate" title="<?php echo htmlspecialchars($link['longurl']); ?>"><?php echo htmlspecialchars($link['longurl']); ?></p>
                                <div class="text-xs text-muted-foreground mt-2 space-y-1">
                                    <p>用户ID: <?php echo $link['user_id'] ?: '匿名'; ?></p>
                                    <p>点击: <?php echo $link['clicks']; ?></p>
                                    <p>创建: <?php echo date('Y-m-d', strtotime($link['created_at'])); ?></p>
                                    <p>过期: <?php echo $link['expiration_date'] ? date('Y-m-d', strtotime($link['expiration_date'])) : '永不过期'; ?></p>
                                    <p>中继页: <?php echo $link['enable_intermediate_page'] ? '开启' : '关闭'; ?></p>
                                    <p>延迟: <?php echo $link['redirect_delay']; ?>s</p>
                                    <p>密码保护: <?php echo $link['link_password'] ? '是' : '否'; ?></p>
                                </div>
                                <div class="flex space-x-2 mt-4">
                                    <button onclick="openEditLinkModal('<?php echo htmlspecialchars($link['shortcode']); ?>', '<?php echo htmlspecialchars(addslashes($link['longurl'])); ?>', <?php echo $link['enable_intermediate_page'] ? 'true' : 'false'; ?>, <?php echo $link['redirect_delay']; ?>, '<?php echo $link['link_password'] ? '***' : ''; ?>', '<?php echo $link['expiration_date'] ? htmlspecialchars($link['expiration_date']) : ''; ?>', '<?php echo $link['user_id'] ?: ''; ?>')" class="flex-1 px-3 py-2 bg-primary text-primary-foreground rounded text-sm">编辑</button>
                                    <form method="post" class="flex-1 inline" onsubmit="return confirm('删除?');">
                                        <input type="hidden" name="action" value="delete_link">
                                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <input type="hidden" name="code" value="<?php echo htmlspecialchars($link['shortcode']); ?>">
                                        <button type="submit" class="w-full px-3 py-2 bg-destructive text-destructive-foreground rounded text-sm">删除</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="hidden md:block overflow-x-auto">
                        <table class="min-w-full bg-card rounded-lg border border-border">
                            <thead>
                                <tr class="border-b border-border">
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">短链接</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">长链接</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">用户ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">点击量</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">创建时间</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">过期时间</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">中继页</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">延迟</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">密码</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase tracking-wider">操作</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <?php foreach ($links as $link): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center space-x-2">
                                                <input type="text" value="<?php echo htmlspecialchars($short_domain_url . '/' . $link['shortcode']); ?>" readonly class="px-3 py-1 border border-input rounded-md bg-background text-sm font-mono" id="short_<?php echo htmlspecialchars($link['shortcode']); ?>">
                                                <button onclick="copyToClipboard('short_<?php echo htmlspecialchars($link['shortcode']); ?>')" class="px-2 py-1 bg-secondary text-secondary-foreground rounded text-xs">复制</button>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-muted-foreground truncate max-w-xs" title="<?php echo htmlspecialchars($link['longurl']); ?>"><?php echo htmlspecialchars($link['longurl']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-muted-foreground"><?php echo $link['user_id'] ?: '匿名'; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-muted-foreground"><?php echo $link['clicks']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-muted-foreground"><?php echo date('Y-m-d', strtotime($link['created_at'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-muted-foreground"><?php echo $link['expiration_date'] ? date('Y-m-d', strtotime($link['expiration_date'])) : '永不过期'; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-muted-foreground"><?php echo $link['enable_intermediate_page'] ? '开启' : '关闭'; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-muted-foreground"><?php echo $link['redirect_delay']; ?>s</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-muted-foreground"><?php echo $link['link_password'] ? '是' : '否'; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-xs font-medium">
                                            <div class="flex space-x-2 justify-end">
                                                <button onclick="openEditLinkModal('<?php echo htmlspecialchars($link['shortcode']); ?>', '<?php echo htmlspecialchars(addslashes($link['longurl'])); ?>', <?php echo $link['enable_intermediate_page'] ? 'true' : 'false'; ?>, <?php echo $link['redirect_delay']; ?>, '<?php echo $link['link_password'] ? '***' : ''; ?>', '<?php echo $link['expiration_date'] ? htmlspecialchars($link['expiration_date']) : ''; ?>', '<?php echo $link['user_id'] ?: ''; ?>')" class="bg-primary text-primary-foreground px-3 py-1 rounded">编辑</button>
                                                <form method="post" class="inline" onsubmit="return confirm('删除?');">
                                                    <input type="hidden" name="action" value="delete_link">
                                                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                    <input type="hidden" name="code" value="<?php echo htmlspecialchars($link['shortcode']); ?>">
                                                    <button type="submit" class="bg-destructive text-destructive-foreground px-3 py-1 rounded">删除</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php elseif ($active_tab === 'users'): ?>
                <section>
                    <h2 class="text-2xl font-bold mb-4">用户管理</h2>
                    <div class="md:hidden space-y-4">
                        <?php foreach ($users as $user): ?>
                            <div class="bg-card rounded-lg border p-4">
                                <div class="flex flex-col md:flex-row md:justify-between md:items-center space-y-2">
                                    <div class="flex-1">
                                        <div class="font-semibold text-lg"><?php echo htmlspecialchars($user['username']); ?></div>
                                        <p class="text-sm text-muted-foreground">创建: <?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></p>
                                    </div>
                                    <form method="post" class="inline" onsubmit="return confirm('删除用户及其链接?');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="px-4 py-2 bg-destructive text-destructive-foreground rounded-md hover:bg-destructive/90">删除</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="hidden md:block overflow-x-auto">
                        <table class="min-w-full bg-card rounded-lg border border-border">
                            <thead>
                                <tr class="border-b border-border">
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">用户名</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">创建时间</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase tracking-wider">操作</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium"><?php echo htmlspecialchars($user['username']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-muted-foreground"><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <form method="post" class="inline" onsubmit="return confirm('删除用户及其链接?');">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="bg-destructive text-destructive-foreground px-4 py-2 rounded-md hover:bg-destructive/90">删除</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php elseif ($active_tab === 'settings'): ?>
                <section>
                    <h2 class="text-2xl font-bold mb-4">系统设置</h2>
                    <form method="post">
                        <input type="hidden" name="action" value="settings">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <div class="bg-card rounded-lg border p-6 space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-sm font-medium">允许未注册用户使用</span>
                                    <p class="text-xs text-muted-foreground">允许未登录用户创建短链接，默认开启。</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="allow_guest" <?php echo ($settings['allow_guest'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-sm font-medium">允许用户注册</span>
                                    <p class="text-xs text-muted-foreground">允许新用户注册账号，默认开启。</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="allow_register" <?php echo ($settings['allow_register'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-sm font-medium">私有模式（仅管理员）</span>
                                    <p class="text-xs text-muted-foreground">仅允许管理员访问和创建短链接，默认关闭。</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="private_mode" <?php echo ($settings['private_mode'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-sm font-medium">启用Cloudflare Turnstile</span>
                                    <p class="text-xs text-muted-foreground">启用CAPTCHA验证以防止机器人滥用，默认关闭。</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="turnstile_enabled" <?php echo ($settings['turnstile_enabled'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-sm font-medium">启用双域名模式</span>
                                    <p class="text-xs text-muted-foreground">使用单独的短链接域名，关闭则使用官网域名，默认关闭。</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="enable_dual_domain" <?php echo ($settings['enable_dual_domain'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2">Turnstile Site Key</label>
                                <p class="text-xs text-muted-foreground mb-2">Cloudflare Turnstile的站点密钥，用于CAPTCHA验证。</p>
                                <input type="text" name="turnstile_site_key" class="w-full px-3 py-2 border border-input rounded-md" value="<?php echo htmlspecialchars($settings['turnstile_site_key'] ?? ''); ?>">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2">Turnstile Secret Key</label>
                                <p class="text-xs text-muted-foreground mb-2">Cloudflare Turnstile的密钥，用于服务器端验证。</p>
                                <input type="text" name="turnstile_secret_key" class="w-full px-3 py-2 border border-input rounded-md" value="<?php echo htmlspecialchars($settings['turnstile_secret_key'] ?? ''); ?>">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2">官网域名</label>
                                <p class="text-xs text-muted-foreground mb-2">网站主域名，例如example.com，用于页面显示。</p>
                                <input type="text" name="official_domain" class="w-full px-3 py-2 border border-input rounded-md" value="<?php echo htmlspecialchars($settings['official_domain'] ?? ''); ?>">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2">短链接域名</label>
                                <p class="text-xs text-muted-foreground mb-2">短链接使用的域名，例如zuz.asia，仅在双域名模式启用时生效。</p>
                                <input type="text" name="short_domain" class="w-full px-3 py-2 border border-input rounded-md" value="<?php echo htmlspecialchars($settings['short_domain'] ?? ''); ?>">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2">站点标题</label>
                                <p class="text-xs text-muted-foreground mb-2">网站的标题，显示在页面标题栏和SEO标签中。</p>
                                <input type="text" name="site_title" class="w-full px-3 py-2 border border-input rounded-md" value="<?php echo htmlspecialchars($settings['site_title'] ?? ''); ?>">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2">Header标题</label>
                                <p class="text-xs text-muted-foreground mb-2">导航栏显示的标题，默认为站点标题。</p>
                                <input type="text" name="header_title" class="w-full px-3 py-2 border border-input rounded-md" value="<?php echo htmlspecialchars($settings['header_title'] ?? ''); ?>">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2">首页简介</label>
                                <p class="text-xs text-muted-foreground mb-2">首页显示的网站描述，用于介绍服务内容。</p>
                                <textarea name="home_description" class="w-full px-3 py-2 border border-input rounded-md"><?php echo htmlspecialchars($settings['home_description'] ?? ''); ?></textarea>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2">首页展示图URL</label>
                                <p class="text-xs text-muted-foreground mb-2">首页显示的图片URL，用于美化首页。</p>
                                <input type="text" name="home_image_url" class="w-full px-3 py-2 border border-input rounded-md" value="<?php echo htmlspecialchars($settings['home_image_url'] ?? ''); ?>">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2">中继页Logo URL</label>
                                <p class="text-xs text-muted-foreground mb-2">中继页面显示的Logo图片URL，留空则不显示。</p>
                                <input type="text" name="intermediate_logo_url" class="w-full px-3 py-2 border border-input rounded-md" value="<?php echo htmlspecialchars($settings['intermediate_logo_url'] ?? ''); ?>">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2">中继页文案</label>
                                <p class="text-xs text-muted-foreground mb-2">中继页面显示的提示文本，默认为“您将被重定向到以下链接:”。</p>
                                <textarea name="intermediate_text" class="w-full px-3 py-2 border border-input rounded-md"><?php echo htmlspecialchars($settings['intermediate_text'] ?? ''); ?></textarea>
                            </div>
                            <button type="submit" class="w-full px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90">保存设置</button>
                        </div>
                    </form>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <div id="addLinkModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 transition-opacity duration-300">
        <div class="bg-card rounded-lg border p-6 max-w-md w-full mx-4 transform scale-95 opacity-0 transition-all duration-300" id="addLinkModalContent">
            <h3 class="text-lg font-semibold mb-4">添加新链接</h3>
            <form method="post">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="space-y-3">
                    <input type="url" name="url" class="w-full px-3 py-2 border border-input rounded-md" placeholder="https://example.com" required>
                    <input type="text" name="custom_code" class="w-full px-3 py-2 border border-input rounded-md" placeholder="自定义短码（可选）" maxlength="10">
                    <input type="number" name="user_id" class="w-full px-3 py-2 border border-input rounded-md" placeholder="用户ID（可选）">
                    <div class="flex items-center justify-between">
                        <span class="text-sm">开启转跳中继页</span>
                        <label class="switch">
                            <input type="checkbox" name="enable_intermediate">
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">转跳延迟（秒，可选）</label>
                        <input type="number" name="redirect_delay" class="w-full px-3 py-2 border border-input rounded-md" min="0" value="0">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">链接密码（可选）</label>
                        <input type="password" name="link_password" class="w-full px-3 py-2 border border-input rounded-md" placeholder="设置密码以加密链接">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">过期日期（可选）</label>
                        <input type="date" name="expiration" class="w-full px-3 py-2 border border-input rounded-md">
                    </div>
                    <div class="flex gap-2">
                        <button type="button" onclick="closeAddLinkModal()" class="flex-1 bg-secondary text-secondary-foreground py-2 px-4 rounded-md">取消</button>
                        <button type="submit" class="flex-1 bg-primary text-primary-foreground py-2 px-4 rounded-md">添加</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div id="editLinkModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 transition-opacity duration-300">
        <div class="bg-card rounded-lg border p-6 max-w-md w-full mx-4 transform scale-95 opacity-0 transition-all duration-300" id="editLinkModalContent">
            <h3 class="text-lg font-semibold mb-4">编辑链接</h3>
            <form method="post">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="code" id="editLinkCode">
                <div class="space-y-3">
                    <input type="url" name="newurl" id="editLinkUrl" class="w-full px-3 py-2 border border-input rounded-md" required>
                    <input type="number" name="user_id" id="editLinkUserId" class="w-full px-3 py-2 border border-input rounded-md" placeholder="用户ID">
                    <div class="flex items-center justify-between">
                        <span class="text-sm">开启转跳中继页</span>
                        <label class="switch">
                            <input type="checkbox" name="enable_intermediate" id="editLinkIntermediate">
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">转跳延迟（秒，可选）</label>
                        <input type="number" name="redirect_delay" id="editLinkDelay" class="w-full px-3 py-2 border border-input rounded-md" min="0">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">链接密码（可选，留空不修改）</label>
                        <input type="password" name="link_password" id="editLinkPassword" class="w-full px-3 py-2 border border-input rounded-md" placeholder="新密码">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">过期日期（可选）</label>
                        <input type="date" name="expiration" id="editLinkExpiration" class="w-full px-3 py-2 border border-input rounded-md">
                    </div>
                    <div class="flex gap-2">
                        <button type="button" onclick="closeEditLinkModal()" class="flex-1 bg-secondary text-secondary-foreground py-2 px-4 rounded-md">取消</button>
                        <button type="submit" class="flex-1 bg-primary text-primary-foreground py-2 px-4 rounded-md">保存</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleMobileMenu() {
            document.getElementById('mobileMenu').classList.toggle('hidden');
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

        function copyToClipboard(id) {
            const el = document.getElementById(id);
            navigator.clipboard.writeText(el.value).then(() => {
                alert('已复制');
            });
        }

        window.onclick = function(event) {
            const addLinkModal = document.getElementById('addLinkModal');
            const editLinkModal = document.getElementById('editLinkModal');
            if (event.target === addLinkModal) closeAddLinkModal();
            if (event.target === editLinkModal) closeEditLinkModal();
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