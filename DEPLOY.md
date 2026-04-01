# ViaKashmir Lead CRM — Deployment Guide

Complete step-by-step for deploying on shared hosting (cPanel) or a VPS.

---

## Requirements

| Requirement | Minimum | Notes |
|---|---|---|
| PHP | 8.0+ | 8.2 recommended |
| MySQL / MariaDB | 5.7+ / 10.4+ | Create a blank DB first |
| Apache mod_rewrite | enabled | For shared hosting (.htaccess) |
| OR nginx | any | Use provided nginx.conf |
| Composer | 2.x | For Google Ads library |
| PHP extensions | curl, pdo_mysql, json | Enabled by default on most hosts |

---

## Option A — Shared Hosting / cPanel (Easiest)

### Step 1 — Upload files
Upload the entire `php/` folder contents to your `public_html` (or a subfolder like `public_html/crm/`).

```
public_html/
├── .htaccess
├── config.php
├── index.php
├── install.php
├── cron.php
├── composer.json
├── src/
├── templates/
└── public/
```

### Step 2 — Create MySQL database
In cPanel → **MySQL Databases**:
1. Create a new database: e.g. `yourusername_viakashmir`
2. Create a new user with a strong password
3. Add the user to the database with **All Privileges**

### Step 3 — Edit config.php
Open `config.php` in cPanel File Manager and fill in your database credentials:

```php
'db' => [
    'host'    => 'localhost',
    'port'    => 3306,
    'name'    => 'yourusername_viakashmir',   // ← your DB name
    'user'    => 'yourusername_dbuser',        // ← your DB user
    'pass'    => 'your_strong_password',       // ← your DB password
    'charset' => 'utf8mb4',
],
```

Also set your Meta webhook verify token:
```php
'meta' => [
    'webhook_verify_token' => 'your_random_secret_string_here',
],
```

### Step 4 — Install Composer dependencies
Via SSH in cPanel Terminal:
```bash
cd public_html    # or wherever you uploaded
composer install --no-dev --optimize-autoloader
```

> **No SSH access?** Upload a pre-built `vendor/` folder.  
> Run `composer install` locally first, then zip and upload the `vendor/` folder.

### Step 5 — Run the installer
Open in your browser:
```
https://yourdomain.com/install.php
```
You should see green checkmarks for all 4 tables.

**Delete install.php immediately after!**
```bash
rm install.php
```
Or delete it via cPanel File Manager.

### Step 6 — Set up the cron job
In cPanel → **Cron Jobs** → Add New:

| Field | Value |
|---|---|
| Minute | */15 |
| Hour | * |
| Day | * |
| Month | * |
| Weekday | * |
| Command | `php /home/yourusername/public_html/cron.php >> /home/yourusername/logs/crm.log 2>&1` |

> Create the `logs/` directory first if it doesn't exist.

### Step 7 — Open the CRM
Navigate to: `https://yourdomain.com/`

---

## Option B — VPS (Ubuntu/Debian with nginx)

### Step 1 — Install requirements
```bash
sudo apt update
sudo apt install php8.2-fpm php8.2-mysql php8.2-curl php8.2-json \
                 nginx mysql-server composer -y
```

### Step 2 — Upload files
```bash
sudo mkdir -p /var/www/viakashmir-crm
sudo chown $USER:www-data /var/www/viakashmir-crm
git clone -b claude/crm-lead-integration-6XhwU \
    https://github.com/aribanigar/leadgenerator.git /tmp/repo
cp -r /tmp/repo/php/. /var/www/viakashmir-crm/
```

### Step 3 — Configure nginx
```bash
sudo cp /var/www/viakashmir-crm/nginx.conf /etc/nginx/sites-available/viakashmir-crm
sudo nano /etc/nginx/sites-available/viakashmir-crm
# → Replace yourdomain.com with your actual domain
# → Adjust php8.2-fpm.sock path if using a different PHP version

sudo ln -s /etc/nginx/sites-available/viakashmir-crm /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

### Step 4 — Create MySQL database
```bash
sudo mysql -u root -p
```
```sql
CREATE DATABASE viakashmir_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'viakashmir'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON viakashmir_crm.* TO 'viakashmir'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Step 5 — Configure and install
```bash
cd /var/www/viakashmir-crm
nano config.php            # fill in DB credentials
composer install --no-dev --optimize-autoloader
php install.php            # creates tables
rm install.php             # delete installer
```

