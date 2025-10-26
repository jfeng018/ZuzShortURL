<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (get_setting($pdo, 'private_mode') === 'true' && !require_admin_auth()) {
    header('Location: /admin');
    exit;
}

if (!is_logged_in()) {
    header('Location: /login');
    exit;
}

$user_id = get_current_user_id();
$reserved_codes = ['admin', 'help', 'about', 'api', 'login', 'register', 'logout', 'dashboard'];
$csrf_token = generate_csrf_token();
$error = '';
$success = '';
$links = [];
$sort = $_GET['sort'] ?? 'time';
$order = $sort === 'clicks' ? 'clicks DESC' : 'created_at DESC';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_rate_limit($pdo);
    if (!validate_csrf_token($_POST['csrf'] ?? '')) {
        $error = 'CSRF令牌无效。';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'add') {
            if (get_setting($pdo, 'turnstile_enabled') === 'true' && !validate_captcha($_POST['cf-turnstile-response'] ?? '', $pdo)) {
                $error = 'CAPTCHA验证失败。';
            } else {
                $longurl = trim($_POST['url'] ?? '');
                $custom_code = trim($_POST['custom_code'] ?? '');
                $enable_intermediate = isset($_POST['enable_intermediate']);
                $redirect_delay = is_numeric($_POST['redirect_delay']) ? (int)$_POST['redirect_delay'] : 0;
                $link_password = trim($_POST['link_password'] ?? '');
                $password_hash = !empty($link_password) ? password_hash($link_password, PASSWORD_DEFAULT) : null;
                $expiration = $_POST['expiration'] ?? null;
                
                if ($expiration && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiration)) {
                    $error = '无效的过期日期格式。';
                }
                
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
                        $stmt->execute([$code, $longurl, $user_id, $enable_str, $redirect_delay, $password_hash, $expiration ?: null]);
                        $success = '链接添加成功。';
                    }
                }
            }
        } elseif ($action === 'edit') {
            $code = $_POST['code'] ?? '';
            $newurl = trim($_POST['newurl'] ?? '');
            $enable_intermediate = isset($_POST['enable_intermediate']);
            $redirect_delay = is_numeric($_POST['redirect_delay']) ? (int)$_POST['redirect_delay'] : 0;
            $link_password = trim($_POST['link_password'] ?? '');
            $password_hash = !empty($link_password) ? password_hash($link_password, PASSWORD_DEFAULT) : null;
            $expiration = $_POST['expiration'] ?? null;
            
            if ($expiration && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiration)) {
                $error = '无效的过期日期格式。';
            }
            
            $stmt = $pdo->prepare("SELECT id FROM short_links WHERE shortcode = ? AND user_id = ?");
            $stmt->execute([$code, $user_id]);
            if (!$stmt->fetch()) {
                $error = '无权限编辑此链接。';
            } elseif (!filter_var($newurl, FILTER_VALIDATE_URL)) {
                $error = '无效的新URL。';
            } else {
                $enable_str = $enable_intermediate ? 'true' : 'false';
                $stmt = $pdo->prepare("UPDATE short_links SET longurl = ?, enable_intermediate_page = ?, redirect_delay = ?, link_password = ?, expiration_date = ? WHERE shortcode = ? AND user_id = ?");
                $stmt->execute([$newurl, $enable_str, $redirect_delay, $password_hash, $expiration ?: null, $code, $user_id]);
                $success = '链接更新成功。';
            }
        } elseif ($action === 'delete') {
            $code = $_POST['code'] ?? '';
            $stmt = $pdo->prepare("DELETE FROM short_links WHERE shortcode = ? AND user_id = ?");
            $stmt->execute([$code, $user_id]);
            $success = '链接删除成功。';
        } elseif ($action === 'delete_expired') {
            $stmt = $pdo->prepare("DELETE FROM short_links WHERE user_id = ? AND expiration_date < NOW()");
            $stmt->execute([$user_id]);
            $success = '已过期链接删除成功。';
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM short_links WHERE user_id = ? ORDER BY $order");
$stmt->execute([$user_id]);
$links = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_links = count($links);
$total_clicks = array_sum(array_column($links, 'clicks'));
$avg_click_rate = $total_links > 0 ? round($total_clicks / $total_links, 2) : 0;

// 来源统计 (top 10)
$sources_query = $pdo->prepare("
SELECT 
  CASE 
    WHEN referrer IS NULL OR referrer = '' THEN 'Direct'
    ELSE regexp_replace(referrer, '^(https?://)?([^/]+).*', '\\2')
  END AS domain,
  COUNT(*) as count 
FROM click_logs 
WHERE shortcode IN (SELECT shortcode FROM short_links WHERE user_id = ?)
GROUP BY domain 
ORDER BY count DESC
LIMIT 10
");
$sources_query->execute([$user_id]);
$sources = $sources_query->fetchAll(PDO::FETCH_ASSOC);

// Top 50 短码点击量
$top_query = $pdo->prepare("SELECT shortcode, clicks FROM short_links WHERE user_id = ? ORDER BY clicks DESC LIMIT 50");
$top_query->execute([$user_id]);
$top_links = $top_query->fetchAll(PDO::FETCH_ASSOC);

// 过去30天每日点击趋势
$daily_clicks_query = $pdo->prepare("
SELECT date(clicked_at) as day, COUNT(*) as count 
FROM click_logs 
WHERE shortcode IN (SELECT shortcode FROM short_links WHERE user_id = ?)
AND clicked_at >= NOW() - INTERVAL '30 days'
GROUP BY day 
ORDER BY day ASC
");
$daily_clicks_query->execute([$user_id]);
$daily_clicks_raw = $daily_clicks_query->fetchAll(PDO::FETCH_ASSOC);

$daily_clicks = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $daily_clicks[$date] = 0;
}
foreach ($daily_clicks_raw as $row) {
    $daily_clicks[$row['day']] = (int)$row['count'];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户控制台 - <?php echo htmlspecialchars(get_setting($pdo, 'site_title') ?? 'Zuz.Asia'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="includes/script.js"></script>
    <link rel="stylesheet" href="includes/styles.css">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
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
    <?php include 'includes/header.php'; ?>
    <main class="container mx-auto p-4 pt-20">
        <?php if ($error): ?>
            <div class="bg-destructive/10 border border-destructive/30 text-destructive px-4 py-3 rounded-lg mb-4"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-secondary/50 border border-secondary/30 text-secondary-foreground px-4 py-3 rounded-lg mb-4"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <div class="flex justify-between mb-4">
        
            <div class="space-x-2">
                <button onclick="openAddModal()" class="px-4 py-2 bg-black text-primary-foreground rounded-lg">+ 新建链接</button>
                <form method="post" class="inline">
                    <input type="hidden" name="action" value="delete_expired">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <button type="submit" class="px-4 py-2 bg-destructive text-destructive-foreground rounded-lg" onclick="return confirm('确定删除所有已过期链接?');">删除过期链接</button>
                </form>
            </div>
            <div class="flex space-x-2">
                <button onclick="toggleSort()" class="p-2 bg-black text-primary-foreground rounded-lg md:hidden" id="sortButton" title="切换排序">
  <svg id="icon-time" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
  </svg>
  <svg id="icon-clicks" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
  </svg>
</button>
            </div>
        </div>
        <div class="md:hidden space-y-4">
            <?php foreach ($links as $link): ?>
                <div class="bg-card rounded-lg border p-4">
                    <div class="flex items-center space-x-2 mb-2">
                        <input type="text" value="<?php echo htmlspecialchars($short_url . '/' . $link['shortcode']); ?>" readonly class="flex-1 px-3 py-1 border border-input rounded-lg bg-background text-sm font-mono" id="short_<?php echo htmlspecialchars($link['shortcode']); ?>">
                        <button onclick="copyToClipboard('short_<?php echo htmlspecialchars($link['shortcode']); ?>')" class="px-2 py-2 bg-secondary text-secondary-foreground rounded text-xs">复制</button>
                    </div>
                    <p class="text-muted-foreground text-sm mb-4 truncate" title="<?php echo htmlspecialchars($link['longurl']); ?>"><?php echo htmlspecialchars($link['longurl']); ?></p>
                    <div class="space-y-2 text-xs text-muted-foreground mb-4">
                        <p>点击: <?php echo htmlspecialchars($link['clicks']); ?></p>
                        <p>创建: <?php echo date('Y-m-d H:i', strtotime($link['created_at'])); ?></p>
                        <p>过期: <?php echo $link['expiration_date'] ? date('Y-m-d', strtotime($link['expiration_date'])) : '永不过期'; ?></p>
                        <p>中继页: <?php echo $link['enable_intermediate_page'] ? '开启' : '关闭'; ?></p>
                        <p>延迟: <?php echo $link['redirect_delay']; ?>s</p>
                        <p>密码保护: <?php echo $link['link_password'] ? '是' : '否'; ?></p>
                    </div>
                    <div class="flex space-x-2">
                        <button onclick="openEditModal('<?php echo htmlspecialchars($link['shortcode']); ?>', '<?php echo htmlspecialchars(addslashes($link['longurl'])); ?>', <?php echo $link['enable_intermediate_page'] ? 'true' : 'false'; ?>, <?php echo $link['redirect_delay']; ?>, '<?php echo $link['link_password'] ? '***' : ''; ?>', '<?php echo $link['expiration_date'] ? htmlspecialchars($link['expiration_date']) : ''; ?>')" class="flex-1 px-3 py-2 bg-black text-primary-foreground rounded text-sm">编辑</button>
                        <form method="post" class="flex-1 inline" onsubmit="return confirm('删除?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="code" value="<?php echo htmlspecialchars($link['shortcode']); ?>">
                            <button type="submit" class="w-full px-3 py-2 bg-destructive text-destructive-foreground rounded text-sm">删除</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($links)): ?>
                <div class="text-center py-12 text-muted-foreground">暂无链接。</div>
            <?php endif; ?>
        </div>
        <div class="hidden md:block overflow-x-auto">
            <table class="min-w-full bg-card rounded-lg border border-border">
                <thead>
                    <tr class="border-b border-border">
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">短链接</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">长链接</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">点击量</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">创建时间</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">过期时间</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">中继页</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">延迟</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">密码</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <?php foreach ($links as $link): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center space-x-2">
                                    <input type="text" value="<?php echo htmlspecialchars($short_url . '/' . $link['shortcode']); ?>" readonly class="px-3 py-1 border border-input rounded-lg bg-background text-sm font-mono" id="short_<?php echo htmlspecialchars($link['shortcode']); ?>">
                                    <button onclick="copyToClipboard('short_<?php echo htmlspecialchars($link['shortcode']); ?>')" class="px-2 py-1 bg-secondary text-secondary-foreground rounded text-xs">复制</button>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-muted-foreground truncate max-w-xs" title="<?php echo htmlspecialchars($link['longurl']); ?>"><?php echo htmlspecialchars($link['longurl']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-muted-foreground"><?php echo htmlspecialchars($link['clicks']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-muted-foreground"><?php echo date('Y-m-d H:i', strtotime($link['created_at'])); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-muted-foreground"><?php echo $link['expiration_date'] ? date('Y-m-d', strtotime($link['expiration_date'])) : '永不过期'; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-muted-foreground"><?php echo $link['enable_intermediate_page'] ? '开启' : '关闭'; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-muted-foreground"><?php echo $link['redirect_delay']; ?>s</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-muted-foreground"><?php echo $link['link_password'] ? '是' : '否'; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-xs font-medium">
                                <div class="flex space-x-2">
                                    <button onclick="openEditModal('<?php echo htmlspecialchars($link['shortcode']); ?>', '<?php echo htmlspecialchars(addslashes($link['longurl'])); ?>', <?php echo $link['enable_intermediate_page'] ? 'true' : 'false'; ?>, <?php echo $link['redirect_delay']; ?>, '<?php echo $link['link_password'] ? '***' : ''; ?>', '<?php echo $link['expiration_date'] ? htmlspecialchars($link['expiration_date']) : ''; ?>')" class="bg-black text-primary-foreground px-3 py-1 rounded">编辑</button>
                                    <form method="post" class="inline" onsubmit="return confirm('删除?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <input type="hidden" name="code" value="<?php echo htmlspecialchars($link['shortcode']); ?>">
                                        <button type="submit" class="bg-destructive text-destructive-foreground px-3 py-1 rounded">删除</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($links)): ?>
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-muted-foreground">暂无链接。</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-card rounded-lg border p-6 text-center">
                <h3 class="text-lg font-semibold text-muted-foreground">总链接数</h3>
                <p class="text-3xl font-bold"><?php echo $total_links; ?></p>
            </div>
            <div class="bg-card rounded-lg border p-6 text-center">
                <h3 class="text-lg font-semibold text-muted-foreground">总点击量</h3>
                <p class="text-3xl font-bold"><?php echo $total_clicks; ?></p>
            </div>
            <div class="bg-card rounded-lg border p-6 text-center">
                <h3 class="text-lg font-semibold text-muted-foreground">平均点击率</h3>
                <p class="text-3xl font-bold"><?php echo $avg_click_rate; ?></p>
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
        </div>
    </main>
    <?php include 'includes/footer.php'; ?>

    <div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 transition-opacity duration-300">
        <div class="bg-card rounded-lg border p-6 max-w-md w-full mx-4 transform scale-95 opacity-0 transition-all duration-300" id="addModalContent">
            <h3 class="text-lg font-semibold mb-4">添加新短链接</h3>
            <form method="post" id="addForm">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="space-y-3">
                    <input type="url" name="url" class="w-full px-3 py-2 border border-input rounded-lg" placeholder="https://example.com" required>
                    <input type="text" name="custom_code" class="w-full px-3 py-2 border border-input rounded-lg" placeholder="自定义短码（可选）" maxlength="10">
                    <div class="flex items-center justify-between">
                        <label class="text-sm font-medium">开启转跳中继页</label>
                        <label class="switch">
                            <input type="checkbox" name="enable_intermediate">
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">转跳延迟（秒，可选）</label>
                        <input type="number" name="redirect_delay" class="w-full px-3 py-2 border border-input rounded-lg" min="0" value="0">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">链接密码（可选）</label>
                        <input type="password" name="link_password" class="w-full px-3 py-2 border border-input rounded-lg" placeholder="设置密码以加密链接">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">过期日期（可选）</label>
                        <input type="date" name="expiration" class="w-full px-3 py-2 border border-input rounded-lg">
                    </div>
                    <?php if (get_setting($pdo, 'turnstile_enabled') === 'true'): ?>
                    <div class="cf-turnstile" data-sitekey="<?php echo htmlspecialchars(get_setting($pdo, 'turnstile_site_key')); ?>"></div>
                    <?php endif; ?>
                    <div class="flex gap-2">
                        <button type="button" onclick="closeAddModal()" class="flex-1 bg-secondary text-secondary-foreground py-2 px-4 rounded-lg">取消</button>
                        <button type="submit" class="flex-1 bg-black text-primary-foreground py-2 px-4 rounded-lg">添加</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 transition-opacity duration-300">
        <div class="bg-card rounded-lg border p-6 max-w-md w-full mx-4 transform scale-95 opacity-0 transition-all duration-300" id="editModalContent">
            <h3 class="text-lg font-semibold mb-4">编辑短链接</h3>
            <form method="post" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="code" id="editCode">
                <div class="space-y-3">
                    <input type="url" name="newurl" id="editUrl" class="w-full px-3 py-2 border border-input rounded-lg" required>
                    <div class="flex items-center justify-between">
                        <label class="text-sm font-medium">开启转跳中继页</label>
                        <label class="switch">
                            <input type="checkbox" name="enable_intermediate" id="editIntermediate">
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">转跳延迟（秒，可选）</label>
                        <input type="number" name="redirect_delay" id="editDelay" class="w-full px-3 py-2 border border-input rounded-lg" min="0">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">链接密码（可选，留空不修改）</label>
                        <input type="password" name="link_password" id="editPassword" class="w-full px-3 py-2 border border-input rounded-lg" placeholder="新密码">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">过期日期（可选）</label>
                        <input type="date" name="expiration" id="editExpiration" class="w-full px-3 py-2 border border-input rounded-lg">
                    </div>
                    <div class="flex gap-2">
                        <button type="button" onclick="closeEditModal()" class="flex-1 bg-secondary text-secondary-foreground py-2 px-4 rounded-lg">取消</button>
                        <button type="submit" class="flex-1 bg-black text-primary-foreground py-2 px-4 rounded-lg">保存</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentSort = '<?php echo $sort; ?>';
function toggleSort() {
  currentSort = currentSort === 'time' ? 'clicks' : 'time';
  document.getElementById('icon-time').classList.toggle('hidden', currentSort !== 'time');
  document.getElementById('icon-clicks').classList.toggle('hidden', currentSort !== 'clicks');
  window.location.href = '?sort=' + currentSort;
}
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('icon-time').classList.toggle('hidden', currentSort !== 'time');
  document.getElementById('icon-clicks').classList.toggle('hidden', currentSort !== 'clicks');
});

function openAddModal() {
    const modal = document.getElementById('addModal');
    const content = document.getElementById('addModalContent');
    modal.classList.remove('hidden');
    setTimeout(() => {
        content.classList.remove('scale-95', 'opacity-0');
    }, 10);
}


        function closeAddModal() {
            const modal = document.getElementById('addModal');
            const content = document.getElementById('addModalContent');
            content.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
            document.getElementById('addForm').reset();
        }

        function openEditModal(code, url, enableIntermediate, delay, password, expiration) {
            document.getElementById('editCode').value = code;
            document.getElementById('editUrl').value = url;
            document.getElementById('editIntermediate').checked = enableIntermediate;
            document.getElementById('editDelay').value = delay;
            document.getElementById('editPassword').value = password;
            document.getElementById('editExpiration').value = expiration ? expiration.split(' ')[0] : '';
            const modal = document.getElementById('editModal');
            const content = document.getElementById('editModalContent');
            modal.classList.remove('hidden');
            setTimeout(() => {
                content.classList.remove('scale-95', 'opacity-0');
            }, 10);
        }

        function closeEditModal() {
            const modal = document.getElementById('editModal');
            const content = document.getElementById('editModalContent');
            content.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
            document.getElementById('editForm').reset();
        }

        function copyToClipboard(id) {
            const el = document.getElementById(id);
            navigator.clipboard.writeText(el.value).then(() => {
                alert('已复制');
            });
        }

        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            if (event.target === addModal) closeAddModal();
            if (event.target === editModal) closeEditModal();
        }

        // 图表渲染
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
    </script>
</body>
</html>