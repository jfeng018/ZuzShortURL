<?php
require_once 'config.php';

$csrf_token = generate_csrf_token();
$error = '';
$success = '';
$links = [];
$users = [];
$show_list = false;
$input_token = $_POST['token'] ?? '';
$valid_token = hash_equals($admin_token, $input_token);
$active_tab = $_GET['tab'] ?? 'dashboard';

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
                $private_mode = isset($_POST['private_mode']);
                $allow_guest = isset($_POST['allow_guest']);
                $allow_register = isset($_POST['allow_register']);
                set_setting($pdo, 'private_mode', $private_mode);
                set_setting($pdo, 'allow_guest', $allow_guest);
                set_setting($pdo, 'allow_register', $allow_register);
                $success = '设置更新成功。';
                break;
            case 'logout':
                unset($_SESSION['admin_auth']);
                $show_list = false;
                $success = '已登出。';
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
}
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

        .mobile-menu {
            display: none;
            z-index: 50;
        }

        @media (max-width: 768px) {
            .desktop-menu {
                display: none;
            }
            .mobile-menu {
                display: block;
            }
        }

        .mobile-menu {
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 40px;
            height: 20px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 20px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 2px;
            bottom: 2px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #2196F3;
        }

        input:checked + .slider:before {
            transform: translateX(20px);
        }
    </style>
