<?php
/**
 * ASG — Admin Control Center
 */
define('ASG_BOOT', 1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';

Auth::requireAdmin();
$user = Auth::user();
$page = sanitize($_GET['page'] ?? 'dashboard');

// Handle feature toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_feature'])) {
    CSRF::verifyOrFail();
    $key = sanitize($_POST['flag_key'] ?? '');
    $val = isset($_POST['enabled']) ? 1 : 0;
    DB::query("UPDATE feature_flags SET is_enabled=? WHERE flag_key=?", [$val, $key]);
    audit('admin.feature_toggle', $key, ['value' => $val]);
    header("Location: /admin/?page=features&saved=1");
    exit;
}

// Handle settings save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    CSRF::verifyOrFail();
    foreach ($_POST as $k => $v) {
        if (in_array($k, ['save_settings','csrf_token'])) continue;
        $k = sanitize($k);
        DB::query("UPDATE site_settings SET `value`=?, updated_by=? WHERE `key`=?",
                  [sanitize((string)$v), Auth::id(), $k]);
    }
    audit('admin.settings_saved');
    header("Location: /admin/?page=settings&saved=1");
    exit;
}

// Stats for dashboard
$stats = [];
if ($page === 'dashboard') {
    $stats = [
        'users'     => DB::count("SELECT COUNT(*) FROM users WHERE role='user'"),
        'posts'     => DB::count("SELECT COUNT(*) FROM posts WHERE status='published'"),
        'orders'    => DB::count("SELECT COUNT(*) FROM orders WHERE status='paid'"),
        'donations' => DB::count("SELECT COUNT(*) FROM donations WHERE status='completed'"),
        'revenue'   => DB::one("SELECT COALESCE(SUM(total),0) AS t FROM orders WHERE status='paid'")['t'] ?? 0,
        'donated'   => DB::one("SELECT COALESCE(SUM(amount),0) AS t FROM donations WHERE status='completed'")['t'] ?? 0,
        'recent_users'  => DB::all("SELECT id, display_name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 8"),
        'recent_orders' => DB::all("SELECT o.id, o.total, o.status, o.payment_method, o.created_at, u.display_name FROM orders o LEFT JOIN users u ON u.id=o.user_id ORDER BY o.created_at DESC LIMIT 8"),
    ];
}
$flags    = DB::all("SELECT * FROM feature_flags ORDER BY flag_key");
$settings = DB::all("SELECT * FROM site_settings ORDER BY group_name, `key`");
$posts    = $page === 'posts'    ? DB::all("SELECT p.*, u.display_name AS author FROM posts p JOIN users u ON u.id=p.author_id ORDER BY p.created_at DESC LIMIT 50") : [];
$products = $page === 'products' ? DB::all("SELECT * FROM products ORDER BY created_at DESC LIMIT 50") : [];
$users    = $page === 'users'    ? DB::all("SELECT id, display_name, email, role, is_active, auth_provider, last_login, created_at FROM users ORDER BY created_at DESC LIMIT 50") : [];
$orders   = $page === 'orders'   ? DB::all("SELECT o.*, u.display_name FROM orders o LEFT JOIN users u ON u.id=o.user_id ORDER BY o.created_at DESC LIMIT 50") : [];
$donations= $page === 'donations'? DB::all("SELECT * FROM donations ORDER BY created_at DESC LIMIT 50") : [];
$messages = $page === 'messages' ? DB::all("SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 50") : [];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ASG Admin — <?= ucfirst($page) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<style>
:root{--gold:#c9a84c;--bg:#050508;--bg2:#0a0a10;--bg3:#0d0d18;--accent:#00e5ff;--red:#ff4757;--green:#2ed573;--text:#e0e0e0;--text2:#7a7a90;--border:rgba(201,168,76,0.18);--font-d:'Orbitron',monospace;--font-b:'Rajdhani',sans-serif;--font-m:'Share Tech Mono',monospace}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:var(--font-b);font-size:15px;min-height:100vh;display:flex}
/* SIDEBAR */
.sidebar{width:240px;min-height:100vh;background:var(--bg2);border-right:1px solid var(--border);padding:0;display:flex;flex-direction:column;flex-shrink:0}
.sidebar-logo{padding:24px 20px;border-bottom:1px solid var(--border)}
.sidebar-logo span{font-family:var(--font-d);font-size:16px;font-weight:900;color:var(--gold);letter-spacing:3px}
.sidebar-logo small{display:block;font-family:var(--font-m);font-size:9px;color:var(--text2);letter-spacing:3px;margin-top:4px}
.sidebar nav{flex:1;padding:16px 0}
.nav-section{padding:8px 20px 4px;font-family:var(--font-m);font-size:9px;letter-spacing:3px;color:var(--text2);text-transform:uppercase}
.sidebar nav a{display:flex;align-items:center;gap:10px;padding:10px 20px;color:var(--text2);text-decoration:none;font-size:13px;font-weight:600;letter-spacing:1px;transition:all 0.2s;border-left:2px solid transparent}
.sidebar nav a:hover,.sidebar nav a.active{color:var(--gold);background:rgba(201,168,76,0.06);border-left-color:var(--gold)}
.sidebar nav a .icon{font-size:16px;width:20px;text-align:center}
.sidebar-user{padding:16px 20px;border-top:1px solid var(--border)}
.sidebar-user .name{font-size:13px;font-weight:600;color:var(--text)}
.sidebar-user .role{font-family:var(--font-m);font-size:9px;color:var(--gold);letter-spacing:2px}
/* MAIN */
.main{flex:1;min-height:100vh;overflow-x:hidden}
.topbar{background:var(--bg2);border-bottom:1px solid var(--border);padding:16px 32px;display:flex;align-items:center;justify-content:space-between}
.topbar h1{font-family:var(--font-d);font-size:18px;font-weight:700;letter-spacing:3px;color:var(--text)}
.topbar .actions{display:flex;gap:12px;align-items:center}
.content{padding:32px}
/* CARDS */
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:32px}
.stat-card{background:var(--bg2);border:1px solid var(--border);padding:24px;position:relative;overflow:hidden}
.stat-card::before{content:'';position:absolute;top:0;left:0;width:2px;height:100%;background:var(--gold)}
.stat-card .label{font-family:var(--font-m);font-size:10px;letter-spacing:3px;color:var(--text2);text-transform:uppercase;margin-bottom:8px}
.stat-card .value{font-family:var(--font-d);font-size:28px;font-weight:700;color:var(--gold)}
.stat-card .sub{font-size:12px;color:var(--text2);margin-top:4px}
/* TABLE */
.table-wrap{background:var(--bg2);border:1px solid var(--border);overflow-x:auto;margin-bottom:24px}
table{width:100%;border-collapse:collapse}
th{font-family:var(--font-m);font-size:10px;letter-spacing:3px;color:var(--text2);text-transform:uppercase;padding:14px 20px;text-align:left;border-bottom:1px solid var(--border);white-space:nowrap}
td{padding:12px 20px;font-size:13px;border-bottom:1px solid rgba(201,168,76,0.06);vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:rgba(201,168,76,0.03)}
/* BADGES */
.badge{display:inline-block;padding:2px 8px;font-family:var(--font-m);font-size:9px;letter-spacing:2px;border-radius:2px;text-transform:uppercase}
.badge-green{background:rgba(46,213,115,0.15);color:var(--green)}
.badge-red{background:rgba(255,71,87,0.15);color:var(--red)}
.badge-gold{background:rgba(201,168,76,0.15);color:var(--gold)}
.badge-blue{background:rgba(0,229,255,0.12);color:var(--accent)}
/* FORMS */
.form-section{background:var(--bg2);border:1px solid var(--border);padding:28px;margin-bottom:24px}
.form-section h3{font-family:var(--font-d);font-size:13px;font-weight:700;letter-spacing:3px;color:var(--gold);margin-bottom:20px;text-transform:uppercase}
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px}
label{display:block;font-family:var(--font-m);font-size:10px;letter-spacing:2px;color:var(--text2);margin-bottom:6px;text-transform:uppercase}
input[type=text],input[type=email],input[type=password],input[type=number],select,textarea{
  width:100%;background:var(--bg3);border:1px solid var(--border);color:var(--text);
  padding:10px 14px;font-family:var(--font-b);font-size:14px;
  outline:none;transition:border-color 0.2s}
