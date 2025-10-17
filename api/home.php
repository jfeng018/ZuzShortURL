<?php
require_once 'config.php';

if (get_setting($pdo, 'private_mode') && !require_admin_auth()) {
    header('Location: /admin');
    exit;
}

$history = [];
if (isset($_COOKIE['short_history'])) {
    $history = json_decode($_COOKIE['short_history'], true) ?: [];
}
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
    </style>
</head>
<body class="bg-background text-foreground min-h-screen">
    <nav class="bg-card border-b border-border px-4 py-4 fixed top-0 w-full z-40">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold">Zuz.Asia</h1>
            <button onclick="toggleMobileMenu()" class="md:hidden px-4 py-2 bg-primary text-primary-foreground rounded-md">菜单</button>
            <div class="hidden md:flex space-x-4 desktop-menu">
                <?php if (is_logged_in()): ?>
                    <span class="text-muted-foreground">欢迎，<?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
                    <a href="/dashboard" class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90">控制台</a>
                    <a href="/logout" class="px-4 py-2 bg-destructive text-destructive-foreground rounded-md hover:bg-destructive/90">登出</a>
                <?php else: ?>
                    <a href="/login" class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90">登录</a>
                    <a href="/register" class="px-4 py-2 bg-secondary text-secondary-foreground rounded-md hover:bg-secondary/80">注册</a>
                <?php endif; ?>
                <a href="/api/docs" class="px-4 py-2 bg-secondary text-secondary-foreground rounded-md hover:bg-secondary/80">API文档</a>
            </div>
            <div id="mobileMenu" class="hidden absolute top-16 right-4 md:hidden bg-card rounded-lg border p-4 space-y-2 mobile-menu">
                <?php if (is_logged_in()): ?>
                    <span class="text-muted-foreground block">欢迎，<?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
                    <a href="/dashboard" class="block px-4 py-2 bg-primary text-primary-foreground rounded-md">控制台</a>
                    <a href="/logout" class="block px-4 py-2 bg-destructive text-destructive-foreground rounded-md">登出</a>
                <?php else: ?>
                    <a href="/login" class="block px-4 py-2 bg-primary text-primary-foreground rounded-md">登录</a>
                    <a href="/register" class="block px-4 py-2 bg-secondary text-secondary-foreground rounded-md">注册</a>
                <?php endif; ?>
                <a href="/api/docs" class="block px-4 py-2 bg-secondary text-secondary-foreground rounded-md">API文档</a>
            </div>
        </div>
    </nav>
    <div class="container mx-auto px-4 py-4 pt-20">
        <section class="hero-section mb-8 bg-card/50 rounded-xl p-4 md:p-8 md:flex md:items-center md:space-x-8">
            <div class="md:w-1/2 mb-4 md:mb-0">
                <h1 class="text-4xl md:text-6xl font-bold mb-4">Zuz.Asia</h1>
                <p class="text-lg md:text-xl text-muted-foreground max-w-md">Zuz.Asia是一个免费、开源的短链接服务，旨在为用户提供简单、高效、安全的链接缩短体验。无需注册即可使用；我们的系统基于PostgreSQL数据库，数据安全有保障。加入数千用户，享受无限短链接创建的便利。</p>
                <div class="space-x-4 mt-6">
                    <?php if (is_logged_in()): ?>
                        <a href="/dashboard" class="inline-flex items-center px-8 py-4 bg-primary hover:bg-primary/90 text-primary-foreground rounded-lg transition-colors font-semibold text-lg">前往控制台</a>
                    <?php else: ?>
                        <a href="/create" class="inline-flex items-center px-8 py-4 bg-primary hover:bg-primary/90 text-primary-foreground rounded-lg transition-colors font-semibold text-lg">免费开始</a>
                    <?php endif; ?>
                    <a href="/api/docs" class="inline-flex items-center px-8 py-4 bg-secondary text-secondary-foreground rounded-lg transition-colors font-semibold text-lg">API文档</a>
                </div>
            </div>
            <div class="md:w-1/2">
                <img src="https://cdn.mengze.vip/gh/JanePHPDev/Blog-Static-Resource@main/images/d2fc9d8ee03eb8a8.jpg" alt="UI预览" class="mx-auto max-w-full md:max-w-md rounded-lg shadow-lg">
            </div>
        </section>

        <section class="grid md:grid-cols-3 gap-4 md:gap-8 mb-8 md:mb-16">
            <div class="bg-card rounded-lg border p-4 md:p-8 pricing-card">
                <h3 class="text-xl md:text-2xl font-bold mb-4">即时缩短</h3>
                <p class="text-muted-foreground">输入长连接，一键生成短链接，无需等待、立即分享、极速加载</p>
            </div>
            <div class="bg-card rounded-lg border p-4 md:p-8 pricing-card">
                <h3 class="text-xl md:text-2xl font-bold mb-4">无限使用</h3>
                <p class="text-muted-foreground">免费计划支持无限量地创建短链接，也可以Fork仓库源码自己搭建本系统。</p>
            </div>
            <div class="bg-card rounded-lg border p-4 md:p-8 pricing-card">
                <h3 class="text-xl md:text-2xl font-bold mb-4">安全可靠</h3>
                <p class="text-muted-foreground">基于PostgreSQL数据库加密存储，性能极致优化，安全可靠。</p>
            </div>
        </section>

        <section class="text-center mb-8 md:mb-16">
            <h2 class="text-2xl md:text-3xl font-bold mb-8">选择您的计划</h2>
            <div class="grid md:grid-cols-3 gap-4 md:gap-8 max-w-5xl mx-auto">
                <div class="bg-card rounded-lg border p-4 md:p-8 pricing-card">
                    <h3 class="text-xl md:text-2xl font-bold mb-4">免费版</h3>
                    <ul class="space-y-2 text-left mb-6">
                        <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> 无限短链接</li>
                        <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> 基本统计</li>
                        <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> 自定义短码</li>
                        <li class="flex items-center"><span class="text-yellow-500 mr-2">⚠</span> 速率限制</li>
                    </ul>
                    <p class="text-2xl md:text-3xl font-bold text-green-600 mb-4">$0 / 月</p>
                    <a href="/create" class="w-full bg-primary text-primary-foreground py-3 px-6 rounded-md hover:bg-primary/90 transition-colors font-semibold">立即开始</a>
                </div>
                <div class="bg-card rounded-lg border p-4 md:p-8 pricing-card">
                    <h3 class="text-xl md:text-2xl font-bold mb-4">注册用户套餐</h3>
                    <ul class="space-y-2 text-left mb-6">
                        <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> 管理个人链接</li>
                        <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> 高级统计数据</li>
                        <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> 自定义短码</li>
                        <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> 中继页设置</li>
                    </ul>
                    <p class="text-2xl md:text-3xl font-bold text-green-600 mb-4">$0 / 月</p>
                    <a href="/register" class="w-full bg-primary text-primary-foreground py-3 px-6 rounded-md hover:bg-primary/90 transition-colors font-semibold">注册使用</a>
                </div>
                <div class="bg-card rounded-lg border p-4 md:p-8 pricing-card">
                    <h3 class="text-xl md:text-2xl font-bold mb-4">自建用户套餐</h3>
                    <ul class="space-y-2 text-left mb-6">
                        <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> 完全自由控制</li>
                        <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> 自托管部署</li>
                        <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> 自定义功能</li>
                        <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> 开源免费</li>
                    </ul>
                    <p class="text-2xl md:text-3xl font-bold text-green-600 mb-4">$0 / 月</p>
                    <a href="https://github.com/JanePHPDev/ZuzShortURL" target="_blank" class="w-full bg-primary text-primary-foreground py-3 px-6 rounded-md hover:bg-primary/90 transition-colors font-semibold">Fork 项目</a>
                </div>
            </div>
        </section>

        <section class="text-center mb-8 md:mb-16">
            <h2 class="text-2xl md:text-3xl font-bold mb-8">用户评价</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-8 max-w-5xl mx-auto">
                <div class="bg-card rounded-lg border p-4 md:p-6">
                    <img src="https://cdn.mengze.vip/gh/JanePHPDev/Blog-Static-Resource@main/images/3974b5accbd063ba.png" alt="用户头像" class="w-12 h-12 rounded-full mx-auto mb-4">
                    <h4 class="font-semibold mb-2">大白萝卜</h4>
                    <p class="text-sm text-muted-foreground">"不错不错，很棒的项目"</p>
                </div>
                <div class="bg-card rounded-lg border p-4 md:p-6">
                    <img src="https://cdn.mengze.vip/gh/JanePHPDev/Blog-Static-Resource@main/images/f2f846d91a1c14d8.jpg" alt="用户头像" class="w-12 h-12 rounded-full mx-auto mb-4">
                    <h4 class="font-semibold mb-2">柠枺</h4>
                    <p class="text-sm text-muted-foreground">"很不错的，光看UI不够，中继页设计和账号下管理链接功能都很出色。"</p>
                </div>
                <div class="bg-card rounded-lg border p-4 md:p-6">
                    <img src="https://cdn.mengze.vip/gh/YShenZe/Blog-Static-Resource@main/images/1746460967151.jpg" alt="用户头像" class="w-12 h-12 rounded-full mx-auto mb-4">
                    <h4 class="font-semibold mb-2">梦泽</h4>
                    <p class="text-sm text-muted-foreground">"安全可靠，从不担心链接泄露。开源代码值得信赖。"</p>
                </div>
            </div>
        </section>

        <section class="grid md:grid-cols-3 gap-4 md:gap-8 mb-8 md:mb-16">
            <div class="bg-card rounded-lg border p-4 md:p-8 text-center pricing-card">
                <h3 class="text-3xl md:text-4xl font-bold text-primary">10k+</h3>
                <p class="text-muted-foreground">链接已创建</p>
            </div>
            <div class="bg-card rounded-lg border p-4 md:p-8 text-center pricing-card">
                <h3 class="text-3xl md:text-4xl font-bold text-primary">99.9%</h3>
                <p class="text-muted-foreground">正常运行时间</p>
            </div>
            <div class="bg-card rounded-lg border p-4 md:p-8 text-center pricing-card">
                <h3 class="text-3xl md:text-4xl font-bold text-primary">1.3s</h3>
                <p class="text-muted-foreground">平均响应时间</p>
            </div>
        </section>

        <section class="text-center mb-8 md:mb-16">
            <h2 class="text-2xl md:text-3xl font-bold mb-4">准备好缩短您的第一个链接了吗？</h2>
            <?php if (is_logged_in()): ?>
                <a href="/dashboard" class="inline-flex items-center px-8 py-4 bg-primary hover:bg-primary/90 text-primary-foreground rounded-lg transition-colors font-semibold text-lg">前往控制台</a>
            <?php else: ?>
                <a href="/create" class="inline-flex items-center px-8 py-4 bg-primary hover:bg-primary/90 text-primary-foreground rounded-lg transition-colors font-semibold text-lg">免费开始</a>
            <?php endif; ?>
            <a href="/api/docs" class="inline-flex items-center px-8 py-4 bg-secondary text-secondary-foreground rounded-lg transition-colors font-semibold text-lg ml-4">API文档</a>
        </section>

        <footer class="pt-8 border-t border-border text-center text-sm text-muted-foreground">
            <p>&copy; 2025 Zuz.Asia. All rights reserved. | <a href="https://github.com/JanePHPDev/ZuzShortURL" target="_blank" class="text-primary hover:underline">GitHub</a></p>
        </footer>
    </div>
    <script>
        function toggleMobileMenu() {
            document.getElementById('mobileMenu').classList.toggle('hidden');
        }
    </script>
</body>
</html>
<?php
exit;
?>