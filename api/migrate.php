<?php
$error = '';
$success = '';

function getPdoFromEnv(): PDO
{
    $url = parse_url(getenv('DATABASE_URL'));
    if (!$url) {
        throw new RuntimeException('DATABASE_URL 未配置或格式错误');
    }

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        $url['host'],
        $url['port'] ?? 5432,
        ltrim($url['path'], '/')
    );

    return new PDO(
        $dsn,
        $url['user'],
        $url['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getPdoFromEnv();
        $pdo->beginTransaction();

        $pdo->exec("CREATE TABLE IF NOT EXISTS short_links (
            id SERIAL PRIMARY KEY,
            shortcode VARCHAR(10) UNIQUE NOT NULL,
            longurl TEXT NOT NULL,
            user_id INT DEFAULT NULL,
            enable_intermediate_page BOOLEAN DEFAULT FALSE,
            redirect_delay INT DEFAULT 0,
            link_password TEXT DEFAULT NULL,
            expiration_date TIMESTAMP DEFAULT NULL,
            clicks INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("DO $$
        BEGIN
            IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='short_links' AND column_name='user_id') THEN
                ALTER TABLE short_links ADD COLUMN user_id INT DEFAULT NULL;
            END IF;
            IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='short_links' AND column_name='expiration_date') THEN
                ALTER TABLE short_links ADD COLUMN expiration_date TIMESTAMP DEFAULT NULL;
            END IF;
            IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='short_links' AND column_name='redirect_delay') THEN
                ALTER TABLE short_links ADD COLUMN redirect_delay INT DEFAULT 0;
            END IF;
            IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='short_links' AND column_name='link_password') THEN
                ALTER TABLE short_links ADD COLUMN link_password TEXT DEFAULT NULL;
            END IF;
        END $$");

        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id SERIAL PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
            ip INET PRIMARY KEY,
            request_count INT DEFAULT 0,
            window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS sessions (
            sess_id VARCHAR(128) PRIMARY KEY,
            sess_data TEXT NOT NULL,
            sess_lifetime INT NOT NULL,
            sess_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            key VARCHAR(50) PRIMARY KEY,
            value TEXT DEFAULT 'true'
        )");

        // 新增 click_logs 表用于跟踪点击来源
        $pdo->exec("CREATE TABLE IF NOT EXISTS click_logs (
            id SERIAL PRIMARY KEY,
            shortcode VARCHAR(10) NOT NULL,
            referrer TEXT,
            user_agent TEXT,
            ip INET,
            clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // 当前域名（纯域名，不含协议）
        $currentDomain = $_SERVER['HTTP_HOST'] ?? 'localhost';

        $defaults = [
            'allow_guest'        => 'false',
            'allow_register'     => 'true',
            'private_mode'       => 'false',
            'turnstile_enabled'  => 'false',
            'enable_dual_domain' => 'false',
            'turnstile_site_key' => '',
            'turnstile_secret_key' => '',
            'official_domain' => $currentDomain, // 仅域名，如 example.com
            'short_domain' => '',
            'site_title' => 'Zuz.Asia',
            'header_title' => 'Zuz.Asia',
            'home_description' => 'Zuz.Asia是一个免费、开源的短链接服务，旨在为用户提供简单、高效、安全的链接缩短体验。无需注册即可使用；我们的系统基于PostgreSQL数据库，数据安全有保障。加入数千用户，享受无限短链接创建的便利。',
            'home_image_url' => 'https://cdn.mengze.vip/gh/JanePHPDev/Blog-Static-Resource@main/images/d2fc9d8ee03eb8a8.jpg',
            'intermediate_logo_url' => '',
            'intermediate_text' => '您将被重定向到以下链接:'
        ];
        foreach ($defaults as $k => $v) {
            $stmt = $pdo->prepare("
                INSERT INTO settings (key, value) VALUES (?, ?) 
                ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value 
                WHERE settings.value IS NULL OR settings.value = ''
            ");
            $stmt->execute([$k, $v]);
        }

        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_short_links_user_id ON short_links(user_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_short_links_expiration_date ON short_links(expiration_date)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_rate_limits_window_start ON rate_limits(window_start)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sessions_sess_time ON sessions(sess_time)");

        // 为 click_logs 添加索引
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_click_logs_shortcode ON click_logs(shortcode)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_click_logs_clicked_at ON click_logs(clicked_at)");

        $pdo->exec("SELECT setval('short_links_id_seq', COALESCE((SELECT MAX(id)+1 FROM short_links), 1), false)");
        $pdo->exec("SELECT setval('users_id_seq',      COALESCE((SELECT MAX(id)+1 FROM users),      1), false)");

        $pdo->commit();
        $success = '数据库迁移成功！';
        header('Location: /admin');
        exit;
    } catch (Throwable $e) {
        isset($pdo) && $pdo->inTransaction() && $pdo->rollBack();
        $error = '迁移失败: ' . htmlspecialchars($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>数据库迁移 - Zuz.Asia</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="includes/styles.css">
</head>
<body class="bg-background text-foreground min-h-screen flex items-center justify-center">
<main class="container mx-auto p-4">
    <div class="max-w-md mx-auto bg-card rounded-lg border p-6">
        <h2 class="text-2xl font-bold mb-4">数据库迁移</h2>
        <p class="text-muted-foreground mb-4">首次部署时，请运行此迁移以初始化数据库结构。</p>
        <?php if ($error): ?>
            <div class="bg-destructive/10 border border-destructive/30 text-destructive px-4 py-3 rounded-md mb-4"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-secondary/50 border border-secondary/30 text-secondary-foreground px-4 py-3 rounded-md mb-4"><?= $success ?></div>
        <?php endif; ?>
        <form method="post">
            <button type="submit" class="w-full bg-primary text-primary-foreground py-2 rounded-md hover:bg-primary/90">运行迁移</button>
        </form>
    </div>
</main>
</body>
</html>