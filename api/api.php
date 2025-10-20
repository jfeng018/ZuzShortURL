<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API文档 - <?php echo htmlspecialchars(get_setting($pdo, 'site_title') ?? 'Zuz.Asia'); ?></title>
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
    <link rel="stylesheet" href="../includes/styles.css">
</head>
<body class="bg-background text-foreground min-h-screen">
    <?php include 'includes/header.php'; ?>
    <main class="container mx-auto p-4 pt-20">
        <div class="text-center mb-8">
            <h2 class="text-3xl font-bold inline-flex items-center">
                <svg class="h-8 w-8 mr-2 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l-4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                </svg>
                API 文档
            </h2>
        </div>
        <div class="bg-card rounded-lg border p-4 mb-6">
            <h3 class="text-xl font-bold mb-3 inline-flex items-center">
                <svg class="h-5 w-5 mr-2 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.657l-2.122 2.122M6.364 17.364l2.121 2.121m9.9-9.9l2.121-2.122" />
                </svg>
                创建短链接 POST /api/create
            </h3>
            <p class="text-muted-foreground mb-3">Headers: Content-Type: application/json</p>
            <pre class="bg-muted rounded-md p-3 overflow-x-auto text-sm"><code>{
  "url": "https://example.com",
  "custom_code": "abcde",
  "enable_intermediate": true,
  "expiration": "2025-12-31"
}</code></pre>
            <p class="text-muted-foreground mb-3 mt-3">Response:</p>
            <pre class="bg-muted rounded-md p-3 overflow-x-auto text-sm"><code>{
  "success": true,
  "short_url": "https://zuz.asia/abcde"
}</code></pre>
            <p class="text-sm text-destructive mt-3">注: 速率限制120次/分钟，无API密钥（开源）。</p>
        </div>
        <div class="text-center">
            <a href="/" class="inline-flex items-center px-3 py-3 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 text-sm">
                <svg class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1m-6 0h6" />
                </svg>
                返回首页
            </a>
        </div>
    </main>
    <?php include 'includes/footer.php'; ?>
</body>
</html>