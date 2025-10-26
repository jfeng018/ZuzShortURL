# ZuzShortURL
## QQ Group Open: 491102600 Welcome to Join!

![Logo](https://cdn.mengze.vip/gh/JanePHPDev/Blog-Static-Resource@main/images/1dfc553c491976d9.png)

[![GitHub Repo stars](https://img.shields.io/github/stars/JanePHPDev/ZuzShortURL?style=social)](https://github.com/JanePHPDev/ZuzShortURL)
[![GitHub forks](https://img.shields.io/github/forks/JanePHPDev/ZuzShortURL?style=social)](https://github.com/JanePHPDev/ZuzShortURL)
[![GitHub license](https://img.shields.io/github/license/JanePHPDev/ZuzShortURL)](https://github.com/JanePHPDev/ZuzShortURL/blob/main/LICENSE)
[![GitHub issues](https://img.shields.io/github/issues/JanePHPDev/ZuzShortURL)](https://github.com/JanePHPDev/ZuzShortURL/issues)

A next-generation short URL SaaS solution built with PHP + PostgreSQL, tailored for startup teams, e-commerce platforms, and small to medium-sized enterprises. :rocket:

[Live Demo](https://zuz.asia) | [Project Website](https://zeinklab.com/) | [中文文档](README_CN.MD)
Demo Admin URL: https://zuz.asia/admin  
Demo Admin Token: admintoken  
The demo site clears data periodically—please don't use it for long-term needs. For production short URL services, visit the official site.

![ZuzShortURL Dual-View Screenshots](https://cdn.mengze.vip/gh/JanePHPDev/Blog-Static-Resource@main/images/682ef55dd97a397c.jpg)

> *Releases are for version announcements only. For usage, please fork this repo or use one-click deployment.*  
> *Due to some conflicts between Vercel and PHP, features relying on Composer—like QR code generation and live code management—have been ~~temporarily suspended~~.* [^1]  
> *The current warehouse is updated with the 1.1.9-C2 stable version, and the Docker warehouse has been updated synchronously.*

![Star History Chart](https://api.star-history.com/svg?repos=JanePHPDev/ZuzShortURL&type=Date)

## Table of Contents

- [Docker Deployment](#docker-deployment)
- [Apache + PostgreSQL Deployment](#apache--postgresql-deployment)
  - [Prepare PostgreSQL Database](#prepare-postgresql-database)
  - [Configure Apache Virtual Host](#configure-apache-virtual-host)
  - [Enable Required Apache Modules](#enable-required-apache-modules)
  - [Set File Permissions](#set-file-permissions)
- [Environment Variable Format](#environment-variable-format)
- [Local Testing](#local-testing)
- [Database Migration](#database-migration)
- [About Free Vercel Deployment](#about-free-vercel-deployment)
- [Free Database Option (Supabase)](#free-database-option-supabase)
- [⚠️ Contribution Policy Exception Notice](#️-contribution-policy-exception-notice)

## Docker Deployment

This project provides Docker image support, built with Apache + PHP 8.3, exposing port 8437 (customizable). The image includes the `pdo_pgsql` extension, enables `rewrite` and `env` modules, and is pre-configured for URL rewriting. **Docker Compose is not supported yet**—use `docker run` for manual deployment.

### Prerequisites
- Install Docker ([official download](https://www.docker.com/products/docker-desktop/)).
- Prepare a PostgreSQL database (see [Free Database Option (Supabase)](#free-database-option-supabase) or deploy manually).
- Environment variables: `DATABASE_URL` and `ADMIN_TOKEN` (format in [Environment Variable Format](#environment-variable-format)).

### Pull the Image
```sh
docker pull janephpdev/zuzshorturl:latest
```

### Run the Container
```sh
docker run -d \
  --name zuzshorturl-app \
  -e DATABASE_URL=postgresql://<your-username>:<your-password>@<database-host>:<port>/<database-name> \
  -e ADMIN_TOKEN=<your-admin-token> \
  -p 8437:8437 \
  janephpdev/zuzshorturl:latest
```

- **Parameter Notes**:
  - `-d`: Run in detached mode (background).
  - `--name`: Container name (for easy management, e.g., `docker stop zuzshorturl-app` to stop).
  - `-e`: Inject environment variables (replace placeholders).
  - `-p 8437:8437`: Port mapping (host 8437 → container 8437).
- **Custom Port**: For a different port, use `-p 8080:8437` (host 8080 → container 8437).

### Access and Initialization
1. Open your browser and visit `http://localhost:8437` (or your custom port).
2. On first run, perform database migration: Visit `http://localhost:8437/migrate`, enter `ADMIN_TOKEN`, and click "Run Migration".
3. After successful migration, you'll be redirected to the admin panel (`/admin`).

### Nginx Reverse Proxy Support
Proxy port 8437 in your host's Nginx config (example for `/etc/nginx/sites-available/default`):
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
- Restart Nginx: `sudo systemctl restart nginx`.
- Configure SSL for HTTPS (e.g., via Let's Encrypt).

### Manage the Container
- View logs: `docker logs zuzshorturl-app`.
- Stop/remove: `docker stop zuzshorturl-app && docker rm zuzshorturl-app`.
- Update image: Pull the new version and rerun.

**Tip**: If your PostgreSQL database is on the host machine, use the server's public IP for the database host, not 127.0.0.1—inside Docker, 127.0.0.1 refers to the container itself, not the host.

## Apache + PostgreSQL Deployment

### Prepare PostgreSQL Database

First, create the database and user, and grant necessary permissions (replace placeholders like `<database-name>` with actual values, e.g., `zuz_db`):

```sql
-- Create database
CREATE DATABASE <database-name>;

-- Create user
CREATE USER <database-username> WITH PASSWORD '<database-password>';

-- Grant database privileges
GRANT ALL PRIVILEGES ON DATABASE <database-name> TO <database-username>;

-- Connect to the database
\c <database-name>

-- Grant schema privileges (important!)
ALTER SCHEMA public OWNER TO <database-username>;
GRANT ALL ON SCHEMA public TO <database-username>;
```

**Tip**: No need to reconnect after running these. :bulb:

### Configure Apache Virtual Host

Edit your Apache config file (usually in `/etc/apache2/sites-available/` or `/etc/httpd/conf.d/`):

```apache
<VirtualHost *:80>
    # Force redirect to HTTPS
    Redirect permanent / https://<your-domain>/
</VirtualHost>

<VirtualHost *:443>
    # Document root
    DocumentRoot /var/www/<site-root>/api

    # Environment variables (use English comments to avoid parsing issues)
    SetEnv DATABASE_URL "postgresql://<your-username>:<your-password>@<database-host>:<port>/<database-name>"
    SetEnv ADMIN_TOKEN   "<your-admin-token>"
</VirtualHost>
```

**Tip**: Set up SSL certificates for HTTPS (e.g., with Let's Encrypt) for security. :lock:

### Enable Required Apache Modules

```sh
# Enable rewrite module (for URL rewriting)
sudo a2enmod rewrite

# Enable env module (for environment variables)
sudo a2enmod env

# Restart Apache
sudo systemctl restart apache2
```

### Set File Permissions

```sh
# Enter project directory
cd /var/www/<site-root>

# Set owner to Apache user (may be www-data or apache depending on your system)
sudo chown -R www-data:www-data .

# Set appropriate permissions (for production, refine: PHP files 644, dirs 755)
sudo chmod -R 755 .
```

For database migration to work, ensure:
- [x] PostgreSQL service is running
- [x] PHP has the `pdo_pgsql` extension installed
- [ ] :warning: If permissions are insufficient, check user settings

## Environment Variable Format

The project stores the PostgreSQL connection string and admin login token as environment variables for maximum security.  
For deployment on a personal VPS, refer to the sections above. If not using Apache, manually add these to your environment variables.

```env
DATABASE_URL=postgresql://<your-username>:<your-password>@<database-host>:<port>/<database-name>
ADMIN_TOKEN=your-token
```

## Local Testing

After entering the project root directory, use the following command for local debugging:

```sh
php -S localhost:8000 -t . api/index.php
```

When using servers like Nginx, Apache, or IIS, set the root directory to `api/`, and configure URL rewriting.  
Apache rewriting rules are already built into the code.

## Database Migration

After initial deployment or database reset, manually run the migration to set up the table structure. Your PostgreSQL user needs CREATE privileges. Follow these steps:

1. Ensure `DATABASE_URL` and `ADMIN_TOKEN` environment variables are set.
2. Visit `your-domain/migrate` in your browser.
3. Enter the admin token.
4. Click the "Run Migration" button.
5. On success, you'll be automatically redirected to the admin panel.

**:exclamation: Note**: Run migration only once—re-running isn't needed. Without migration, the system may not function properly.

**Common Migration Failure Causes**:
- Insufficient privileges: Ensure the user has CREATE permissions.
- Incorrect token: Double-check the environment variable.
- Database connection failure: Verify DATABASE_URL format.

## About Free Vercel Deployment

To accommodate users looking for free hosting, we've added specific support for Vercel.  
Fork this repo, then import it into your Vercel dashboard and fill in the environment variables as per the format above.  
Or use the one-click deploy link below—after deployment, add env vars and redeploy once.

[![Deploy with Vercel](https://vercel.com/button)](https://vercel.com/import/project?template=https://github.com/JanePHPDev/ZuzShortURL)

After deployment, run the initial migration on first access—see the steps above:

> 1. Ensure `DATABASE_URL` and `ADMIN_TOKEN` environment variables are set.  
> 2. Visit `your-domain/migrate` in your browser.  
> 3. Enter the admin token.  
> 4. Click the "Run Migration" button.  
> 5. On success, you'll be automatically redirected to the admin panel.

## Free Database Option (Supabase)

| Step | Action |
|------|--------|
| 1 | Sign up at [Supabase](https://app.supabase.com) → Create a new Project (free tier: 500 MB storage, 5M API calls/day) |
| 2 | In the breadcrumb nav, find the Connect button → Select `URI` format → Choose Session pooler |
| 3 | Copy the connection string, which looks like: <br>`postgresql://username:password@aws-0-ap-southeast-1.pooler.supabase.com:5432/postgres` (mask password like `***` for safety) |
| 4 | Paste the string into Vercel's `DATABASE_URL` env var—no need to create tables manually; the app auto-migrates on first access |

> Supabase's free tier is plenty for personal use. Upgrade on-demand if you exceed limits. :moneybag:

## ⚠️ Contribution Policy Exception Notice

This project is open-source under the [MIT License](LICENSE), **allowing free use, modification, distribution, and commercial applications**.  
However, **I explicitly reject all pull requests (PRs)**. Please **do not submit PRs**—they will be closed immediately.  
If you have new ideas, open an Issue instead, and I'll consider them. For urgent bugs or security issues, email Master@Zeapi.ink.  
You're welcome to fork and maintain your own branch, but **do not attempt to merge changes back into this repo**.  
:no_entry: This isn't standard open-source practice, but it's my choice.

[^1]: Strikethrough here indicates a feature adjustment, but it's not fully removed—only limited by Vercel.