input:focus,select:focus,textarea:focus{border-color:var(--gold)}
textarea{min-height:100px;resize:vertical}
/* BUTTONS */
.btn{display:inline-flex;align-items:center;gap:6px;font-family:var(--font-d);font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;padding:10px 20px;border:none;cursor:pointer;text-decoration:none;transition:all 0.2s}
.btn-gold{background:linear-gradient(135deg,var(--gold) 0%,#8b6914 100%);color:#000}
.btn-gold:hover{box-shadow:0 0 20px rgba(201,168,76,0.4)}
.btn-outline{background:transparent;color:var(--text2);border:1px solid var(--border)}
.btn-outline:hover{border-color:var(--gold);color:var(--gold)}
.btn-red{background:rgba(255,71,87,0.15);color:var(--red);border:1px solid rgba(255,71,87,0.3)}
.btn-sm{padding:6px 14px;font-size:9px}
/* TOGGLE SWITCH */
.toggle-wrap{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border)}
.toggle-wrap:last-child{border-bottom:none}
.toggle-info .flag-label{font-size:14px;font-weight:600;color:var(--text);margin-bottom:2px}
.toggle-info .flag-desc{font-size:12px;color:var(--text2)}
.toggle{position:relative;display:inline-block;width:48px;height:24px}
.toggle input{opacity:0;width:0;height:0}
.toggle-slider{position:absolute;inset:0;background:#1a1a2a;cursor:pointer;transition:.3s;border:1px solid var(--border)}
.toggle-slider::before{content:'';position:absolute;height:16px;width:16px;left:3px;bottom:3px;background:var(--text2);transition:.3s}
input:checked+.toggle-slider{background:rgba(201,168,76,0.2);border-color:var(--gold)}
input:checked+.toggle-slider::before{transform:translateX(24px);background:var(--gold)}
/* ALERT */
.alert{padding:12px 20px;margin-bottom:20px;border-left:3px solid var(--green);background:rgba(46,213,115,0.08);font-size:13px;color:var(--green)}
/* SECTION CARD */
.section-card{background:var(--bg2);border:1px solid var(--border);margin-bottom:24px}
.section-card .card-header{padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.section-card .card-header h3{font-family:var(--font-d);font-size:13px;letter-spacing:3px;color:var(--gold)}
.section-card .card-body{padding:0}
</style>
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-logo">
    <span>ASG ADMIN</span>
    <small>CONTROL CENTER</small>
  </div>
  <nav>
    <div class="nav-section">Overview</div>
    <a href="?page=dashboard" class="<?= $page==='dashboard'?'active':'' ?>"><span class="icon">◈</span> Dashboard</a>

    <div class="nav-section">Content</div>
    <a href="?page=posts"    class="<?= $page==='posts'?'active':'' ?>"><span class="icon">◧</span> Posts & Docs</a>
    <a href="?page=media"    class="<?= $page==='media'?'active':'' ?>"><span class="icon">◫</span> Media Library</a>
    <a href="?page=messages" class="<?= $page==='messages'?'active':'' ?>"><span class="icon">◎</span> Messages</a>

    <div class="nav-section">Commerce</div>
    <a href="?page=products" class="<?= $page==='products'?'active':'' ?>"><span class="icon">◈</span> Products</a>
    <a href="?page=orders"   class="<?= $page==='orders'?'active':'' ?>"><span class="icon">◧</span> Orders</a>
    <a href="?page=donations"class="<?= $page==='donations'?'active':'' ?>"><span class="icon">◎</span> Donations</a>

    <div class="nav-section">System</div>
    <a href="?page=users"    class="<?= $page==='users'?'active':'' ?>"><span class="icon">◈</span> Users</a>
    <a href="?page=features" class="<?= $page==='features'?'active':'' ?>"><span class="icon">◧</span> Feature Flags</a>
    <a href="?page=settings" class="<?= $page==='settings'?'active':'' ?>"><span class="icon">◎</span> Settings</a>
    <a href="?page=audit"    class="<?= $page==='audit'?'active':'' ?>"><span class="icon">◫</span> Audit Log</a>
  </nav>
  <div class="sidebar-user">
    <div class="name"><?= htmlspecialchars($user['display_name']) ?></div>
    <div class="role"><?= strtoupper($user['role']) ?></div>
    <a href="/api/auth.php?action=logout" class="btn btn-outline btn-sm" style="margin-top:10px">Logout</a>
  </div>
</aside>

<main class="main">
  <div class="topbar">
    <h1><?= strtoupper($page) ?></h1>
    <div class="actions">
      <?php if (isset($_GET['saved'])): ?>
        <span style="color:var(--green);font-family:var(--font-m);font-size:11px">✓ SAVED</span>
      <?php endif; ?>
      <a href="/" class="btn btn-outline btn-sm">← View Site</a>
    </div>
  </div>

  <div class="content">

  <?php if ($page === 'dashboard'): ?>
    <div class="stat-grid">
      <div class="stat-card"><div class="label">Total Users</div><div class="value"><?= number_format($stats['users']) ?></div></div>
      <div class="stat-card"><div class="label">Published Posts</div><div class="value"><?= number_format($stats['posts']) ?></div></div>
      <div class="stat-card"><div class="label">Paid Orders</div><div class="value"><?= number_format($stats['orders']) ?></div></div>
      <div class="stat-card"><div class="label">Revenue</div><div class="value">₹<?= number_format($stats['revenue'], 2) ?></div></div>
      <div class="stat-card"><div class="label">Donations</div><div class="value"><?= number_format($stats['donations']) ?></div></div>
      <div class="stat-card"><div class="label">Total Donated</div><div class="value">₹<?= number_format($stats['donated'], 2) ?></div></div>
    </div>

    <div class="section-card">
      <div class="card-header"><h3>Recent Users</h3></div>
      <div class="card-body">
        <div class="table-wrap">
          <table>
            <tr><th>Name</th><th>Email</th><th>Role</th><th>Joined</th></tr>
            <?php foreach ($stats['recent_users'] as $u): ?>
            <tr>
              <td><?= htmlspecialchars($u['display_name']) ?></td>
              <td style="color:var(--text2)"><?= htmlspecialchars($u['email']) ?></td>
              <td><span class="badge badge-<?= $u['role']==='superadmin'?'gold':($u['role']==='admin'?'blue':'green') ?>"><?= $u['role'] ?></span></td>
              <td style="color:var(--text2);font-family:var(--font-m);font-size:11px"><?= substr($u['created_at'],0,10) ?></td>
            </tr>
            <?php endforeach; ?>
          </table>
        </div>
      </div>
    </div>

    <div class="section-card">
      <div class="card-header"><h3>Recent Orders</h3></div>
      <div class="card-body">
        <div class="table-wrap">
          <table>
            <tr><th>#</th><th>Customer</th><th>Total</th><th>Method</th><th>Status</th><th>Date</th></tr>
            <?php foreach ($stats['recent_orders'] as $o): ?>
            <tr>
              <td style="font-family:var(--font-m);font-size:11px;color:var(--text2)">#<?= $o['id'] ?></td>
              <td><?= htmlspecialchars($o['display_name'] ?? $o['guest_email'] ?? 'Guest') ?></td>
              <td style="color:var(--gold)">₹<?= number_format($o['total'],2) ?></td>
              <td style="font-size:12px;color:var(--text2)"><?= $o['payment_method'] ?></td>
              <td><span class="badge badge-<?= $o['status']==='paid'||$o['status']==='completed'?'green':($o['status']==='pending'?'gold':'red') ?>"><?= $o['status'] ?></span></td>
              <td style="color:var(--text2);font-family:var(--font-m);font-size:11px"><?= substr($o['created_at'],0,10) ?></td>
            </tr>
            <?php endforeach; ?>
          </table>
        </div>
      </div>
    </div>

  <?php elseif ($page === 'features'): ?>
    <div class="section-card">
      <div class="card-header"><h3>Feature Flags</h3><span style="font-family:var(--font-m);font-size:10px;color:var(--text2)">Toggle features on/off without code changes</span></div>
      <div class="card-body">
        <?php foreach ($flags as $f): ?>
        <form method="POST" style="display:contents">
          <?= CSRF::field() ?>
          <input type="hidden" name="toggle_feature" value="1">
          <input type="hidden" name="flag_key" value="<?= htmlspecialchars($f['flag_key']) ?>">
          <div class="toggle-wrap">
            <div class="toggle-info">
              <div class="flag-label"><?= htmlspecialchars($f['label']) ?></div>
              <div class="flag-desc"><?= htmlspecialchars($f['description'] ?? '') ?></div>
            </div>
            <label class="toggle">
              <input type="checkbox" name="enabled" <?= $f['is_enabled'] ? 'checked' : '' ?> onchange="this.form.submit()">
              <span class="toggle-slider"></span>
            </label>
          </div>
        </form>
        <?php endforeach; ?>
      </div>
    </div>

  <?php elseif ($page === 'settings'): ?>
    <form method="POST">
      <?= CSRF::field() ?>
      <input type="hidden" name="save_settings" value="1">
      <?php
      $groups = [];
      foreach ($settings as $s) $groups[$s['group_name']][] = $s;
      foreach ($groups as $grp => $items): ?>
      <div class="form-section">
        <h3><?= ucfirst($grp) ?> Settings</h3>
        <div class="form-grid">
          <?php foreach ($items as $s): ?>
          <div>
            <label><?= htmlspecialchars($s['label']) ?></label>
            <input type="text" name="<?= htmlspecialchars($s['key']) ?>" value="<?= htmlspecialchars($s['value'] ?? '') ?>">
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <button type="submit" class="btn btn-gold">Save All Settings</button>
    </form>

  <?php elseif ($page === 'posts'): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <span style="color:var(--text2);font-size:13px"><?= count($posts) ?> posts found</span>
      <a href="/admin/post-editor.php" class="btn btn-gold">+ New Post</a>
    </div>
    <div class="table-wrap">
      <table>
        <tr><th>Title</th><th>Type</th><th>Status</th><th>Author</th><th>Views</th><th>Date</th><th>Actions</th></tr>
        <?php foreach ($posts as $p): ?>
        <tr>
          <td style="max-width:300px"><a href="/blog/<?= $p['slug'] ?>" style="color:var(--text);text-decoration:none" target="_blank"><?= htmlspecialchars($p['title']) ?></a></td>
          <td><span class="badge badge-blue"><?= $p['type'] ?></span></td>
          <td><span class="badge badge-<?= $p['status']==='published'?'green':'gold' ?>"><?= $p['status'] ?></span></td>
          <td style="color:var(--text2)"><?= htmlspecialchars($p['author']) ?></td>
          <td style="color:var(--text2);font-family:var(--font-m);font-size:12px"><?= number_format($p['views']) ?></td>
          <td style="color:var(--text2);font-family:var(--font-m);font-size:11px"><?= substr($p['created_at'],0,10) ?></td>
          <td>
            <a href="/admin/post-editor.php?id=<?= $p['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>

  <?php elseif ($page === 'users'): ?>
    <div class="table-wrap">
      <table>
        <tr><th>Name</th><th>Email</th><th>Role</th><th>Provider</th><th>Status</th><th>Last Login</th><th>Joined</th></tr>
        <?php foreach ($users as $u): ?>
        <tr>
          <td><?= htmlspecialchars($u['display_name']) ?></td>
          <td style="color:var(--text2);font-size:12px"><?= htmlspecialchars($u['email']) ?></td>
          <td><span class="badge badge-<?= $u['role']==='superadmin'?'gold':($u['role']==='admin'?'blue':'green') ?>"><?= $u['role'] ?></span></td>
          <td style="color:var(--text2);font-size:12px"><?= $u['auth_provider'] ?></td>
          <td><span class="badge badge-<?= $u['is_active']?'green':'red' ?>"><?= $u['is_active']?'Active':'Banned' ?></span></td>
          <td style="color:var(--text2);font-family:var(--font-m);font-size:11px"><?= $u['last_login'] ? substr($u['last_login'],0,10) : '—' ?></td>
          <td style="color:var(--text2);font-family:var(--font-m);font-size:11px"><?= substr($u['created_at'],0,10) ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>

  <?php elseif ($page === 'orders'): ?>
    <div class="table-wrap">
      <table>
        <tr><th>#ID</th><th>Customer</th><th>Total</th><th>Method</th><th>Status</th><th>Date</th></tr>
        <?php foreach ($orders as $o): ?>
        <tr>
          <td style="font-family:var(--font-m);font-size:11px;color:var(--gold)">#<?= $o['id'] ?></td>
          <td><?= htmlspecialchars($o['display_name'] ?? $o['guest_email'] ?? 'Guest') ?></td>
          <td style="color:var(--gold)">₹<?= number_format($o['total'],2) ?></td>
          <td style="font-size:12px;color:var(--text2)"><?= $o['payment_method'] ?></td>
          <td><span class="badge badge-<?= $o['status']==='paid'||$o['status']==='completed'?'green':($o['status']==='pending'?'gold':'red') ?>"><?= $o['status'] ?></span></td>
          <td style="color:var(--text2);font-family:var(--font-m);font-size:11px"><?= substr($o['created_at'],0,10) ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>

  <?php elseif ($page === 'donations'): ?>
    <div class="table-wrap">
      <table>
        <tr><th>Donor</th><th>Amount</th><th>Method</th><th>Message</th><th>Status</th><th>Date</th></tr>
        <?php foreach ($donations as $d): ?>
        <tr>
          <td><?= $d['is_anonymous'] ? '<em style="color:var(--text2)">Anonymous</em>' : htmlspecialchars($d['donor_name'] ?? '—') ?></td>
          <td style="color:var(--gold);font-weight:700">₹<?= number_format($d['amount'],2) ?></td>
          <td style="font-size:12px;color:var(--text2)"><?= $d['method'] ?></td>
          <td style="max-width:200px;font-size:12px;color:var(--text2)"><?= htmlspecialchars(substr($d['message'] ?? '—', 0, 60)) ?></td>
          <td><span class="badge badge-<?= $d['status']==='completed'?'green':($d['status']==='pending'?'gold':'red') ?>"><?= $d['status'] ?></span></td>
          <td style="color:var(--text2);font-family:var(--font-m);font-size:11px"><?= substr($d['created_at'],0,10) ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>

  <?php elseif ($page === 'messages'): ?>
    <div class="table-wrap">
      <table>
        <tr><th>Name</th><th>Email</th><th>Subject</th><th>Message</th><th>Date</th></tr>
        <?php foreach ($messages as $m): ?>
        <tr style="<?= !$m['is_read']?'background:rgba(201,168,76,0.04)':'' ?>">
          <td style="font-weight:<?= !$m['is_read']?'700':'400' ?>"><?= htmlspecialchars($m['name']) ?></td>
          <td style="color:var(--text2);font-size:12px"><?= htmlspecialchars($m['email']) ?></td>
          <td><?= htmlspecialchars($m['subject'] ?? '—') ?></td>
          <td style="max-width:250px;font-size:12px;color:var(--text2)"><?= htmlspecialchars(substr($m['message'],0,80)) ?>...</td>
          <td style="color:var(--text2);font-family:var(--font-m);font-size:11px"><?= substr($m['created_at'],0,10) ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>

  <?php elseif ($page === 'audit'): ?>
    <?php
    $logs = DB::all("SELECT a.*, u.display_name FROM audit_log a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.created_at DESC LIMIT 100");
    ?>
    <div class="table-wrap">
      <table>
        <tr><th>Time</th><th>User</th><th>Action</th><th>Target</th><th>IP</th></tr>
        <?php foreach ($logs as $l): ?>
        <tr>
          <td style="font-family:var(--font-m);font-size:11px;color:var(--text2)"><?= $l['created_at'] ?></td>
          <td style="font-size:12px"><?= htmlspecialchars($l['display_name'] ?? 'System') ?></td>
          <td><span class="badge badge-gold"><?= htmlspecialchars($l['action']) ?></span></td>
          <td style="font-size:12px;color:var(--text2)"><?= htmlspecialchars($l['target'] ?? '—') ?></td>
          <td style="font-family:var(--font-m);font-size:11px;color:var(--text2)"><?= $l['ip_address'] ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>

  <?php endif; ?>
  </div>
</main>
</body>
</html>
