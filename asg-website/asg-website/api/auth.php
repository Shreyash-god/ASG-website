<?php
/**
 * ASG — Auth API
 * Handles: register, login, logout, Google OAuth, GitHub OAuth
 */
define('ASG_BOOT', 1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

$action = $_GET['action'] ?? '';

match($action) {
    'register'        => handleRegister(),
    'login'           => handleLogin(),
    'logout'          => handleLogout(),
    'google'          => handleGoogleRedirect(),
    'google_callback' => handleGoogleCallback(),
    'github'          => handleGithubRedirect(),
    'github_callback' => handleGithubCallback(),
    'me'              => handleMe(),
    default           => json_err('Unknown action', 404)
};

// ── REGISTER ───────────────────────────────────────────────
function handleRegister(): never {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Method not allowed', 405);
    CSRF::verifyOrFail();

    if (!feature('registration_open')) json_err('Registration is currently closed');

    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $name  = sanitize($_POST['name'] ?? '');

    if (!$email || !$pass || !$name)      json_err('All fields are required');
    if (!validate_email($email))           json_err('Invalid email address');
    if (strlen($pass) < 8)                json_err('Password must be at least 8 characters');
    if (!preg_match('/[A-Z]/', $pass))     json_err('Password must contain an uppercase letter');
    if (!preg_match('/[0-9]/', $pass))     json_err('Password must contain a number');

    $exists = DB::one("SELECT id FROM users WHERE email=?", [$email]);
    if ($exists) json_err('Email already registered');

    $hash  = password_hash($pass, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    $token = bin2hex(random_bytes(32));
    $id    = DB::insert(
        "INSERT INTO users (email, password_hash, display_name, verify_token) VALUES (?,?,?,?)",
        [$email, $hash, $name, $token]
    );

    audit('user.register', "user:$id");
    json_ok(['message' => 'Account created. Please verify your email.']);
}

// ── LOGIN ──────────────────────────────────────────────────
function handleLogin(): never {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Method not allowed', 405);
    CSRF::verifyOrFail();

    if (!RateLimit::loginCheck()) {
        json_err('Too many login attempts. Try again in 15 minutes.', 429);
    }

    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if (!$email || !$pass) json_err('Email and password required');

    $user = DB::one("SELECT * FROM users WHERE email=? AND auth_provider='email'", [$email]);

    if (!$user || !password_verify($pass, $user['password_hash'])) {
        json_err('Invalid email or password');
    }
    if (!$user['is_active']) json_err('Account suspended');

    Auth::login($user);
    audit('user.login', "user:{$user['id']}");

    $redirect = match($user['role']) {
        'superadmin', 'admin' => '/admin/',
        default               => '/'
    };

    json_ok([
        'message'  => 'Login successful',
        'user'     => ['name' => $user['display_name'], 'role' => $user['role']],
        'redirect' => $redirect
    ]);
}

// ── LOGOUT ─────────────────────────────────────────────────
function handleLogout(): never {
    audit('user.logout', Auth::id() ? "user:" . Auth::id() : null);
    Auth::logout();
    json_ok(['message' => 'Logged out', 'redirect' => '/']);
}

// ── GOOGLE OAUTH ───────────────────────────────────────────
function handleGoogleRedirect(): never {
    if (!feature('google_auth')) json_err('Google login disabled');
    $state = bin2hex(random_bytes(16));
    Session::set('oauth_state', $state);
    $params = http_build_query([
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => GOOGLE_REDIRECT,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
    ]);
    redirect("https://accounts.google.com/o/oauth2/v2/auth?$params");
}

function handleGoogleCallback(): never {
    if (!feature('google_auth')) json_err('Google login disabled');
    $state = $_GET['state'] ?? '';
    if (!hash_equals(Session::get('oauth_state', ''), $state)) json_err('Invalid state', 403);
    Session::delete('oauth_state');

    $code = $_GET['code'] ?? '';
    if (!$code) redirect('/pages/login.php?error=oauth_failed');

    // Exchange code for token
    $resp = http_post('https://oauth2.googleapis.com/token', [
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => GOOGLE_REDIRECT,
        'grant_type'    => 'authorization_code',
    ]);
    $token = json_decode($resp, true);
    if (empty($token['access_token'])) redirect('/pages/login.php?error=oauth_failed');

    // Get user info
    $info = json_decode(file_get_contents(
        'https://www.googleapis.com/oauth2/v3/userinfo',
        false,
        stream_context_create(['http' => ['header' => "Authorization: Bearer {$token['access_token']}"]])
    ), true);

    $user = oauthFindOrCreate('google', $info['sub'], $info['email'], $info['name'], $info['picture'] ?? null);
    Auth::login($user);
    audit('user.oauth_login', "provider:google");
    redirect('/');
}

// ── GITHUB OAUTH ───────────────────────────────────────────
function handleGithubRedirect(): never {
    if (!feature('github_auth')) json_err('GitHub login disabled');
    $state = bin2hex(random_bytes(16));
    Session::set('oauth_state', $state);
    $params = http_build_query([
        'client_id'    => GITHUB_CLIENT_ID,
        'redirect_uri' => GITHUB_REDIRECT,
        'scope'        => 'user:email',
        'state'        => $state,
    ]);
    redirect("https://github.com/login/oauth/authorize?$params");
}

function handleGithubCallback(): never {
    if (!feature('github_auth')) json_err('GitHub login disabled');
    $state = $_GET['state'] ?? '';
    if (!hash_equals(Session::get('oauth_state', ''), $state)) json_err('Invalid state', 403);
    Session::delete('oauth_state');

    $code = $_GET['code'] ?? '';
    if (!$code) redirect('/pages/login.php?error=oauth_failed');

    $resp  = http_post('https://github.com/login/oauth/access_token', [
        'client_id'     => GITHUB_CLIENT_ID,
        'client_secret' => GITHUB_CLIENT_SECRET,
        'code'          => $code,
        'redirect_uri'  => GITHUB_REDIRECT,
    ], ['Accept: application/json']);
    $token = json_decode($resp, true);
    if (empty($token['access_token'])) redirect('/pages/login.php?error=oauth_failed');

    $ctx  = stream_context_create(['http' => [
        'header' => "Authorization: Bearer {$token['access_token']}\r\nUser-Agent: ASGStudios\r\n"
    ]]);
    $info = json_decode(file_get_contents('https://api.github.com/user', false, $ctx), true);
    if (empty($info['email'])) {
        $emails = json_decode(file_get_contents('https://api.github.com/user/emails', false, $ctx), true);
        foreach ($emails as $e) { if ($e['primary']) { $info['email'] = $e['email']; break; } }
    }

    $user = oauthFindOrCreate('github', (string)$info['id'], $info['email'], $info['name'] ?: $info['login'], $info['avatar_url'] ?? null);
    Auth::login($user);
    audit('user.oauth_login', "provider:github");
    redirect('/');
}

// ── ME (current user info) ─────────────────────────────────
function handleMe(): never {
    if (!Auth::check()) json_err('Not authenticated', 401);
    $u = Auth::user();
    json_ok(['user' => [
        'id'    => $u['id'],
        'name'  => $u['display_name'],
        'email' => $u['email'],
        'role'  => $u['role'],
        'avatar'=> $u['avatar_url'],
    ]]);
}

// ── HELPERS ────────────────────────────────────────────────
function oauthFindOrCreate(string $provider, string $provId, string $email, string $name, ?string $avatar): array {
    if (!feature('registration_open') && !DB::one("SELECT id FROM users WHERE email=?", [$email])) {
        redirect('/pages/login.php?error=registration_closed');
    }
    $user = DB::one("SELECT * FROM users WHERE auth_provider=? AND provider_id=?", [$provider, $provId])
         ?: DB::one("SELECT * FROM users WHERE email=?", [$email]);

    if ($user) {
        DB::query("UPDATE users SET provider_id=?, avatar_url=COALESCE(?,avatar_url), auth_provider=?, is_verified=1 WHERE id=?",
                  [$provId, $avatar, $provider, $user['id']]);
        return DB::one("SELECT * FROM users WHERE id=?", [$user['id']]);
    }

    $id = DB::insert(
        "INSERT INTO users (email, display_name, auth_provider, provider_id, avatar_url, is_verified) VALUES (?,?,?,?,?,1)",
        [$email, $name, $provider, $provId, $avatar]
    );
    return DB::one("SELECT * FROM users WHERE id=?", [$id]);
}

function http_post(string $url, array $data, array $headers = []): string {
    $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => implode("\r\n", $headers),
        'content' => http_build_query($data),
        'timeout' => 15,
    ]]);
    return file_get_contents($url, false, $ctx) ?: '';
}
