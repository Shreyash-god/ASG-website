<?php
/**
 * ASG Studios & ASG Group — Core Configuration
 * Keep this file OUTSIDE public web root in production!
 */

defined('ASG_BOOT') or die('Direct access not permitted.');

// ── ENVIRONMENT ────────────────────────────────────────────
define('ASG_ENV',     getenv('ASG_ENV')     ?: 'production'); // development | production
define('ASG_VERSION', '1.0.0');
define('ASG_SITE',    'https://asgstudios.online');
define('ASG_NAME',    'ASG Studios & ASG Group');

// ── DATABASE ───────────────────────────────────────────────
define('DB_HOST',     getenv('DB_HOST')     ?: 'localhost');
define('DB_NAME',     getenv('DB_NAME')     ?: 'asg_website');
define('DB_USER',     getenv('DB_USER')     ?: 'asg_dbuser');
define('DB_PASS',     getenv('DB_PASS')     ?: '');
define('DB_PORT',     getenv('DB_PORT')     ?: 3306);
define('DB_CHARSET',  'utf8mb4');

// ── SECURITY ───────────────────────────────────────────────
define('APP_KEY',        getenv('APP_KEY')        ?: 'CHANGE_THIS_32_CHAR_SECRET_KEY!!');
define('JWT_SECRET',     getenv('JWT_SECRET')     ?: 'CHANGE_THIS_JWT_SECRET_KEY_LONG!!');
define('SESSION_SECURE', ASG_ENV === 'production');
define('CSRF_TOKEN_LEN', 48);
define('BCRYPT_COST',    12);

// ── OAUTH ──────────────────────────────────────────────────
define('GOOGLE_CLIENT_ID',     getenv('GOOGLE_CLIENT_ID')     ?: '');
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '');
define('GOOGLE_REDIRECT',      ASG_SITE . '/api/auth/google/callback');

define('GITHUB_CLIENT_ID',     getenv('GITHUB_CLIENT_ID')     ?: '');
define('GITHUB_CLIENT_SECRET', getenv('GITHUB_CLIENT_SECRET') ?: '');
define('GITHUB_REDIRECT',      ASG_SITE . '/api/auth/github/callback');

// ── PAYMENT ────────────────────────────────────────────────
define('PAYPAL_MODE',        'sandbox'); // sandbox | live
define('PAYPAL_CLIENT_ID',   getenv('PAYPAL_CLIENT_ID')   ?: '');
define('PAYPAL_SECRET',      getenv('PAYPAL_SECRET')      ?: '');
define('RAZORPAY_KEY_ID',    getenv('RAZORPAY_KEY_ID')    ?: '');
define('RAZORPAY_KEY_SECRET',getenv('RAZORPAY_KEY_SECRET') ?: '');

// ── EMAIL ──────────────────────────────────────────────────
define('MAIL_HOST',     getenv('MAIL_HOST')     ?: 'smtp.gmail.com');
define('MAIL_PORT',     getenv('MAIL_PORT')     ?: 587);
define('MAIL_USER',     getenv('MAIL_USER')     ?: '');
define('MAIL_PASS',     getenv('MAIL_PASS')     ?: '');
define('MAIL_FROM',     getenv('MAIL_FROM')     ?: 'noreply@asgstudios.online');
define('MAIL_NAME',     'ASG Studios');

// ── PATHS ──────────────────────────────────────────────────
define('BASE_PATH',    __DIR__);
define('PUBLIC_PATH',  BASE_PATH . '/public');
define('UPLOAD_PATH',  PUBLIC_PATH . '/uploads');
define('UPLOAD_URL',   ASG_SITE . '/uploads');
define('MAX_UPLOAD_MB', 20);

// ── RATE LIMITING ──────────────────────────────────────────
define('RATE_LOGIN_MAX',   5);   // attempts per window
define('RATE_LOGIN_WIN',   900); // seconds (15 min)
define('RATE_API_MAX',     60);  // requests per window
define('RATE_API_WIN',     60);  // seconds

// ── SESSION ────────────────────────────────────────────────
ini_set('session.cookie_httponly',  '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');
ini_set('session.gc_maxlifetime',  '86400');
if (SESSION_SECURE) {
    ini_set('session.cookie_secure', '1');
}

// ── ERROR HANDLING ─────────────────────────────────────────
if (ASG_ENV === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', BASE_PATH . '/logs/error.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// ── SECURITY HEADERS (called from bootstrap) ───────────────
function asg_security_headers(): void {
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
    header("Content-Security-Policy: default-src 'self'; " .
           "script-src 'self' 'unsafe-inline' https://www.paypal.com https://js.stripe.com https://accounts.google.com https://checkout.razorpay.com; " .
           "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
           "font-src 'self' https://fonts.gstatic.com; " .
           "img-src 'self' data: https:; " .
           "frame-src https://www.paypal.com https://js.stripe.com https://accounts.google.com;");
}