</head>
<body class="bg-background text-foreground min-h-screen">
    <nav class="bg-card border-b border-border px-4 py-4 fixed top-0 w-full z-40">
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
            <div class="bg-destructive/10 border border-destructive/30 text-destructive px-4 py-3 rounded-md mb-4"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-secondary/50 border border-secondary/30 text-secondary-foreground px-4 py-3 rounded-md mb-4"><?php echo htmlspecialchars($success); ?></div>
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
                    <a href="?tab=dashboard" class="px-4 py-2 -mb-px <?php echo $active_tab === 'dashboard' ? 'border-primary text-primary' : 'text-muted-foreground'; ?>">数据看板</a>
                    <a href="?tab=links" class="px-4 py-2 -mb-px <?php echo $active_tab === 'links' ? 'border-primary text-primary' : 'text-muted-foreground'; ?>">链接管理</a>
                    <a href="?tab=users" class="px-4 py-2 -mb-px <?php echo $active_tab === 'users' ? 'border-primary text-primary' : 'text-muted-foreground'; ?>">用户管理</a>
                    <a href="?tab=settings" class="px-4 py-2 -mb-px <?php echo $active_tab === 'settings' ? 'border-primary text-primary' : 'text-muted-foreground'; ?>">系统设置</a>
                </div>
            </div>
            <?php if ($active_tab === 'dashboard'): ?>
                <section>
                    <h2 class="text-2xl font-bold mb-4">数据看板</h2>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="bg-card rounded-lg border p-6 text-center">
                            <h3 class="text-lg font-semibold text-muted-foreground">注册用户数</h3>
                            <p class="text-3xl font-bold"><?php echo $total_users; ?></p>
                        </div>
                        <div class="bg-card rounded-lg border p-6 text-center">
                            <h3 class="text-lg font-semibold text-muted-foreground">总链接量</h3>
                            <p class="text-3xl font-bold"><?php echo $total_links; ?></p>
                        </div>
                        <div class="bg-card rounded-lg border p-6 text-center">
                            <h3 class="text-lg font-semibold text-muted-foreground">总点击量</h3>
                            <p class="text-3xl font-bold"><?php echo $total_clicks; ?></p>
                        </div>
                        <div class="bg-card rounded-lg border p-6 text-center">
                            <h3 class="text-lg font-semibold text-muted-foreground">点击率</h3>
                            <p class="text-3xl font-bold"><?php echo $click_rate; ?>%</p>
                        </div>
                    </div>
                </section>
            <?php elseif ($active_tab === 'links'): ?>
                <section>
                    <h2 class="text-2xl font-bold mb-4">链接管理</h2>
                    <button onclick="openAddLinkModal()" class="mb-4 px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90">+ 添加链接</button>
                    <div class="md:hidden space-y-4">
                        <?php foreach ($links as $link): ?>
                            <div class="bg-card rounded-lg border p-4">
                                <div class="flex items-center space-x-2 mb-2">
                                    <input type="text" value="<?php echo htmlspecialchars($base_url . '/' . $link['shortcode']); ?>" readonly class="flex-1 px-3 py-1 border border-input rounded-md bg-background text-sm font-mono" id="short_<?php echo htmlspecialchars($link['shortcode']); ?>">
                                    <button onclick="copyToClipboard('short_<?php echo htmlspecialchars($link['shortcode']); ?>')" class="px-2 py-1 bg-secondary text-secondary-foreground rounded text-xs">复制</button>
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
                                    <button onclick="openEditLinkModal('<?php echo htmlspecialchars($link['shortcode']); ?>', '<?php echo htmlspecialchars(addslashes($link['longurl'])); ?>', <?php echo $link['enable_intermediate_page'] ? 'true' : 'false'; ?>, <?php echo $link['redirect_delay']; ?>, '<?php echo $link['link_password'] ? '***' : ''; ?>', '<?php echo $link['expiration_date'] ? htmlspecialchars($link['expiration_date']) : ''; ?>', '<?php echo $link['user_id'] ?: ''; ?>')" class="flex-1 px-3 py-1 bg-primary text-primary-foreground rounded text-xs">编辑</button>
                                    <form method="post" class="flex-1 inline" onsubmit="return confirm('删除?');">
                                        <input type="hidden" name="action" value="delete_link">
                                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <input type="hidden" name="code" value="<?php echo htmlspecialchars($link['shortcode']); ?>">
                                        <button type="submit" class="w-full px-3 py-1 bg-destructive text-destructive-foreground rounded text-xs">删除</button>
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
                                                <input type="text" value="<?php echo htmlspecialchars($base_url . '/' . $link['shortcode']); ?>" readonly class="px-3 py-1 border border-input rounded-md bg-background text-sm font-mono" id="short_<?php echo htmlspecialchars($link['shortcode']); ?>">
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
                                <span class="text-sm font-medium">允许未注册用户使用（默认允许）</span>
                                <label class="switch">
                                    <input type="checkbox" name="allow_guest" <?php echo get_setting($pdo, 'allow_guest') ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium">允许用户注册（默认允许）</span>
                                <label class="switch">
                                    <input type="checkbox" name="allow_register" <?php echo get_setting($pdo, 'allow_register') ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <button type="submit" class="w-full px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90">保存设置</button>
                        </div>
                    </form>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <div id="addLinkModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-card rounded-lg border p-6 max-w-md w-full mx-4">
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
                        <button type="button" onclick="closeAddLinkModal()" class="flex-1 bg-secondary text-secondary-foreground py-2 px-4 rounded-md hover:bg-secondary/80">取消</button>
                        <button type="submit" class="flex-1 bg-primary text-primary-foreground py-2 px-4 rounded-md hover:bg-primary/90">添加</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div id="editLinkModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-card rounded-lg border p-6 max-w-md w-full mx-4">
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
                        <button type="button" onclick="closeEditLinkModal()" class="flex-1 bg-secondary text-secondary-foreground py-2 px-4 rounded-md hover:bg-secondary/80">取消</button>
                        <button type="submit" class="flex-1 bg-primary text-primary-foreground py-2 px-4 rounded-md hover:bg-primary/90">保存</button>
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
            document.getElementById('addLinkModal').classList.remove('hidden');
        }

        function closeAddLinkModal() {
            document.getElementById('addLinkModal').classList.add('hidden');
        }

        function openEditLinkModal(code, url, enableIntermediate, delay, password, expiration, userId) {
            document.getElementById('editLinkCode').value = code;
            document.getElementById('editLinkUrl').value = url;
            document.getElementById('editLinkIntermediate').checked = enableIntermediate;
            document.getElementById('editLinkDelay').value = delay;
            document.getElementById('editLinkPassword').value = password;
            document.getElementById('editLinkExpiration').value = expiration ? expiration.split(' ')[0] : '';
            document.getElementById('editLinkUserId').value = userId;
            document.getElementById('editLinkModal').classList.remove('hidden');
        }

        function closeEditLinkModal() {
            document.getElementById('editLinkModal').classList.add('hidden');
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
    </script>
</body>
</html>
<?php
exit;
?>