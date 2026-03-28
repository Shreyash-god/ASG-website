<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login — ASG Studios & ASG Group</title>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;600&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<style>
:root{--gold:#c9a84c;--bg:#000;--bg2:#04040a;--bg3:#080810;--text:#e0e0e0;--text2:#7a7a96;--border:rgba(201,168,76,0.2);--accent:#00e5ff;--red:#ff4757;--green:#2ed573}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:'Rajdhani',sans-serif;min-height:100vh;display:flex;flex-direction:column}
.grid-bg{position:fixed;inset:0;background-image:linear-gradient(rgba(201,168,76,0.04) 1px,transparent 1px),linear-gradient(90deg,rgba(201,168,76,0.04) 1px,transparent 1px);background-size:64px 64px;z-index:0}
.vignette{position:fixed;inset:0;background:radial-gradient(ellipse at 50% 50%,transparent 30%,var(--bg) 100%);z-index:0}
.nav{position:relative;z-index:2;padding:20px 40px;display:flex;align-items:center;gap:16px}
.nav a{font-family:'Orbitron',monospace;font-size:20px;font-weight:900;letter-spacing:5px;color:var(--gold);text-decoration:none}
.main{flex:1;display:flex;align-items:center;justify-content:center;padding:40px;position:relative;z-index:2}
.card{background:rgba(4,4,10,0.92);border:1px solid var(--border);padding:48px;width:100%;max-width:440px;position:relative;backdrop-filter:blur(20px)}
.card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--gold),transparent)}
h1{font-family:'Orbitron',monospace;font-size:22px;font-weight:700;letter-spacing:4px;color:var(--gold);margin-bottom:8px}
.sub{font-family:'Share Tech Mono',monospace;font-size:10px;letter-spacing:3px;color:var(--text2);margin-bottom:36px}
.oauth-btn{display:flex;align-items:center;gap:14px;background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:14px 20px;cursor:pointer;font-family:'Rajdhani',sans-serif;font-size:14px;font-weight:600;transition:all 0.3s;text-decoration:none;width:100%;margin-bottom:10px}
.oauth-btn:hover{border-color:var(--gold);color:var(--gold);background:rgba(201,168,76,0.06)}
.oauth-btn svg{width:20px;height:20px;flex-shrink:0}
.divider{display:flex;align-items:center;gap:16px;margin:24px 0;color:var(--text2);font-family:'Share Tech Mono',monospace;font-size:9px;letter-spacing:3px}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--border)}
.tabs{display:flex;gap:0;margin-bottom:24px}
.tab{flex:1;font-family:'Orbitron',monospace;font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;padding:12px;background:var(--bg3);border:1px solid var(--border);color:var(--text2);cursor:pointer;transition:all 0.3s}
.tab.active{background:rgba(201,168,76,0.1);border-color:var(--gold);color:var(--gold)}
.pane{display:none}.pane.active{display:block}
label{display:block;font-family:'Share Tech Mono',monospace;font-size:9px;letter-spacing:3px;color:var(--text2);text-transform:uppercase;margin-bottom:8px}
input{width:100%;background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:12px 16px;font-family:'Rajdhani',sans-serif;font-size:15px;outline:none;margin-bottom:14px;transition:border-color 0.2s}
input:focus{border-color:var(--gold)}
input::placeholder{color:var(--text2)}
.btn{width:100%;font-family:'Orbitron',monospace;font-size:10px;font-weight:700;letter-spacing:3px;text-transform:uppercase;color:#000;background:linear-gradient(135deg,var(--gold) 0%,#8b6914 100%);border:none;padding:14px;cursor:pointer;transition:all 0.3s;margin-top:6px}
.btn:hover{box-shadow:0 0 20px rgba(201,168,76,0.4)}
.err{font-family:'Share Tech Mono',monospace;font-size:11px;color:var(--red);margin-bottom:12px;display:none}
.ok-msg{font-family:'Share Tech Mono',monospace;font-size:11px;color:var(--green);margin-bottom:12px;display:none}
</style>
</head>
<body>
<div class="grid-bg"></div>
<div class="vignette"></div>
<nav class="nav"><a href="/">ASG</a></nav>
<div class="main">
  <div class="card">
    <h1>WELCOME</h1>
    <div class="sub">// SIGN IN TO ASG STUDIOS & ASG GROUP</div>

    <?php
    $error = $_GET['error'] ?? '';
    $msgs  = ['oauth_failed'=>'OAuth authentication failed. Please try again.','registration_closed'=>'Registration is currently closed.'];
    if ($error && isset($msgs[$error])):
    ?><div style="font-family:'Share Tech Mono',monospace;font-size:11px;color:var(--red);margin-bottom:20px;padding:12px;border:1px solid rgba(255,71,87,0.3);background:rgba(255,71,87,0.08)"><?= htmlspecialchars($msgs[$error]) ?></div><?php endif; ?>

    <a class="oauth-btn" href="/api/auth.php?action=google">
      <svg viewBox="0 0 24 24" fill="none"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
      Continue with Google
    </a>
    <a class="oauth-btn" href="/api/auth.php?action=github">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0 0 24 12c0-6.63-5.37-12-12-12z"/></svg>
      Continue with GitHub
    </a>

    <div class="divider">OR EMAIL</div>
    <div class="tabs">
      <button class="tab active" onclick="st('login')">Login</button>
      <button class="tab" onclick="st('reg')">Register</button>
    </div>

    <div class="pane active" id="p-login">
      <div id="login-ok" class="ok-msg"></div>
      <div id="login-err" class="err"></div>
      <label>Email</label><input type="email" id="l-email" placeholder="your@email.com">
      <label>Password</label><input type="password" id="l-pass" placeholder="••••••••">
      <button class="btn" onclick="doLogin()">Login</button>
    </div>
    <div class="pane" id="p-reg">
      <div id="reg-ok" class="ok-msg"></div>
      <div id="reg-err" class="err"></div>
      <label>Full Name</label><input type="text" id="r-name" placeholder="Your Name">
      <label>Email</label><input type="email" id="r-email" placeholder="your@email.com">
      <label>Password</label><input type="password" id="r-pass" placeholder="Min 8 chars, 1 uppercase, 1 number">
      <button class="btn" onclick="doReg()">Create Account</button>
    </div>
  </div>
</div>
<script>
function st(tab) {
  document.querySelectorAll('.tab').forEach((t,i) => t.classList.toggle('active',(tab==='login'&&i===0)||(tab==='reg'&&i===1)));
  document.getElementById('p-login').classList.toggle('active', tab==='login');
  document.getElementById('p-reg').classList.toggle('active',   tab==='reg');
}
async function doLogin() {
  const email=document.getElementById('l-email').value.trim(), pass=document.getElementById('l-pass').value;
  const err=document.getElementById('login-err'), ok=document.getElementById('login-ok');
  err.style.display='none'; ok.style.display='none';
  const fd=new FormData(); fd.append('email',email); fd.append('password',pass); fd.append('csrf_token','');
  const r=await fetch('/api/auth.php?action=login',{method:'POST',body:fd});
  const d=await r.json();
  if(d.success){ok.textContent='Login successful! Redirecting...';ok.style.display='block';setTimeout(()=>window.location.href=d.redirect||'/',1000);}
  else{err.textContent=d.error;err.style.display='block';}
}
async function doReg() {
  const name=document.getElementById('r-name').value.trim(),email=document.getElementById('r-email').value.trim(),pass=document.getElementById('r-pass').value;
  const err=document.getElementById('reg-err'),ok=document.getElementById('reg-ok');
  err.style.display='none'; ok.style.display='none';
  const fd=new FormData(); fd.append('name',name); fd.append('email',email); fd.append('password',pass); fd.append('csrf_token','');
  const r=await fetch('/api/auth.php?action=register',{method:'POST',body:fd});
  const d=await r.json();
  if(d.success){ok.textContent=d.message;ok.style.display='block';st('login');}
  else{err.textContent=d.error;err.style.display='block';}
}
</script>
</body>
</html>
