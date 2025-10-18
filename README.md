# ZuzShortURL
这是一个基于 PHP 开发、使用 PostgreSQL 数据库的短链接系统，前端采用 TailwindCSS，并针对 Vercel 做了专门的部署优化。  
如果你不想自己维护数据库，可直接使用 Supabase 提供的免费 PostgreSQL 方案，**5 分钟上线**。
<img src="https://cdn.mengze.vip/gh/JanePHPDev/Blog-Static-Resource@main/images/d2fc9d8ee03eb8a8.jpg" title="双端预览图" />

*Release仅用于版本发布，使用时请Frok本仓库或点击一键部署。
*很抱歉，由于PHP和Vercel的一些冲突，导致二维码和图形验证码一直没上线，以及目录结构混乱，正在寻找解决方法

## 环境变量格式

项目的PostgreSQL连接符和Admin登录Token采用环境变量存储，这样可以做到几乎绝对的安全性。
```env
DATABASE_URL="你的PostgreSQL连接符"
ADMIN_TOKEN="你的Token"
```

## 本地测试

进入项目根目录之后可使用如下命令进行本地调试
```sh
php -S localhost:8000 -t . api/index.php
```

在使用Nginx、阿帕奇、IIS等服务器软件时可将运行目录设置为`api/`，然后编写伪静态。

## Vercel搭建

Frok本仓库后，进入Vercel控制台导入该项目，按照环境变量格式填好环境变量即可搭建成功。
也可点击如下链接一键部署，部署成功后再填环境变量之后重新Deploy一次即可。  
[![Deploy with Vercel](https://vercel.com/button)](https://vercel.com/import/project?template=https://github.com/JanePHPDev/ZuzShortURL)

## 免费数据库方案（Supabase）

| 步骤 | 操作 |
|---|---|
| 1 | 注册 [Supabase](https://app.supabase.com) → 新建一个 Project（免费额度 500 MB 存储、每日 500 万次 API 调用） |
| 2 | 面包导航找到Connect按钮 → 选择 `URI` 格式 → 选择Session pooler |
| 3 | 复制连接串，格式大致如下：<br>`postgresql://用户名:密码@aws-0-ap-southeast-1.pooler.supabase.com:5432/postgres` |
| 4 | 把连接串粘贴到 Vercel 环境变量 `DATABASE_URL` 即可，无需额外建表，程序首次访问自动迁移 |

> Supabase 免费额度足够个人使用，超出后可一键升级，按量计费。

## ⚠️ 贡献政策例外声明
此项目使用 [MIT 协议](LICENSE) 开源，**允许自由使用、修改、分发、商用**。  
但**我明确拒绝任何拉取请求（Pull Request）**。请**不要提交 PR**，它们会被立即关闭。  
如果你有新的想法，请提交Issue，我会视情况采纳；若发现项目有紧急漏洞或安全漏洞，请发送邮件到Master@Zeapi.ink。
你可以自由 fork 并维护自己的分支，但**不要试图将修改合并回本仓库**。  
这不是开源的标准做法，但这是我选择的方式。
