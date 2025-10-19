<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$csrf_token = generate_csrf_token();
$error = '';
$success = '';
$admin_authenticated = require_admin_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf'] ?? '')) {
        $error = 'CSRF令牌无效。';
    } elseif (!$admin_authenticated) {
        $error = '需要管理员权限。';
    } else {
        try {
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
            $pdo->exec("ALTER TABLE short_links ADD COLUMN IF NOT EXISTS user_id INT DEFAULT NULL;");
            $pdo->exec("ALTER TABLE short_links ADD COLUMN IF NOT EXISTS expiration_date TIMESTAMP DEFAULT NULL;");
            $pdo->exec("ALTER TABLE short_links ADD COLUMN IF NOT EXISTS redirect_delay INT DEFAULT 0;");
            $pdo->exec("ALTER TABLE short_links ADD COLUMN IF NOT EXISTS link_password TEXT DEFAULT NULL;");
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
                \"key\" VARCHAR(50) PRIMARY KEY,
                value TEXT DEFAULT 'true'
            )");
            $pdo->exec("ALTER TABLE settings ALTER COLUMN value TYPE TEXT;");
            $pdo->exec("INSERT INTO settings (\"key\", value) VALUES ('allow_guest', 'false') ON CONFLICT (\"key\") DO NOTHING");
            $pdo->exec("INSERT INTO settings (\"key\", value) VALUES ('allow_register', 'true') ON CONFLICT (\"key\") DO NOTHING");
            $pdo->exec("INSERT INTO settings (\"key\", value) VALUES ('private_mode', 'false') ON CONFLICT (\"key\") DO NOTHING");
            $pdo->exec("INSERT INTO settings (\"key\", value) VALUES ('turnstile_enabled', 'false') ON CONFLICT (\"key\") DO NOTHING");
            $pdo->exec("INSERT INTO settings (\"key\", value) VALUES ('turnstile_site_key', '') ON CONFLICT (\"key\") DO NOTHING");
            $pdo->exec("INSERT INTO settings (\"key\", value) VALUES ('turnstile_secret_key', '') ON CONFLICT (\"key\") DO NOTHING");
            $pdo->exec("SELECT setval('short_links_id_seq', COALESCE((SELECT MAX(id) FROM short_links), 1), true);");
            $pdo->exec("SELECT setval('users_id_seq', COALESCE((SELECT MAX(id) FROM users), 1), true);");
            
            $pdo->commit();
            $success = '数据库迁移成功！';
            header('Location: /admin');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = '迁移失败: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>数据库迁移 - Zuz.Asia</title>
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
<body class="bg-background text-foreground min-h-screen">
    <main class="container mx-auto p-4 pt-20">
        <div class="max-w-md mx-auto bg-card rounded-lg border p-6">
            <h2 class="text-2xl font-bold mb-4">数据库迁移</h2>
            <p class="text-muted-foreground mb-4">首次部署时，请运行此迁移以初始化数据库结构。</p>
            <?php if ($error): ?>
                <div class="bg-destructive/10 border border-destructive/30 text-destructive px-4 py-3 rounded-md mb-4"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="bg-secondary/50 border border-secondary/30 text-secondary-foreground px-4 py-3 rounded-md mb-4"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if (!$admin_authenticated): ?>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2">管理员令牌 (ADMIN_TOKEN)</label>
                        <input type="password" name="admin_token" class="w-full px-3 py-2 border border-input rounded-md" required>
                    </div>
                    <button type="submit" class="w-full bg-primary text-primary-foreground py-2 rounded-md hover:bg-primary/90">验证并迁移</button>
                </form>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <button type="submit" class="w-full bg-primary text-primary-foreground py-2 rounded-md hover:bg-primary/90">运行迁移</button>
                </form>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>