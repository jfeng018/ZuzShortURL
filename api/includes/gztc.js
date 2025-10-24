// sponsor-popup.js
(function() {
    'use strict';
    
    // 配置
    const config = {
        title: '感谢您的支持！',
        sponsorName: 'Ksgf452',
        amount: '130元',
        currency: 'RMB',
        purpose: '购买服务器',
        showDuration: 5000, // 显示时长（毫秒）
        cookieName: 'sponsor_popup_shown',
        cookieExpiry: 7 // Cookie过期天数
    };

    // 检查是否已经显示过弹窗
    function hasShownPopup() {
        const cookies = document.cookie.split(';');
        for (let cookie of cookies) {
            const [name, value] = cookie.trim().split('=');
            if (name === config.cookieName && value === 'true') {
                return true;
            }
        }
        return false;
    }

    // 设置Cookie
    function setCookie() {
        const expiryDate = new Date();
        expiryDate.setDate(expiryDate.getDate() + config.cookieExpiry);
        document.cookie = `${config.cookieName}=true; expires=${expiryDate.toUTCString()}; path=/`;
    }

    // 创建样式
    function createStyles() {
        const styles = `
            #sponsor-popup-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.6);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 10000;
                animation: fadeIn 0.3s ease-in-out;
            }

            #sponsor-popup {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 15px;
                padding: 30px;
                max-width: 400px;
                width: 90%;
                text-align: center;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
                animation: slideIn 0.4s ease-out;
                color: white;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            }

            #sponsor-popup h2 {
                margin: 0 0 20px 0;
                font-size: 24px;
                font-weight: bold;
            }

            #sponsor-popup .sponsor-info {
                background-color: rgba(255, 255, 255, 0.1);
                border-radius: 10px;
                padding: 20px;
                margin: 20px 0;
            }

            #sponsor-popup .sponsor-name {
                font-size: 20px;
                font-weight: bold;
                margin-bottom: 10px;
            }

            #sponsor-popup .sponsor-amount {
                font-size: 28px;
                font-weight: bold;
                color: #FFD700;
                margin: 10px 0;
            }

            #sponsor-popup .purpose {
                font-size: 16px;
                opacity: 0.9;
                margin-top: 10px;
            }

            #sponsor-popup .close-btn {
                background-color: rgba(255, 255, 255, 0.2);
                border: 1px solid rgba(255, 255, 255, 0.3);
                color: white;
                padding: 10px 20px;
                border-radius: 25px;
                cursor: pointer;
                font-size: 14px;
                margin-top: 20px;
                transition: all 0.3s ease;
            }

            #sponsor-popup .close-btn:hover {
                background-color: rgba(255, 255, 255, 0.3);
                transform: translateY(-2px);
            }

            #sponsor-popup .progress-bar {
                width: 100%;
                height: 4px;
                background-color: rgba(255, 255, 255, 0.2);
                border-radius: 2px;
                margin-top: 20px;
                overflow: hidden;
            }

            #sponsor-popup .progress {
                height: 100%;
                background-color: #FFD700;
                border-radius: 2px;
                animation: progressAnimation ${config.showDuration}ms linear;
            }

            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }

            @keyframes slideIn {
                from { 
                    transform: translateY(-50px);
                    opacity: 0;
                }
                to { 
                    transform: translateY(0);
                    opacity: 1;
                }
            }

            @keyframes progressAnimation {
                from { width: 100%; }
                to { width: 0%; }
            }

            @media (max-width: 480px) {
                #sponsor-popup {
                    padding: 20px;
                    margin: 20px;
                }
                
                #sponsor-popup h2 {
                    font-size: 20px;
                }
                
                #sponsor-popup .sponsor-amount {
                    font-size: 24px;
                }
            }
        `;
        
        const styleSheet = document.createElement('style');
        styleSheet.textContent = styles;
        document.head.appendChild(styleSheet);
    }

    // 创建弹窗
    function createPopup() {
        const overlay = document.createElement('div');
        overlay.id = 'sponsor-popup-overlay';
        
        const popup = document.createElement('div');
        popup.id = 'sponsor-popup';
        
        popup.innerHTML = `
            <h2>${config.title}</h2>
            <div class="sponsor-info">
                <div class="sponsor-name">赞助者：${config.sponsorName}</div>
                <div class="sponsor-amount">${config.amount} ${config.currency}</div>
                <div class="purpose">用于${config.purpose}</div>
            </div>
            <button class="close-btn" onclick="this.closest('#sponsor-popup-overlay').remove()">关闭</button>
            <div class="progress-bar">
                <div class="progress"></div>
            </div>
        `;
        
        overlay.appendChild(popup);
        document.body.appendChild(overlay);
        
        // 自动关闭
        setTimeout(() => {
            if (overlay.parentNode) {
                overlay.style.animation = 'fadeIn 0.3s ease-in-out reverse';
                setTimeout(() => {
                    overlay.remove();
                }, 300);
            }
        }, config.showDuration);
        
        // 点击背景关闭
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                overlay.style.animation = 'fadeIn 0.3s ease-in-out reverse';
                setTimeout(() => {
                    overlay.remove();
                }, 300);
            }
        });
    }

    // 初始化
    function init() {
        // 确保页面加载完成
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
            return;
        }
        
        // 检查是否已经显示过
        if (hasShownPopup()) {
            return;
        }
        
        // 创建样式和弹窗
        createStyles();
        createPopup();
        
        // 设置Cookie，标记已显示
        setCookie();
    }

    // 启动弹窗
    init();
})();
