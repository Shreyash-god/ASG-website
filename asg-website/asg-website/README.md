# ASG Studios & ASG Group — Website
## Full-Stack PHP + MySQL Web Application
**Founder:** Shreyash Ghosh | **Domain:** asgstudios.online

---

## ⚡ QUICK START

### Requirements
- PHP 8.1+ (with PDO, PDO_MySQL, mbstring, openssl, fileinfo, GD)
- MySQL 8.0+ or MariaDB 10.6+
- Apache 2.4+ with mod_rewrite enabled
- SSL certificate (Let's Encrypt recommended)

---

## 📁 PROJECT STRUCTURE

```
asg-website/
├── config.php                 ← Core config (keep outside public web root!)
├── includes/
│   └── bootstrap.php          ← DB, Auth, CSRF, helpers
├── api/
│   ├── auth.php               ← Register, login, OAuth
│   ├── posts.php              ← Blog/docs CRUD
│   ├── shop.php               ← Products, checkout
│   ├── donations.php          ← Donations
│   ├── contact.php            ← Contact form
│   └── newsletter.php         ← Newsletter subscribe
├── admin/
│   ├── index.php              ← Admin control panel
│   └── post-editor.php        ← Rich post editor
├── public/
│   ├── index.html             ← Main SPA frontend
│   ├── .htaccess              ← URL rewriting
│   ├── images/
│   │   └── asg-logo.png       ← Your logo
│   ├── uploads/               ← User uploads (auto-created)
│   └── pages/
│       ├── login.php          ← Standalone login
│       ├── privacy.php        ← Privacy policy
│       └── terms.php          ← Terms of service
├── .htaccess                  ← Root security rules
├── database.sql               ← Full DB schema + seed data
└── README.md                  ← This file
```

---

## 🚀 DEPLOYMENT STEPS

### 1. Upload Files
Upload the entire `asg-website/` directory to your server.

**IMPORTANT — Directory Layout on Server:**
```
/var/www/asgstudios.online/     ← Web root (Apache DocumentRoot)
    └── (contents of public/)
/var/www/asg-backend/           ← Backend (outside web root!)
    ├── config.php
    ├── includes/
    └── api/
```

Or keep everything together but ensure `config.php` is protected via `.htaccess`.

### 2. Setup Database
```bash
mysql -u root -p
CREATE DATABASE asg_website CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'asg_dbuser'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD';
GRANT ALL PRIVILEGES ON asg_website.* TO 'asg_dbuser'@'localhost';
FLUSH PRIVILEGES;
EXIT;

mysql -u asg_dbuser -p asg_website < database.sql
```

### 3. Configure Environment Variables
Set these in your server environment (Apache `SetEnv`, `.env`, or hosting panel):

```bash
# Database
DB_HOST=localhost
DB_NAME=asg_website
DB_USER=asg_dbuser
DB_PASS=your_strong_password

# Security (generate random 32+ char strings!)
APP_KEY=your_32_char_random_key_here_now
JWT_SECRET=your_jwt_secret_key_here_long

# Email
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USER=your@gmail.com
MAIL_PASS=your_app_password
MAIL_FROM=noreply@asgstudios.online

# Payments
RAZORPAY_KEY_ID=rzp_live_xxxxxxxxxxxx
RAZORPAY_KEY_SECRET=your_razorpay_secret
PAYPAL_CLIENT_ID=your_paypal_client_id
PAYPAL_SECRET=your_paypal_secret

# OAuth
GOOGLE_CLIENT_ID=xxxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your_google_secret
GITHUB_CLIENT_ID=your_github_client_id
GITHUB_CLIENT_SECRET=your_github_secret
```

### 4. Apache Virtual Host
```apache
<VirtualHost *:443>
    ServerName asgstudios.online
    ServerAlias www.asgstudios.online
    DocumentRoot /var/www/asgstudios.online/public

    <Directory /var/www/asgstudios.online/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Block access to backend
    <Directory /var/www/asgstudios.online>
        Options -Indexes
        AllowOverride None
    </Directory>

    # Alias for API
    Alias /api /var/www/asgstudios.online/api
    Alias /admin /var/www/asgstudios.online/admin

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/asgstudios.online/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/asgstudios.online/privkey.pem

    ErrorLog ${APACHE_LOG_DIR}/asg_error.log
    CustomLog ${APACHE_LOG_DIR}/asg_access.log combined
</VirtualHost>
```

### 5. File Permissions
```bash
chmod 750 /var/www/asgstudios.online/
chmod 640 config.php
chmod 755 public/uploads/
chown -R www-data:www-data /var/www/asgstudios.online/public/uploads/
mkdir -p logs && chmod 750 logs && chown www-data:www-data logs/
```

### 6. SSL Certificate
```bash
certbot --apache -d asgstudios.online -d www.asgstudios.online
```

### 7. Enable Apache Modules
```bash
a2enmod rewrite headers expires deflate ssl
systemctl restart apache2
```

---

## 🔐 FIRST LOGIN

After deployment:
1. Go to `https://asgstudios.online/admin/`
2. Email: `admin@asgstudios.online`
3. **IMMEDIATELY change the password** in Users → Edit Profile
4. Add your real Razorpay/PayPal keys in Settings → Payment
5. Add your Google/GitHub OAuth credentials
6. Toggle features on/off via Admin → Feature Flags

---

## 💳 PAYMENT SETUP

### Razorpay (Cards + UPI)
1. Create account at razorpay.com
2. Go to Settings → API Keys → Generate Key
3. Add Key ID and Key Secret to your environment

### PayPal
1. Create app at developer.paypal.com
2. Get Client ID and Secret from Sandbox/Live app
3. For live transactions, set `PAYPAL_MODE=live` in config

### UPI
Add your UPI ID in Admin → Settings → Payment

### Bank Transfer
Add bank account details in Admin → Settings → Payment

---

## 📧 OAUTH SETUP

### Google OAuth
1. console.cloud.google.com → Create Project → APIs & Services → Credentials
2. Create OAuth 2.0 Client ID (Web application)
3. Authorized redirect URIs: `https://asgstudios.online/api/auth.php?action=google_callback`
4. Add Client ID and Secret to environment

### GitHub OAuth
1. github.com → Settings → Developer settings → OAuth Apps → New OAuth App
2. Homepage URL: `https://asgstudios.online`
3. Callback URL: `https://asgstudios.online/api/auth.php?action=github_callback`
4. Add Client ID and Secret to environment

---

## 🛡️ SECURITY FEATURES

- ✅ CSRF protection on all POST forms
- ✅ bcrypt password hashing (cost 12)
- ✅ Rate limiting (login: 5/15min, API: 60/min, contact: 3/hr)
- ✅ Session hardening (HTTPOnly, SameSite=Strict, Secure)
- ✅ XSS protection (input sanitization, CSP headers)
- ✅ SQL injection prevention (PDO prepared statements)
- ✅ File upload validation (MIME type, size limit, secure naming)
- ✅ Audit log for all sensitive actions
- ✅ Feature flags (disable features without code changes)
- ✅ HSTS, X-Frame-Options, Content-Security-Policy headers
- ✅ Blocked directory listing

---

## 🎨 DESIGN SYSTEM

| Token      | Value              | Usage                  |
|------------|---------------------|------------------------|
| `--gold`   | `#c9a84c`          | Primary accent, brand  |
| `--accent` | `#00e5ff`          | ASG Group, cyan        |
| `--bg`     | `#000000`          | Background             |
| `--chrome` | `#e0e0e0`          | Main text              |
| Font 1     | Orbitron           | Headings, brand        |
| Font 2     | Rajdhani           | Body text              |
| Font 3     | Share Tech Mono    | Labels, code, mono     |

---

## 📞 SUPPORT

**Founder:** Shreyash Ghosh  
**Email:** contact@asgstudios.online  
**Site:** https://asgstudios.online

---

*ASG™ · ASG Studios™ · ASG Group™ are trademarks of Shreyash Ghosh.*  
*All rights reserved. © 2024–2025 ASG Studios & ASG Group.*
