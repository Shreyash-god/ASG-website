<?php
/**
 * ASG — Bootstrap: Database, Auth, CSRF, Helpers
 */
defined('ASG_BOOT') or die('Direct access not permitted.');

// ── DATABASE (PDO) ─────────────────────────────────────────
class DB {
    private static ?PDO $pdo = null;

    public static function get(): PDO {
        if (!self::$pdo) {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT .
                   ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_FOUND_ROWS   => true,
            ]);
        }
        return self::$pdo;
    }

    public static function query(string $sql, array $params = []): PDOStatement {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function one(string $sql, array $params = []): ?array {
        return self::query($sql, $params)->fetch() ?: null;
    }

    public static function all(string $sql, array $params = []): array {
        return self::query($sql, $params)->fetchAll();
    }

    public static function insert(string $sql, array $params = []): int {
        self::query($sql, $params);
        return (int) self::get()->lastInsertId();
    }

    public static function count(string $sql, array $params = []): int {
        return (int) self::query($sql, $params)->fetchColumn();
    }
}

// ── SESSION ────────────────────────────────────────────────
class Session {
    public static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_name('ASG_SESSION');
            session_start();
        }
    }

    public static function regenerate(): void {
        session_regenerate_id(true);
    }

    public static function set(string $key, mixed $value): void {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool {
        return isset($_SESSION[$key]);
    }

    public static function delete(string $key): void {
        unset($_SESSION[$key]);
    }

    public static function destroy(): void {
        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/');
    }
}

// ── CSRF ───────────────────────────────────────────────────
class CSRF {
    public static function token(): string {
        if (!Session::has('csrf_token')) {
            Session::set('csrf_token', bin2hex(random_bytes(CSRF_TOKEN_LEN)));
        }
        return Session::get('csrf_token');
    }

    public static function field(): string {
        return '<input type="hidden" name="csrf_token" value="' . self::token() . '">';
    }

    public static function verify(): bool {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return hash_equals(Session::get('csrf_token', ''), $token);
    }

    public static function verifyOrFail(): void {
        if (!self::verify()) {
            http_response_code(403);
            die(json_encode(['error' => 'Invalid CSRF token']));
        }
    }
}

// ── AUTH ───────────────────────────────────────────────────
class Auth {
    private static ?array $user = null;

    public static function check(): bool {
        return self::user() !== null;
    }

    public static function user(): ?array {
        if (self::$user !== null) return self::$user;
        $uid = Session::get('user_id');
        if (!$uid) return null;
        $u = DB::one("SELECT * FROM users WHERE id=? AND is_active=1", [$uid]);
        self::$user = $u ?: null;
        return self::$user;
    }

    public static function id(): ?int {
        return self::user()['id'] ?? null;
    }

    public static function isAdmin(): bool {
        $role = self::user()['role'] ?? '';
        return in_array($role, ['admin', 'superadmin']);
    }

    public static function isSuperAdmin(): bool {
        return (self::user()['role'] ?? '') === 'superadmin';
    }

    public static function login(array $user): void {
        Session::regenerate();
        Session::set('user_id', $user['id']);
        Session::set('user_role', $user['role']);
        self::$user = $user;
        DB::query("UPDATE users SET last_login=NOW(), login_ip=? WHERE id=?",
                  [client_ip(), $user['id']]);
    }

    public static function logout(): void {
        Session::destroy();
        self::$user = null;
    }

    public static function requireLogin(): void {
        if (!self::check()) {
            redirect('/pages/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        }
    }

    public static function requireAdmin(): void {
        self::requireLogin();
        if (!self::isAdmin()) { http_response_code(403); die('Forbidden'); }
    }
}

// ── RATE LIMITER ───────────────────────────────────────────
class RateLimit {
    public static function check(string $key, int $max, int $window): bool {
        $ip    = client_ip();
        $skey  = "rl_{$key}_{$ip}";
        $now   = time();
        $data  = Session::get($skey, ['count' => 0, 'start' => $now]);

        if ($now - $data['start'] > $window) {
            $data = ['count' => 0, 'start' => $now];
        }
        $data['count']++;
        Session::set($skey, $data);
        return $data['count'] <= $max;
    }

    public static function loginCheck(): bool {
        return self::check('login', RATE_LOGIN_MAX, RATE_LOGIN_WIN);
    }
}

// ── RESPONSE HELPERS ───────────────────────────────────────
function json_ok(array $data = [], int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, ...$data]);
    exit;
}

function json_err(string $message, int $code = 400, array $extra = []): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $message, ...$extra]);
    exit;
}

function redirect(string $url, int $code = 302): never {
    http_response_code($code);
    header("Location: $url");
    exit;
}

// ── VALIDATION ─────────────────────────────────────────────
function sanitize(string $v): string {
    return htmlspecialchars(strip_tags(trim($v)), ENT_QUOTES, 'UTF-8');
}

function validate_email(string $e): bool {
    return (bool) filter_var($e, FILTER_VALIDATE_EMAIL);
}

function slugify(string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    return strtolower($text);
}

// ── UTILITY ────────────────────────────────────────────────
function client_ip(): string {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = trim(explode(',', $_SERVER[$h])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

function setting(string $key, string $default = ''): string {
    static $cache = [];
    if (!isset($cache[$key])) {
        $row = DB::one("SELECT `value` FROM site_settings WHERE `key`=?", [$key]);
        $cache[$key] = $row ? $row['value'] : $default;
    }
    return $cache[$key] ?? $default;
}

function feature(string $key): bool {
    static $cache = [];
    if (!isset($cache[$key])) {
        $row = DB::one("SELECT is_enabled FROM feature_flags WHERE flag_key=?", [$key]);
        $cache[$key] = $row ? (bool)$row['is_enabled'] : false;
    }
    return $cache[$key];
}

function audit(string $action, ?string $target = null, array $payload = []): void {
    $uid = Auth::id();
    DB::query(
        "INSERT INTO audit_log (user_id, action, target, ip_address, user_agent, payload) VALUES (?,?,?,?,?,?)",
        [$uid, $action, $target, client_ip(), $_SERVER['HTTP_USER_AGENT'] ?? '', json_encode($payload)]
    );
}

function upload_file(array $file, string $subdir = 'uploads'): array {
    $maxBytes = MAX_UPLOAD_MB * 1024 * 1024;
    if ($file['error'] !== UPLOAD_ERR_OK)  throw new Exception('Upload error: ' . $file['error']);
    if ($file['size'] > $maxBytes)         throw new Exception('File too large (max ' . MAX_UPLOAD_MB . 'MB)');

    $allowed_mime = ['image/jpeg','image/png','image/gif','image/webp',
                     'application/pdf','text/plain','video/mp4'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed_mime)) throw new Exception('File type not allowed');

    $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
    $name = bin2hex(random_bytes(16)) . '.' . strtolower($ext);
    $dir  = PUBLIC_PATH . '/' . $subdir;
    if (!is_dir($dir)) mkdir($dir, 0750, true);
    $path = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $path)) throw new Exception('Could not save file');

    return ['filename' => $name, 'path' => $path, 'url' => ASG_SITE . "/$subdir/$name", 'mime' => $mime];
}

// ── BOOT ───────────────────────────────────────────────────
Session::start();
asg_security_headers();
