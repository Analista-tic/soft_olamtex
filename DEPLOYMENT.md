# Deployment Guide - Zero-Knowledge Password Manager

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Server Setup](#server-setup)
3. [Application Installation](#application-installation)
4. [Database Configuration](#database-configuration)
5. [Web Server Configuration](#web-server-configuration)
6. [SSL/TLS Setup](#ssltls-setup)
7. [Environment Configuration](#environment-configuration)
8. [Post-Deployment](#post-deployment)
9. [Maintenance](#maintenance)
10. [Troubleshooting](#troubleshooting)

## Prerequisites

### Required Software

- **PHP**: 8.2 or higher
- **Composer**: Latest version
- **Node.js**: 18.x or higher
- **NPM**: 9.x or higher
- **Database**: MySQL 8.0+ or PostgreSQL 13+
- **Web Server**: Nginx 1.18+ or Apache 2.4+
- **Git**: For version control

### Recommended Server Specifications

**Minimum (Single User):**
- 1 CPU core
- 2 GB RAM
- 20 GB SSD storage
- 100 Mbps network

**Recommended (5-10 Users):**
- 2 CPU cores
- 4 GB RAM
- 40 GB SSD storage
- 1 Gbps network

**Production (50+ Users):**
- 4+ CPU cores
- 8+ GB RAM
- 100+ GB SSD storage
- 1 Gbps network

## Server Setup

### 1. Update System Packages

```bash
# Ubuntu/Debian
sudo apt update && sudo apt upgrade -y

# CentOS/RHEL
sudo yum update -y
```

### 2. Install PHP 8.2

```bash
# Ubuntu/Debian
sudo apt install software-properties-common
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install php8.2 php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-gd php8.2-bcmath

# Verify installation
php -v
```

### 3. Install Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# Verify installation
composer --version
```

### 4. Install Node.js and NPM

```bash
# Using NodeSource repository
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# Verify installation
node --version
npm --version
```

### 5. Install Database

**MySQL:**
```bash
sudo apt install mysql-server
sudo mysql_secure_installation

# Create database
sudo mysql -u root -p
CREATE DATABASE vault_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'vault_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON vault_db.* TO 'vault_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

**PostgreSQL:**
```bash
sudo apt install postgresql postgresql-contrib
sudo -u postgres psql

CREATE DATABASE vault_db;
CREATE USER vault_user WITH PASSWORD 'your_secure_password';
GRANT ALL PRIVILEGES ON DATABASE vault_db TO vault_user;
\q
```

## Application Installation

### 1. Clone Repository

```bash
cd /var/www
sudo git clone https://github.com/yourusername/vault-app.git
cd vault-app
```

### 2. Set Permissions

```bash
sudo chown -R www-data:www-data /var/www/vault-app
sudo chmod -R 755 /var/www/vault-app
sudo chmod -R 775 /var/www/vault-app/storage
sudo chmod -R 775 /var/www/vault-app/bootstrap/cache
```

### 3. Install Dependencies

```bash
# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install JavaScript dependencies
npm ci --production
```

### 4. Build Frontend Assets

```bash
npm run build
```

## Database Configuration

### 1. Configure Environment

```bash
cp .env.example .env
nano .env
```

Update database credentials:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vault_db
DB_USERNAME=vault_user
DB_PASSWORD=your_secure_password
```

### 2. Generate Application Key

```bash
php artisan key:generate
```

### 3. Run Migrations

```bash
php artisan migrate --force
```

### 4. Optimize Application

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Web Server Configuration

### Nginx Configuration

Create `/etc/nginx/sites-available/vault-app`:

```nginx
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    root /var/www/vault-app/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/vault-app /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### Apache Configuration

Create `/etc/apache2/sites-available/vault-app.conf`:

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    DocumentRoot /var/www/vault-app/public

    <Directory /var/www/vault-app/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/vault-app-error.log
    CustomLog ${APACHE_LOG_DIR}/vault-app-access.log combined

    # Security headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
</VirtualHost>
```

Enable the site:
```bash
sudo a2ensite vault-app
sudo a2enmod rewrite headers
sudo systemctl reload apache2
```

## SSL/TLS Setup

### Using Let's Encrypt (Free)

```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx  # For Nginx
# OR
sudo apt install certbot python3-certbot-apache # For Apache

# Obtain certificate
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com
# OR
sudo certbot --apache -d yourdomain.com -d www.yourdomain.com

# Verify auto-renewal
sudo certbot renew --dry-run
```

### Manual SSL Configuration (Nginx)

Update `/etc/nginx/sites-available/vault-app`:

```nginx
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;
    root /var/www/vault-app/public;

    # SSL Configuration
    ssl_certificate /path/to/fullchain.pem;
    ssl_certificate_key /path/to/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    # HSTS
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;

    # Rest of configuration...
}

# Redirect HTTP to HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name yourdomain.com www.yourdomain.com;
    return 301 https://$server_name$request_uri;
}
```

## Environment Configuration

### Production .env File

```env
APP_NAME="Secure Vault"
APP_ENV=production
APP_KEY=base64:GENERATED_KEY_HERE
APP_DEBUG=false
APP_URL=https://yourdomain.com

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vault_db
DB_USERNAME=vault_user
DB_PASSWORD=your_secure_password

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database

CACHE_STORE=database
CACHE_PREFIX=vault_

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=strict

SANCTUM_STATEFUL_DOMAINS=yourdomain.com,www.yourdomain.com
SESSION_DOMAIN=.yourdomain.com

# Mail Configuration (for notifications)
MAIL_MAILER=smtp
MAIL_HOST=smtp.yourprovider.com
MAIL_PORT=587
MAIL_USERNAME=your_email@yourdomain.com
MAIL_PASSWORD=your_mail_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

### Secure .env File

```bash
sudo chmod 600 .env
sudo chown www-data:www-data .env
```

## Post-Deployment

### 1. Create First User

```bash
php artisan tinker
```

```php
$user = new App\Models\User();
$user->name = 'Admin User';
$user->email = 'admin@yourdomain.com';
$user->password = bcrypt('temporary_password');
$user->save();
```

### 2. Set Up Cron Jobs

```bash
sudo crontab -e -u www-data
```

Add:
```cron
* * * * * cd /var/www/vault-app && php artisan schedule:run >> /dev/null 2>&1
```

### 3. Configure Queue Worker (Optional)

For background jobs:

```bash
sudo nano /etc/systemd/system/vault-worker.service
```

```ini
[Unit]
Description=Vault Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/vault-app
ExecStart=/usr/bin/php /var/www/vault-app/artisan queue:work --sleep=3 --tries=3
Restart=always

[Install]
WantedBy=multi-user.target
```

Enable and start:
```bash
sudo systemctl enable vault-worker
sudo systemctl start vault-worker
```

### 4. Set Up Firewall

```bash
sudo ufw allow 'Nginx Full'  # or 'Apache Full'
sudo ufw allow OpenSSH
sudo ufw enable
```

### 5. Configure Fail2Ban (Optional but Recommended)

```bash
sudo apt install fail2ban
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

## Maintenance

### Backups

#### Automated Backup Script

Create `/usr/local/bin/backup-vault.sh`:

```bash
#!/bin/bash

BACKUP_DIR="/backups/vault"
DATE=$(date +%Y%m%d_%H%M%S)
APP_DIR="/var/www/vault-app"

# Create backup directory
mkdir -p $BACKUP_DIR

# Backup database
mysqldump -u vault_user -p'your_password' vault_db > $BACKUP_DIR/db_$DATE.sql

# Backup application files
tar -czf $BACKUP_DIR/app_$DATE.tar.gz $APP_DIR

# Encrypt backups (optional)
gpg --encrypt --recipient admin@yourdomain.com $BACKUP_DIR/db_$DATE.sql
gpg --encrypt --recipient admin@yourdomain.com $BACKUP_DIR/app_$DATE.tar.gz

# Remove old backups (older than 30 days)
find $BACKUP_DIR -type f -mtime +30 -delete

echo "Backup completed: $DATE"
```

Make executable and schedule:
```bash
sudo chmod +x /usr/local/bin/backup-vault.sh
sudo crontab -e
```

Add:
```cron
0 2 * * * /usr/local/bin/backup-vault.sh >> /var/log/vault-backup.log 2>&1
```

### Updates

```bash
cd /var/www/vault-app

# Backup before update
sudo /usr/local/bin/backup-vault.sh

# Pull latest code
sudo git pull

# Update dependencies
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data npm ci --production
sudo -u www-data npm run build

# Run migrations
php artisan migrate --force

# Clear and rebuild caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart services
sudo systemctl reload php8.2-fpm
sudo systemctl reload nginx  # or apache2
sudo systemctl restart vault-worker  # if using queue worker
```

### Monitoring

#### Log Files

```bash
# Application logs
tail -f /var/www/vault-app/storage/logs/laravel.log

# Web server logs
tail -f /var/log/nginx/error.log    # Nginx
tail -f /var/log/apache2/error.log  # Apache

# System logs
journalctl -u php8.2-fpm -f
journalctl -u nginx -f
```

## Troubleshooting

### Common Issues

#### 1. Permission Errors

```bash
sudo chown -R www-data:www-data /var/www/vault-app
sudo chmod -R 755 /var/www/vault-app
sudo chmod -R 775 /var/www/vault-app/storage
sudo chmod -R 775 /var/www/vault-app/bootstrap/cache
```

#### 2. 500 Internal Server Error

```bash
# Check logs
tail -f /var/www/vault-app/storage/logs/laravel.log

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

#### 3. Database Connection Issues

```bash
# Test connection
php artisan tinker
DB::connection()->getPdo();

# Check credentials in .env
# Verify database user privileges
```

#### 4. SSL Certificate Issues

```bash
# Verify certificate
openssl x509 -in /path/to/cert.pem -text -noout

# Test SSL configuration
curl -I https://yourdomain.com

# Renew Let's Encrypt certificate
sudo certbot renew
```

#### 5. Application Not Loading

```bash
# Check PHP-FPM
sudo systemctl status php8.2-fpm
sudo systemctl restart php8.2-fpm

# Check web server
sudo systemctl status nginx  # or apache2
sudo nginx -t  # Test nginx configuration
sudo systemctl restart nginx

# Check file permissions
ls -la /var/www/vault-app
```

### Performance Optimization

```bash
# Enable OPcache
sudo nano /etc/php/8.2/fpm/php.ini
```

Add/modify:
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
```

```bash
# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
```

## Production Checklist

Before going live:

- [ ] SSL/TLS certificate installed and working
- [ ] HTTPS enforced (HTTP redirects to HTTPS)
- [ ] Firewall configured
- [ ] Fail2Ban installed (recommended)
- [ ] APP_DEBUG=false in .env
- [ ] Database backups automated
- [ ] Cron jobs configured
- [ ] Log rotation configured
- [ ] Monitoring set up
- [ ] Security headers configured
- [ ] Rate limiting enabled
- [ ] First user account created
- [ ] Change all default passwords
- [ ] Document admin credentials securely
- [ ] Test vault functionality
- [ ] Test backup restoration

## Support

For deployment issues:
- Check logs: `/var/www/vault-app/storage/logs/laravel.log`
- Review web server error logs
- Consult Laravel documentation: https://laravel.com/docs

## Security Note

This is a password manager handling sensitive data. Always:
- Keep all software updated
- Use strong passwords
- Enable HTTPS
- Regular backups
- Monitor logs for suspicious activity
- Follow security best practices in SECURITY.md
