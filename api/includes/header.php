<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
?>
<nav class="header-card border-b border-border px-4 py-2 fixed top-0 w-full z-40 backdrop-filter backdrop-blur-md transition-all duration-300">
    <div class="container mx-auto flex justify-between items-center">
        <h1 class="text-2xl font-bold"><?php echo htmlspecialchars(get_setting($pdo, 'header_title') ?? 'Zuz.Asia'); ?></h1>
        <button onclick="toggleMobileMenu()" class="md:hidden px-4 py-2 bg-primary text-primary-foreground rounded-md">菜单</button>
        <div class="hidden md:flex space-x-4 desktop-menu">
            <?php if (is_logged_in()): ?>
                <span  class="text-muted-foreground py-2">欢迎，<?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
                <a href="/dashboard" class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90">控制台</a>
                <a href="/logout" class="px-4 py-2 bg-destructive text-destructive-foreground rounded-md hover:bg-destructive/90">登出</a>
            <?php else: ?>
                <a href="/login" class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90">登录</a>
                <a href="/register" class="px-4 py-2 bg-secondary text-secondary-foreground rounded-md hover:bg-secondary/80">注册</a>
            <?php endif; ?>
            <a href="/api/docs" class="px-4 py-2 bg-secondary text-secondary-foreground rounded-md hover:bg-secondary/80">API文档</a>
        </div>
        <div id="mobileMenu" class="hidden absolute top-16 right-4 md:hidden bg-card rounded-lg border p-4 space-y-2 mobile-menu backdrop-filter backdrop-blur-sm transition-all duration-300 ease-in-out transform scale-95 opacity-0" style="animation: slideDown 0.3s forwards;">
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
<script>
    function toggleMobileMenu() {
        const menu = document.getElementById('mobileMenu');
        menu.classList.toggle('hidden');
        menu.classList.toggle('scale-95');
        menu.classList.toggle('opacity-0');
    }
</script>