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
?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>链接已过期 - <?php echo htmlspecialchars(get_setting($pdo, 'site_title') ?? 'Zuz.Asia'); ?></title>
        <script src="https://cdn.tailwindcss.com"></script>
        <script src="includes/script.js"></script>
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
            <title>密码保护 - <?php echo htmlspecialchars(get_setting($pdo, 'site_title') ?? 'Zuz.Asia'); ?></title>
            <script src="https://cdn.tailwindcss.com"></script>
            <script src="includes/script.js"></script>
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

// 记录点击日志
$log_stmt = $pdo->prepare("INSERT INTO click_logs (shortcode, referrer, user_agent, ip) VALUES (?, ?, ?, ?)");
$log_stmt->execute([
    $code,
    $_SERVER['HTTP_REFERER'] ?? null,
    $_SERVER['HTTP_USER_AGENT'] ?? null,
    $_SERVER['REMOTE_ADDR'] ?? null
]);

// 更新总点击量
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
        <title>正在跳转 - <?php echo htmlspecialchars(get_setting($pdo, 'site_title') ?? 'Zuz.Asia'); ?></title>
        <meta http-equiv="refresh" content="<?php echo $link['redirect_delay']; ?>;url=<?php echo htmlspecialchars($link['longurl']); ?>">
        <script src="https://cdn.tailwindcss.com"></script>
        <script src="includes/script.js"></script>
            <link rel="stylesheet" href="includes/styles.css">
    </head>
    <body class="bg-background text-foreground min-h-screen flex flex-col">
        <?php include 'includes/header.php'; ?>
        <div class="flex-grow flex items-center justify-center">
            <div class="container mx-auto p-4">
                <div class="max-w-lg mx-auto bg-card rounded-lg border p-6 text-center">
                    <?php if ($intermediate_logo_url = get_setting($pdo, 'intermediate_logo_url')): ?>
                        <img src="<?php echo htmlspecialchars($intermediate_logo_url); ?>" alt="Logo" class="mx-auto mb-4 h-16">
                    <?php endif; ?>
                    <h2 class="text-2xl font-bold mb-4">正在跳转...</h2>
                    <p class="text-muted-foreground mb-4"><?php echo htmlspecialchars(get_setting($pdo, 'intermediate_text') ?? '您将被重定向到以下链接:'); ?></p>
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