### Step 6 — Set permissions
```bash
sudo chown -R www-data:www-data /var/www/viakashmir-crm
sudo chmod -R 755 /var/www/viakashmir-crm
sudo chmod 600 /var/www/viakashmir-crm/config.php
```

### Step 7 — Cron job
```bash
crontab -e
```
Add:
```
*/15 * * * * php /var/www/viakashmir-crm/cron.php >> /var/log/viakashmir-crm.log 2>&1
```

### Step 8 — SSL (HTTPS) — free with Let's Encrypt
```bash
sudo apt install certbot python3-certbot-nginx -y
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com
```

---

## Connecting Your Ad Accounts

Once deployed, open the CRM and go to **Ad Accounts**:

### Meta (Facebook / Instagram / WhatsApp)
1. Log in to [business.facebook.com](https://business.facebook.com) → Settings → Ad Accounts → copy your **Account ID** (format: `act_XXXXXXXXX`)
2. Go to [developers.facebook.com](https://developers.facebook.com) → Your App → Tools → Graph API Explorer
3. Select your app, generate an **Access Token** with these permissions:
   - `leads_retrieval`
   - `pages_manage_metadata`
   - `pages_read_engagement`
4. Paste both into the CRM → Connect Meta Account
5. Click **Sync Leads Now**

**For real-time leads (webhook):**
1. In Meta Developer Portal → Your App → Webhooks
2. Click **Add Subscriptions** → Page → `leadgen`
3. Callback URL: `https://yourdomain.com/api/webhooks/meta`
4. Verify Token: same string you set in `config.php → meta.webhook_verify_token`

### Google Ads
1. **Developer Token:** Google Ads dashboard → Tools → API Center  
   *(Takes 1-2 business days for approval on new accounts)*
2. **Customer ID:** Top-right of Google Ads dashboard (e.g. `123-456-7890`)
3. **OAuth credentials:** [console.cloud.google.com](https://console.cloud.google.com) → APIs & Services → Credentials → Create OAuth 2.0 Client ID (Web application type)
4. Generate a **Refresh Token** using Google's OAuth2 Playground: [oauth2.googleapis.com/oauth2/playground](https://oauth2.googleapis.com/oauth2/playground)
   - Scope: `https://www.googleapis.com/auth/adwords`
5. Paste all credentials into the CRM → Connect Google Ads Account

---

## Using the CRM

| Step | Where | What to do |
|---|---|---|
| 1 | **Team** tab | Add your staff members (name, email, role) |
| 2 | **Ad Accounts** tab | Connect Meta and/or Google Ads |
| 3 | Sidebar | Click **Sync Leads Now** — leads appear instantly |
| 4 | **All Leads** tab | Change status, assign to agents, add notes |
| Auto | Cron job | Runs every 15 min, imports new leads automatically |

### Lead Status Workflow
```
New → Contacted → Qualified → Converted
                            ↘ Lost
```

---

## Troubleshooting

| Problem | Fix |
|---|---|
| White page / 500 error | Check PHP error log: `tail -f /var/log/php8.2-fpm.log` |
| "Database connection failed" | Verify credentials in `config.php` |
| No leads appearing | Check that ad account is connected; click Sync Now; check cron log |
| Meta webhook not verifying | Ensure `webhook_verify_token` in `config.php` matches exactly what you entered in Meta portal |
| Google "DEVELOPER_TOKEN" error | Token pending approval — check Google Ads → Tools → API Center |
| 404 on all pages | Ensure `mod_rewrite` is enabled (Apache) or nginx config is correct |
| "vendor not found" | Run `composer install` in the project directory |

---

## File Security Checklist

After deployment, verify these are **not** publicly accessible:

- [ ] `config.php` (blocked by .htaccess / nginx.conf)
- [ ] `composer.json` (blocked)
- [ ] `src/` directory (blocked)
- [ ] `install.php` (must be deleted after setup)
- [ ] `cron.php` (blocked — only runs via CLI)

Test by visiting `https://yourdomain.com/config.php` — should return 403 or 404.
