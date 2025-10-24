<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
?>
<nav class="header-card border-b border-border px-4 py-2 fixed top-0 w-full z-40 backdrop-filter backdrop-blur-md transition-all duration-300">
    <div class="container mx-auto flex justify-between items-center">
        <h1 class="text-2xl font-bold"><?php echo htmlspecialchars(get_setting($pdo, 'header_title')); ?></h1>

        <!-- 桌面菜单 -->
        <div class="hidden md:flex items-center space-x-4">
            <?php if (is_logged_in()): ?>
                <span class="text-muted-foreground py-2">欢迎，<?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
                <a href="/dashboard" class="mx-auto max-w-fit border px-5 py-2 text-sm font-medium shadow-sm transition-all hover:ring-4 hover:ring-neutral-200 disabled:bg-neutral-100 disabled:text-neutral-500 disabled:cursor-not-allowed disabled:hover:ring-0 disabled:border-neutral-200 border-black bg-black text-white hover:bg-neutral-800 rounded-lg">控制台</a>
                <a href="/logout" class="mx-auto max-w-fit border px-5 py-2 text-sm font-medium shadow-sm transition-all hover:ring-4 hover:ring-neutral-200 disabled:bg-neutral-100 disabled:text-neutral-500 disabled:cursor-not-allowed disabled:hover:ring-0 disabled:border-neutral-200 border-black bg-black text-white hover:bg-neutral-800 rounded-lg">登出</a>
            <?php else: ?>
                <a href="/login" class="mx-auto max-w-fit border px-5 py-2 text-sm font-medium shadow-sm transition-all hover:ring-4 hover:ring-neutral-200 disabled:bg-neutral-100 disabled:text-neutral-500 disabled:cursor-not-allowed disabled:hover:ring-0 disabled:border-neutral-200 border-black bg-black text-white hover:bg-neutral-800 rounded-lg">登录</a>
                <a href="/register" class="mx-auto max-w-fit border px-5 py-2 text-sm font-medium shadow-sm transition-all hover:ring-4 hover:ring-neutral-200 disabled:bg-neutral-100 disabled:text-neutral-500 disabled:cursor-not-allowed disabled:hover:ring-0 disabled:border-neutral-200 border-neutral-200 bg-white hover:border-neutral-400 hover:text-neutral-800 text-neutral-500 rounded-lg">注册</a>
            <?php endif; ?>
            
            <!-- 更多选项下拉 -->
            <div class="relative group">
                <button class="flex items-center space-x-1 px-4 py-2 text-sm font-medium rounded-lg border border-neutral-200 bg-white hover:border-neutral-400 hover:text-neutral-800 text-neutral-500 transition-all">
                    <span>更多</span>
                    <svg class="w-4 h-4 transition-transform group-hover:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
                <div class="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 transform group-hover:translate-y-0 translate-y-2">
                    <div class="py-2">
                        <a href="https://github.com/JanePHPDev/ZuzShortURL" target="_blank" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            <svg class="w-4 h-4 mr-3" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                            </svg>
                            GitHub
                        </a>
                        <a href="https://qm.qq.com/q/UgE1QY47y6" target="_blank" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            <svg class="w-4 h-4 mr-3" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/>
                            </svg>
                            QQ群
                        </a>
                        <a href="https://linux.do/t/topic/1050443" target="_blank" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            <svg class="w-4 h-4 mr-3" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/>
                            </svg>
                            Linux.do
                        </a>
                        <a href="https://zuz.asia/" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            <svg class="w-4 h-4 mr-3" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                            </svg>
                            Demo网站
                        </a>
                        <a href="/api/docs" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            <svg class="w-4 h-4 mr-3" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>
                            </svg>
                            API文档
                        </a>
                        <a href="mailto:master@zeapi.ink" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            <svg class="w-4 h-4 mr-3" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                            </svg>
                            联系我们
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="md:hidden flex items-center space-x-2">
            <?php if (!is_logged_in()): ?>
                <a href="/login" class="px-3 py-1.5 text-sm border border-black bg-black text-white rounded-lg">登录</a>
                <a href="/register" class="px-3 py-1.5 text-sm border border-neutral-200 bg-white text-neutral-500 rounded-lg">注册</a>
            <?php else: ?>
                <a href="/dashboard" class="px-3 py-1.5 text-sm border border-black bg-black text-white rounded-lg">控制台</a>
            <?php endif; ?>
            
            <button onclick="toggleMobileMenu()" class="p-2 rounded-lg border border-neutral-200 bg-white text-neutral-500 hover:border-neutral-400 transition-all">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
        </div>
    </div>

    <div id="mobileDropdown" class="fixed top-full left-0 w-full z-50 hidden md:hidden bg-white shadow-lg border-t border-gray-200 transform transition-all duration-300 ease-out origin-top">
        <div class="absolute inset-0 bg-black bg-opacity-25" onclick="toggleMobileMenu()"></div>
        
        <div class="relative px-4 py-4 space-y-2 max-h-[calc(100vh-4rem)] overflow-y-auto opacity-0 scale-y-95">
            <?php if (is_logged_in()): ?>
                <div class="text-sm text-muted-foreground mb-4 text-center">欢迎，<?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></div>
                <a href="/dashboard" class="flex items-center justify-center px-4 py-3 text-sm rounded-lg hover:bg-gray-100" onclick="toggleMobileMenu()">
                    <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
                    </svg>
                    <span class="flex-1 text-left">控制台</span>
                </a>
                <a href="/logout" class="flex items-center justify-center px-4 py-3 text-sm rounded-lg hover:bg-gray-100" onclick="toggleMobileMenu()">
                    <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>
                    </svg>
                    <span class="flex-1 text-left">登出</span>
                </a>
            <?php else: ?>
                <a href="/login" class="flex items-center justify-center px-4 py-3 text-sm rounded-lg hover:bg-gray-100" onclick="toggleMobileMenu()">
                    <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M11 7L9.6 8.4l2.6 2.6H2v2h10.2l-2.6 2.6L11 17l5-5-5-5zm9 12h-8v2h8c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h8v14z"/>
                    </svg>
                    <span class="flex-1 text-left">登录</span>
                </a>
                <a href="/register" class="flex items-center justify-center px-4 py-3 text-sm rounded-lg hover:bg-gray-100" onclick="toggleMobileMenu()">
                    <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                    <span class="flex-1 text-left">注册</span>
                </a>
            <?php endif; ?>
            
            <div class="border-t pt-4 mt-4 space-y-2">
                <a href="https://github.com/JanePHPDev/ZuzShortURL" target="_blank" class="flex items-center justify-center px-4 py-3 text-sm rounded-lg hover:bg-gray-100" onclick="toggleMobileMenu()">
                    <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                    </svg>
                    <span class="flex-1 text-left">GitHub</span>
                </a>
                <a href="https://qm.qq.com/q/UgE1QY47y6" target="_blank" class="flex items-center justify-center px-4 py-3 text-sm rounded-lg hover:bg-gray-100" onclick="toggleMobileMenu()">
                    <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/>
                    </svg>
                    <span class="flex-1 text-left">QQ群</span>
                </a>
                <a href="https://linux.do/t/topic/1050443" target="_blank" class="flex items-center justify-center px-4 py-3 text-sm rounded-lg hover:bg-gray-100" onclick="toggleMobileMenu()">
                    <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/>
                    </svg>
                    <span class="flex-1 text-left">Linux.do</span>
                </a>
                <a href="https://zuz.asia/" class="flex items-center justify-center px-4 py-3 text-sm rounded-lg hover:bg-gray-100" onclick="toggleMobileMenu()">
                    <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                    </svg>
                    <span class="flex-1 text-left">Demo网站</span>
                </a>
                <a href="/api/docs" class="flex items-center justify-center px-4 py-3 text-sm rounded-lg hover:bg-gray-100" onclick="toggleMobileMenu()">
                    <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>
                    </svg>
                    <span class="flex-1 text-left">API文档</span>
                </a>
                <a href="mailto:master@zeapi.ink" class="flex items-center justify-center px-4 py-3 text-sm rounded-lg hover:bg-gray-100" onclick="toggleMobileMenu()">
                    <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                    </svg>
                    <span class="flex-1 text-left">联系我们</span>
                </a>
            </div>
        </div>
    </div>
</nav>

<script>
    function toggleMobileMenu() {
        const dropdown = document.getElementById('mobileDropdown');
        const content = dropdown.querySelector('div.relative.px-4.py-4');
        
        if (dropdown.classList.contains('hidden')) {
            dropdown.classList.remove('hidden');
            setTimeout(() => {
                dropdown.classList.add('!opacity-100', '!scale-y-100');
                content.classList.remove('opacity-0', 'scale-y-95');
            }, 10);
        } else {
            content.classList.add('opacity-0', 'scale-y-95');
            setTimeout(() => {
                dropdown.classList.add('hidden');
                dropdown.classList.remove('!opacity-100', '!scale-y-100');
            }, 300);
        }
    }
</script>