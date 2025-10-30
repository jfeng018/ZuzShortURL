# ZuzShortURL
## QQ群开放：491102600 欢迎加入！

![Logo](https://cdn.mengze.vip/gh/JanePHPDev/Blog-Static-Resource@main/images/1dfc553c491976d9.png)

[![GitHub Repo stars](https://img.shields.io/github/stars/JanePHPDev/ZuzShortURL?style=social)](https://github.com/JanePHPDev/ZuzShortURL)
[![GitHub forks](https://img.shields.io/github/forks/JanePHPDev/ZuzShortURL?style=social)](https://github.com/JanePHPDev/ZuzShortURL)
[![GitHub license](https://img.shields.io/github/license/JanePHPDev/ZuzShortURL)](https://github.com/JanePHPDev/ZuzShortURL/blob/main/LICENSE)
[![GitHub issues](https://img.shields.io/github/issues/JanePHPDev/ZuzShortURL)](https://github.com/JanePHPDev/ZuzShortURL/issues)

基于PHP + PostgreSQL构建的、为创业团队、电商平台及中小型企业量身打造的下一代短链接SaaS解决方案。:rocket:

[在线Demo](https://zuz.asia) | [项目官网](https://zeinklab.com/)  
Demo站后台地址：https://zuz.asia/admin  
Demo站后台token：admintoken  
Demo站会定时清空数据，请勿长期使用，需要短链接服务请访问官网

![ZuzShortURL UI预览截图](https://cdn.mengze.vip/gh/JanePHPDev/Blog-Static-Resource@main/images/46cc19f8046633fa.png)

> *Release仅用于版本发布，使用时请Fork本仓库或点击一键部署。*  
> *由于Vercel与PHP存在部分冲突，故依赖Composer的功能如二维码生成及活码管理~~退出上线~~。* [^1]  
> *当前仓库内为1.1.9-C2稳定版更新，Docker仓库已同步更新。*

![Star History Chart](https://api.star-history.com/svg?repos=JanePHPDev/ZuzShortURL&type=Date)

## 目录

- [Docker 部署方案](#docker-部署方案)
- [Apache + PostgreSQL 部署方案](#apache--postgresql-部署方案)
  - [准备 PostgreSQL 数据库](#准备-postgresql-数据库)
  - [配置 Apache 虚拟主机](#配置-apache-虚拟主机)
  - [启用必要的 Apache 模块](#启用必要的-apache-模块)
  - [设置文件权限](#设置文件权限)
- [环境变量格式](#环境变量格式)
- [本地测试](#本地测试)
- [数据库迁移](#数据库迁移)
- [关于Vercel免费搭建](#关于vercel免费搭建)
- [免费数据库方案（Supabase）](#免费数据库方案supabase)
- [⚠️ 贡献政策例外声明](#️贡献政策例外声明)

## Docker 部署方案

本项目提供 Docker 镜像支持，使用 Apache + PHP 8.3 构建，暴露 8437 端口（可自定义映射）。镜像已预装 `pdo_pgsql` 扩展、启用 `rewrite` 和 `env` 模块，并配置伪静态。**当前不支持 Docker Compose**，请使用 `docker run` 手动部署。

### 前提条件
- 安装 Docker（[官方下载](https://www.docker.com/products/docker-desktop/)）。
- 准备 PostgreSQL 数据库（参考 [免费数据库方案（Supabase）](#免费数据库方案supabase) 或手动部署）。
- 环境变量：`DATABASE_URL` 和 `ADMIN_TOKEN`（格式见 [环境变量格式](#环境变量格式)）。

### 拉取镜像
```sh
docker pull janephpdev/zuzshorturl:latest
```

### 运行容器
```sh
docker run -d \
  --name zuzshorturl-app \
  -e DATABASE_URL=postgresql://<你的用户名>:<你的密码>@<数据库地址>:<端口>/<数据库名> \
  -e ADMIN_TOKEN=<你的管理员令牌> \
  -p 8437:8437 \
  janephpdev/zuzshorturl:latest
```

- **参数说明**：
  - `-d`：后台运行。
  - `--name`：容器名称（便于管理，如 `docker stop zuzshorturl-app` 停止）。
  - `-e`：注入环境变量（替换占位符）。
  - `-p 8437:8437`：端口映射（宿主机 8437 → 容器 8437）。
- **自定义端口**：如需其他端口，用 `-p 8080:8437`（宿主机 8080 → 容器 8437）。

### 访问与初始化
1. 浏览器访问 `http://localhost:8437`（或自定义端口）。
2. 首次运行需执行数据库迁移：访问 `http://localhost:8437/migrate`，输入 `ADMIN_TOKEN`，点击“运行迁移”。
3. 迁移成功后，重定向到管理面板（`/admin`）。

### 支持 Nginx 反向代理
在宿主机 Nginx 配置中代理 8437 端口（示例 `/etc/nginx/sites-available/default`）：
```nginx
server {
    listen 80;
    server_name yourdomain.com;

    location / {
        proxy_pass http://localhost:8437;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```
- 重启 Nginx：`sudo systemctl restart nginx`。
- 为 HTTPS 配置 SSL（如 Let's Encrypt）。

### 管理容器
- 查看日志：`docker logs zuzshorturl-app`。
- 停止/移除：`docker stop zuzshorturl-app && docker rm zuzshorturl-app`。
- 更新镜像：拉取新版后重新运行。

**提示**：如PostgreSQL数据库在宿主机应该把数据库地址写为服务器公网地址，而非127.0.0.1，因为Docker中127.0.0.1指容器本身而非宿主机

## Apache + PostgreSQL 部署方案

### 准备 PostgreSQL 数据库

首先创建数据库和用户，并授予必要的权限（请将`<数据库名>`等占位符替换为实际值，例如`zuz_db`）：

```sql
-- 创建数据库
CREATE DATABASE <数据库名>;

-- 创建用户
CREATE USER <数据库用户名> WITH PASSWORD '<数据库密码>';

-- 授予数据库权限
GRANT ALL PRIVILEGES ON DATABASE <数据库名> TO <数据库用户名>;

-- 连接到数据库
\c <数据库名>

-- 授予 Schema 权限（重要！）
ALTER SCHEMA public OWNER TO <数据库用户名>;
GRANT ALL ON SCHEMA public TO <数据库用户名>;
```

**提示**：执行后无需重复连接数据库。:bulb:

### 配置 Apache 虚拟主机

编辑 Apache 配置文件（通常在 `/etc/apache2/sites-available/` 或 `/etc/httpd/conf.d/`）：

```apache
<VirtualHost *:80>
    # 强制跳转到 HTTPS
    Redirect permanent / https://<你的域名>/
</VirtualHost>

<VirtualHost *:443>
    # 运行目录
    DocumentRoot /var/www/<站点根目录>/api

    # 环境变量（建议使用英文注释以避免解析问题）
    SetEnv DATABASE_URL "postgresql://<你的用户名>:<你的密码>@<数据库地址>:<端口>/<数据库名>"
    SetEnv ADMIN_TOKEN   "<你的管理员令牌>"
</VirtualHost>
```

**提示**：为HTTPS配置SSL证书（如使用Let's Encrypt）以确保安全。:lock:

### 启用必要的 Apache 模块

```sh
# 启用 rewrite 模块（用于伪静态）
sudo a2enmod rewrite

# 启用 env 模块（用于环境变量）
sudo a2enmod env

# 重启 Apache
sudo systemctl restart apache2
```

### 设置文件权限

```sh
# 进入项目目录
cd /var/www/<网站根目录>

# 设置所有者为 Apache 用户（根据系统不同可能是 www-data 或 apache）
sudo chown -R www-data:www-data .

# 设置适当的权限（生产环境可细化：PHP文件644，目录755）
sudo chmod -R 755 .
```

运行数据库迁移必须保持这些：
- [x] 确保 PostgreSQL 服务正在运行
- [x] 确保 PHP 已安装 `pdo_pgsql` 扩展
- [ ]  :warning:如果权限不足，检查用户设置

## 环境变量格式

项目的PostgreSQL连接符和Admin登录Token采用环境变量存储，这样可以做到几乎绝对的安全性。  
如果需要部署到个人VPS上，可参考上方内容，如不使用Apache，必须手动导入下方内容到环境变量。

```env
DATABASE_URL=postgresql://<你的用户名>:<你的密码>@<数据库地址>:<端口>/<数据库名>
ADMIN_TOKEN=你的Token
```

## 本地测试

进入项目根目录之后可使用如下命令进行本地调试

```sh
php -S localhost:8000 -t . api/index.php
```

在使用Nginx、阿帕奇、IIS等服务器软件时可将运行目录设置为`api/`，然后编写伪静态。  
代码中已内置阿帕奇的伪静态方案。

## 数据库迁移

在首次部署或数据库重置后，您需要手动运行数据库迁移来初始化表结构，这需要你的PostgreSQL用户拥有CREATE权限。请按照以下步骤操作：

1. 确保已设置环境变量 `DATABASE_URL` 和 `ADMIN_TOKEN`。
2. 通过浏览器访问 `你的域名/migrate`。
3. 输入管理员Token。
4. 点击“运行迁移”按钮。
5. 迁移成功后，将自动重定向到管理面板。

**:exclamation:注意**：迁移只需运行一次，后续无需重复执行。如果数据库未迁移，系统可能无法正常运行。

**迁移失败常见原因**：
- 权限不足：确保用户有CREATE权限。
- Token错误：检查环境变量是否正确。
- 数据库连接失败：验证DATABASE_URL格式。

## 关于Vercel免费搭建

为了满足某些白嫖用户，我们专门针对Vercel做了部署支持。  
Fork本仓库后，进入Vercel控制台导入该项目，按照环境变量格式填好环境变量即可搭建成功。  
也可点击如下链接一键部署，部署成功后再填环境变量之后重新Deploy一次即可。  

[![Deploy with Vercel](https://vercel.com/button)](https://vercel.com/import/project?template=https://github.com/JanePHPDev/ZuzShortURL)

同样，部署完成之后首次访问需要进行运行迁移，请参考上方给出的方案：  

> 1. 确保已设置环境变量 `DATABASE_URL` 和 `ADMIN_TOKEN`。  
> 2. 通过浏览器访问 `你的域名/migrate`。  
> 3. 输入管理员Token。  
> 4. 点击“运行迁移”按钮。  
> 5. 迁移成功后，将自动重定向到管理面板。  

## 免费数据库方案（Supabase）

| 步骤 | 操作 |
|------|------|
| 1 | 注册 [Supabase](https://app.supabase.com) → 新建一个 Project（免费额度 500 MB 存储、每日 500 万次 API 调用） |
| 2 | 面包屑导航找到Connect按钮 → 选择 `URI` 格式 → 选择Session pooler |
| 3 | 复制连接串，格式大致如下：<br>`postgresql://用户名:密码@aws-0-ap-southeast-1.pooler.supabase.com:5432/postgres`（建议模糊密码如`***`） |
| 4 | 把连接串粘贴到 Vercel 环境变量 `DATABASE_URL` 即可，无需额外建表，程序首次访问自动迁移 |

> Supabase 免费额度足够个人使用，超出后可一键升级，按量计费。:moneybag:

## ⚠️ 贡献政策例外声明

此项目使用 [MIT 协议](LICENSE) 开源，**允许自由使用、修改、分发、商用**。  
但**我明确拒绝任何拉取请求（Pull Request）**。请**不要提交 PR**，它们会被立即关闭。  
如果你有新的想法，请提交Issue，我会视情况采纳；若发现项目有紧急漏洞或安全漏洞，请发送邮件到Master@Zeapi.ink。  
你可以自由 fork 并维护自己的分支，但**不要试图将修改合并回本仓库**。  
:no_entry:这不是开源的标准做法，但这是我选择的方式。

[^1]: 此处使用删除线表示功能调整，但实际未完全退出，仅受Vercel限制。