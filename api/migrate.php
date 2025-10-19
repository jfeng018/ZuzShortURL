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
    } else {
        if (!$admin_authenticated) {
            $input_token = $_POST['admin_token'] ?? '';
            if (hash_equals($admin_token, $input_token)) {
                $_SESSION['admin_auth'] = hash_hmac('sha256', $admin_token, session_id());
                $admin_authenticated = true;
            } else {
                $error = '无效的管理员令牌。';
            }
        }
        
        if ($admin_authenticated && empty($error)) {
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
                
                $pdo->exec("DO $$
                BEGIN
                    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'short_links' AND column_name = 'user_id') THEN
                        ALTER TABLE short_links ADD COLUMN user_id INT DEFAULT NULL;
                    END IF;
                    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'short_links' AND column_name = 'expiration_date') THEN
                        ALTER TABLE short_links ADD COLUMN expiration_date TIMESTAMP DEFAULT NULL;
                    END IF;
                    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'short_links' AND column_name = 'redirect_delay') THEN
                        ALTER TABLE short_links ADD COLUMN redirect_delay INT DEFAULT 0;
                    END IF;
                    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'short_links' AND column_name = 'link_password') THEN
                        ALTER TABLE short_links ADD COLUMN link_password TEXT DEFAULT NULL;
                    END IF;
                END $$;");
                
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
                
                $defaults = [
                    'allow_guest' => 'false',
                    'allow_register' => 'true',
                    'private_mode' => 'false',
                    'turnstile_enabled' => 'false',
                    'turnstile_site_key' => '',
                    'turnstile_secret_key' => ''
                ];
                foreach ($defaults as $key => $value) {
                    $pdo->exec("INSERT INTO settings (\"key\", value) VALUES ('$key', '$value') ON CONFLICT (\"key\") DO NOTHING");
                }
                
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_short_links_user_id ON short_links(user_id)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_short_links_expiration_date ON short_links(expiration_date)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_rate_limits_window_start ON rate_limits(window_start)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sessions_sess_time ON sessions(sess_time)");
                
                $pdo->exec("SELECT setval('short_links_id_seq', COALESCE((SELECT MAX(id)+1 FROM short_links), 1), false);");
                $pdo->exec("SELECT setval('users_id_seq', COALESCE((SELECT MAX(id)+1 FROM users), 1), false);");
                
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
            <form method="post">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <?php if (!$admin_authenticated): ?>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2">管理员令牌 (ADMIN_TOKEN)</label>
                        <input type="password" name="admin_token" class="w-full px-3 py-2 border border-input rounded-md" required>
                    </div>
                <?php endif; ?>
                <button type="submit" class="w-full bg-primary text-primary-foreground py-2 rounded-md hover:bg-primary/90">运行迁移</button>
            </form>
        </div>
    </main>
</body>
</html>