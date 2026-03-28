<?php
/**
 * ASG Admin — Rich Post / Doc Editor
 */
define('ASG_BOOT', 1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireAdmin();

$post_id = (int)($_GET['id'] ?? 0);
$post    = $post_id ? DB::one("SELECT * FROM posts WHERE id=?", [$post_id]) : null;
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ASG Admin — <?= $post ? 'Edit' : 'New' ?> Post</title>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<style>
:root{--gold:#c9a84c;--bg:#050508;--bg2:#0a0a10;--bg3:#0d0d18;--accent:#00e5ff;--text:#e0e0e0;--text2:#7a7a90;--border:rgba(201,168,76,0.18);--font-d:'Orbitron',monospace;--font-b:'Rajdhani',sans-serif;--font-m:'Share Tech Mono',monospace}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:var(--font-b);min-height:100vh}
.topbar{background:var(--bg2);border-bottom:1px solid var(--border);padding:16px 32px;display:flex;align-items:center;gap:20px}
.topbar h1{font-family:var(--font-d);font-size:16px;letter-spacing:3px;color:var(--gold)}
.topbar a{font-family:var(--font-m);font-size:10px;letter-spacing:2px;color:var(--text2);text-decoration:none}
.topbar a:hover{color:var(--gold)}
.editor-layout{display:grid;grid-template-columns:1fr 320px;gap:0;min-height:calc(100vh - 57px)}
.editor-main{padding:32px;border-right:1px solid var(--border)}
.editor-sidebar{padding:24px;background:var(--bg2)}
.field-group{margin-bottom:20px}
label{display:block;font-family:var(--font-m);font-size:9px;letter-spacing:3px;color:var(--text2);text-transform:uppercase;margin-bottom:8px}
input[type=text],select,textarea{width:100%;background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:12px 16px;font-family:var(--font-b);font-size:14px;outline:none;transition:border-color 0.2s}
input:focus,select:focus,textarea:focus{border-color:var(--gold)}
.title-input{font-family:var(--font-d);font-size:22px;font-weight:700;letter-spacing:2px;padding:16px;background:transparent;border:none;border-bottom:1px solid var(--border);width:100%;color:var(--text);outline:none;margin-bottom:20px}
.title-input:focus{border-color:var(--gold)}
.title-input::placeholder{color:var(--text2)}
.content-area{width:100%;min-height:500px;background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:20px;font-family:var(--font-b);font-size:15px;line-height:1.8;outline:none;resize:vertical}
.content-area:focus{border-color:var(--gold)}
.btn{display:inline-flex;align-items:center;gap:6px;font-family:var(--font-d);font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;padding:10px 20px;border:none;cursor:pointer;transition:all 0.2s;text-decoration:none}
.btn-gold{background:linear-gradient(135deg,var(--gold) 0%,#8b6914 100%);color:#000}
.btn-gold:hover{box-shadow:0 0 20px rgba(201,168,76,0.4)}
.btn-outline{background:transparent;color:var(--text2);border:1px solid var(--border)}
.btn-outline:hover{border-color:var(--gold);color:var(--gold)}
.btn-red{background:rgba(255,71,87,0.15);color:#ff4757;border:1px solid rgba(255,71,87,0.3)}
.btn-sm{padding:6px 14px;font-size:9px}
.sidebar-group{margin-bottom:24px}
.sidebar-group h3{font-family:var(--font-d);font-size:11px;letter-spacing:3px;color:var(--gold);margin-bottom:16px;text-transform:uppercase}
.toolbar{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:12px;padding:8px;background:var(--bg3);border:1px solid var(--border)}
.tb-btn{font-family:var(--font-m);font-size:10px;color:var(--text2);background:none;border:none;padding:6px 10px;cursor:pointer;transition:color 0.2s}
.tb-btn:hover{color:var(--gold)}
.msg{padding:12px 16px;margin-bottom:16px;font-family:var(--font-m);font-size:11px;border-left:3px solid}
.msg-ok{border-color:#2ed573;color:#2ed573;background:rgba(46,213,115,0.08)}
.msg-err{border-color:#ff4757;color:#ff4757;background:rgba(255,71,87,0.08)}
</style>
</head>
<body>
<div class="topbar">
  <a href="/admin/">← Admin</a>
  <a href="/admin/?page=posts">← Posts</a>
  <h1><?= $post ? 'EDIT POST' : 'NEW POST' ?></h1>
</div>

<form id="post-form" enctype="multipart/form-data" onsubmit="savePost(event)">
  <div class="editor-layout">
    <div class="editor-main">
      <div id="save-msg" style="display:none"></div>
      <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
      <?php if ($post): ?><input type="hidden" name="id" value="<?= $post['id'] ?>"><?php endif; ?>

      <input class="title-input" type="text" name="title" placeholder="Post title..." value="<?= htmlspecialchars($post['title'] ?? '') ?>" required>

      <div class="field-group">
        <label>Excerpt / Summary</label>
        <textarea name="excerpt" rows="2" placeholder="Brief description shown in listing..."><?= htmlspecialchars($post['excerpt'] ?? '') ?></textarea>
      </div>

      <div class="toolbar">
        <button type="button" class="tb-btn" onclick="wrap('**','**')"><b>B</b></button>
        <button type="button" class="tb-btn" onclick="wrap('*','*')"><i>I</i></button>
        <button type="button" class="tb-btn" onclick="ins('## ')">H2</button>
        <button type="button" class="tb-btn" onclick="ins('### ')">H3</button>
        <button type="button" class="tb-btn" onclick="wrap('`','`')">Code</button>
        <button type="button" class="tb-btn" onclick="ins('\n```\n','\n```\n')">Block</button>
        <button type="button" class="tb-btn" onclick="ins('> ')">Quote</button>
        <button type="button" class="tb-btn" onclick="ins('- ')">List</button>
        <button type="button" class="tb-btn" onclick="ins('[Link Text](https://)')">Link</button>
        <button type="button" class="tb-btn" onclick="ins('![Alt](https://)')">Img</button>
      </div>
      <textarea class="content-area" name="content" id="content-area" placeholder="Write your content here (Markdown supported)..."><?= htmlspecialchars($post['content'] ?? '') ?></textarea>
    </div>

    <div class="editor-sidebar">
      <div class="sidebar-group">
        <h3>Publish</h3>
        <div class="field-group">
          <label>Status</label>
          <select name="status">
            <option value="draft"     <?= ($post['status']??'draft')==='draft'     ?'selected':'' ?>>Draft</option>
            <option value="published" <?= ($post['status']??'')==='published' ?'selected':'' ?>>Published</option>
            <option value="archived"  <?= ($post['status']??'')==='archived'  ?'selected':'' ?>>Archived</option>
          </select>
        </div>
        <div class="field-group">
          <label>Type</label>
          <select name="type">
            <option value="blog"          <?= ($post['type']??'')==='blog'          ?'selected':'' ?>>Blog Post</option>
            <option value="code"          <?= ($post['type']??'')==='code'          ?'selected':'' ?>>Code Snippet</option>
            <option value="documentation" <?= ($post['type']??'')==='documentation' ?'selected':'' ?>>Documentation</option>
            <option value="news"          <?= ($post['type']??'')==='news'          ?'selected':'' ?>>News</option>
          </select>
        </div>
        <div class="field-group" style="display:flex;align-items:center;gap:10px">
          <input type="checkbox" name="featured" id="chk-featured" <?= ($post['is_featured']??0)?'checked':'' ?> style="accent-color:var(--gold);width:16px;height:16px">
          <label for="chk-featured" style="margin:0;cursor:pointer">Featured Post</label>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:16px">
          <button type="submit" class="btn btn-gold">Save</button>
          <?php if ($post): ?>
            <button type="button" class="btn btn-red btn-sm" onclick="deletePost()">Delete</button>
          <?php endif; ?>
        </div>
      </div>

      <div class="sidebar-group">
        <h3>Cover Image</h3>
        <?php if (!empty($post['cover_image'])): ?>
          <img src="<?= htmlspecialchars($post['cover_image']) ?>" alt="Cover" style="width:100%;height:120px;object-fit:cover;margin-bottom:10px;border:1px solid var(--border)">
        <?php endif; ?>
        <input type="file" name="cover" accept="image/*" style="font-size:12px;color:var(--text2)">
      </div>

      <div class="sidebar-group">
        <h3>Tags</h3>
        <div class="field-group">
          <input type="text" name="tags" placeholder="tag1, tag2, tag3" value="<?= htmlspecialchars(implode(', ', json_decode($post['tags']??'[]',true))) ?>">
        </div>
      </div>

      <div class="sidebar-group">
        <h3>Upload Media</h3>
        <input type="file" id="media-file" style="font-size:12px;color:var(--text2);margin-bottom:8px">
        <button type="button" class="btn btn-outline btn-sm" onclick="uploadMedia()">Upload & Insert URL</button>
        <div id="media-result" style="margin-top:8px;font-family:var(--font-m);font-size:10px;color:var(--gold);word-break:break-all"></div>
      </div>
    </div>
  </div>
</form>

<script>
const ta = document.getElementById('content-area');
function wrap(before, after='') {
  const s = ta.selectionStart, e = ta.selectionEnd;
  const sel = ta.value.slice(s,e);
  ta.setRangeText(before+sel+after, s, e, 'select');
  ta.focus();
}
function ins(text) {
  const s = ta.selectionStart;
  ta.setRangeText(text, s, s, 'end');
  ta.focus();
}

async function savePost(ev) {
  ev.preventDefault();
  const fd = new FormData(document.getElementById('post-form'));
  const action = <?= $post ? $post['id'] : 'null' ?> ? 'update' : 'create';
  const res = await fetch('/api/posts.php?action='+action, {method:'POST', body:fd});
  const d   = await res.json();
  const msg = document.getElementById('save-msg');
  msg.style.display='block';
  msg.className='msg '+(d.success?'msg-ok':'msg-err');
  msg.textContent = d.success ? '✓ '+(d.message||'Saved') : '✗ '+(d.error||'Error');
  if (d.success && action==='create') setTimeout(()=>window.location.href='/admin/?page=posts',1200);
}

async function deletePost() {
  if (!confirm('Delete this post permanently?')) return;
  const fd = new FormData();
  fd.append('id','<?= $post['id']??0 ?>');
  fd.append('csrf_token','<?= CSRF::token() ?>');
  const r = await fetch('/api/posts.php?action=delete',{method:'POST',body:fd});
  const d = await r.json();
  if (d.success) window.location.href='/admin/?page=posts';
}

async function uploadMedia() {
  const file = document.getElementById('media-file').files[0];
  if (!file) return;
  const fd = new FormData();
  fd.append('file', file);
  fd.append('csrf_token','<?= CSRF::token() ?>');
  const r = await fetch('/api/posts.php?action=upload',{method:'POST',body:fd});
  const d = await r.json();
  if (d.success) {
    document.getElementById('media-result').textContent = d.url;
    ins(`![${file.name}](${d.url})`);
  }
}
</script>
</body>
</html>
