<?php
require_once 'config.php';

$history = [];
if (isset($_COOKIE['short_history'])) {
    $history = json_decode($_COOKIE['short_history'], true) ?: [];
}
// Render home page
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
    </style>
</head>
<body class="bg-background text-foreground min-h-screen">
    <nav class="bg-card border-b border-border px-4 py-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold">Zuz.Asia</h1>
            <?php if (is_logged_in()): ?>
                <div class="space-x-4">
                    <span class="text-muted-foreground">欢迎，<?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
                    <a href="/dashboard" class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90">控制台</a>
                    <a href="/logout" class="px-4 py-2 bg-destructive text-destructive-foreground rounded-md hover:bg-destructive/90">登出</a>
                </div>
            <?php else: ?>
                <div class="space-x-4">
                    <a href="/login" class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90">登录</a>
                    <a href="/register" class="px-4 py-2 bg-secondary text-secondary-foreground rounded-md hover:bg-secondary/80">注册</a>
                </div>
            <?php endif; ?>
        </div>
    </nav>
    <div class="container mx-auto px-4 py-8">
        <!-- Hero Section -->
        <section class="hero-section text-center mb-16 bg-card/50 rounded-xl p-8">
            <h1 class="text-5xl md:text-7xl font-bold mb-6">Zuz.Asia</h1>
            <p class="text-xl text-muted-foreground max-w-3xl mx-auto mb-8">无需注册，即时创建短链接。简单、高效、安全。享受无缝的链接管理体验。我们的免费计划让您轻松开始。</p>
            <div class="space-x-4">
                <a href="/create" class="inline-flex items-center px-8 py-4 bg-primary hover:bg-primary/90 text-primary-foreground rounded-lg transition-colors font-semibold text-lg">免费开始</a>
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
?>