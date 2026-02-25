<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>DUAL // Login</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#0f1014;color:#c8cfe0;font-family:'Space Mono',monospace;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:40px 20px}
body::before{content:'';position:fixed;inset:0;
  background-image:linear-gradient(rgba(255,70,85,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(255,70,85,.03) 1px,transparent 1px);
  background-size:40px 40px;pointer-events:none}
.box{position:relative;background:#14161c;border:1px solid #1e2130;width:100%;max-width:440px;padding:48px 44px;
  clip-path:polygon(0 0,calc(100% - 24px) 0,100% 24px,100% 100%,24px 100%,0 calc(100% - 24px))}
.logo{font-family:'Bebas Neue',sans-serif;font-size:2rem;letter-spacing:4px;color:#fff;margin-bottom:4px}
.logo span{color:#ff4655}
.subtitle{font-size:.58rem;letter-spacing:3px;text-transform:uppercase;color:#ff4655;margin-bottom:36px}
.tabs{display:flex;gap:0;border-bottom:1px solid #1e2130;margin-bottom:28px}
.tab-btn{flex:1;background:none;border:none;color:#3a3f56;font-family:'Space Mono',monospace;
  font-size:.65rem;letter-spacing:2px;text-transform:uppercase;padding:12px;cursor:pointer;
  border-bottom:2px solid transparent;margin-bottom:-1px;transition:color .2s}
.tab-btn:hover{color:#c8cfe0}
.tab-btn.active{color:#ff4655;border-bottom-color:#ff4655}
.form-section{display:none}
.form-section.active{display:block;animation:fadeIn .3s ease}
@keyframes fadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
label{display:block;font-size:.58rem;letter-spacing:2px;text-transform:uppercase;color:#3a3f56;margin-bottom:6px;margin-top:18px}
label:first-of-type{margin-top:0}
input{width:100%;background:#0f1014;border:1px solid #1e2130;color:#fff;
  font-family:'Space Mono',monospace;font-size:.82rem;padding:12px 14px;
  outline:none;letter-spacing:1px;transition:border-color .2s}
input:focus{border-color:#ff4655}
.btn{margin-top:24px;width:100%;background:#ff4655;border:none;color:#fff;
  font-family:'Bebas Neue',sans-serif;font-size:1.1rem;letter-spacing:4px;padding:14px;
  cursor:pointer;clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);transition:background .2s}
.btn:hover{background:#e03040}
.btn:disabled{background:#3a3f56;cursor:not-allowed}
.error{background:rgba(255,70,85,.08);border:1px solid rgba(255,70,85,.3);
  color:#ff4655;padding:12px 14px;font-size:.7rem;letter-spacing:1px;margin-bottom:18px;display:none}
.success{background:rgba(21,255,128,.08);border:1px solid rgba(21,255,128,.3);
  color:#15ff80;padding:12px 14px;font-size:.7rem;letter-spacing:1px;margin-bottom:18px;display:none}
.bottom-link{margin-top:20px;font-size:.62rem;color:#3a3f56;text-align:center}
.bottom-link a{color:#ff4655;text-decoration:none}
.bottom-link a:hover{text-decoration:underline}
</style>
</head>
<body>
<div class="box">
  <div class="logo">DUAL<span>//</span>2V2</div>
  <div class="subtitle">// Player Account</div>

  <div class="tabs">
    <button class="tab-btn active" onclick="switchTab('login')">LOGIN</button>
    <button class="tab-btn" onclick="switchTab('signup')">SIGN UP</button>
  </div>

  <!-- LOGIN -->
  <div class="form-section active" id="section-login">
    <div class="error" id="login-error"></div>
    <div class="success" id="login-success"></div>
    <label>Username or Email</label>
    <input type="text" id="login-identifier" placeholder="your_username" autocomplete="username"/>
    <label>Password</label>
    <input type="password" id="login-password" placeholder="••••••••" autocomplete="current-password"/>
    <button class="btn" id="login-btn" onclick="doLogin()">LOGIN</button>
    <div class="bottom-link">No account? <a href="#" onclick="switchTab('signup');return false">Sign up</a></div>
  </div>

  <!-- SIGNUP -->
  <div class="form-section" id="section-signup">
    <div class="error" id="signup-error"></div>
    <div class="success" id="signup-success"></div>
    <label>Username</label>
    <input type="text" id="signup-username" placeholder="your_username" maxlength="32" autocomplete="username"/>
    <label>Email</label>
    <input type="email" id="signup-email" placeholder="you@email.com" autocomplete="email"/>
    <label>Password</label>
    <input type="password" id="signup-password" placeholder="min 6 characters" autocomplete="new-password"/>
    <button class="btn" id="signup-btn" onclick="doSignup()">CREATE ACCOUNT</button>
    <div class="bottom-link">Already have an account? <a href="#" onclick="switchTab('login');return false">Login</a></div>
  </div>
</div>

<script>
// Use path relative to current page — works on localhost AND production
const SITE     = window.location.pathname.replace(/\/[^\/]+$/, '');  // e.g. /dual2v2
const AUTH_URL = SITE + '/auth/handler.php';
const next     = new URLSearchParams(location.search).get('next') || SITE + '/index.html';

// Auto-switch to signup if ?tab=signup
if (new URLSearchParams(location.search).get('tab') === 'signup') switchTab('signup');

function switchTab(tab) {
  document.querySelectorAll('.tab-btn').forEach((b,i)=> b.classList.toggle('active',['login','signup'][i]===tab));
  document.querySelectorAll('.form-section').forEach((s,i)=> s.classList.toggle('active',['login','signup'][i]===tab));
}

async function doLogin() {
  const identifier = document.getElementById('login-identifier').value.trim();
  const password   = document.getElementById('login-password').value;
  const btn        = document.getElementById('login-btn');
  const errEl      = document.getElementById('login-error');
  const okEl       = document.getElementById('login-success');

  errEl.style.display = 'none';
  okEl.style.display  = 'none';
  if (!identifier || !password) { showErr(errEl,'Fill in all fields'); return; }

  btn.disabled = true; btn.textContent = 'LOGGING IN...';
  try {
    const fd = new FormData();
    fd.append('action','login'); fd.append('identifier',identifier); fd.append('password',password);
    const res  = await fetch(AUTH_URL, {method:'POST', body:fd});
    const data = await res.json();
    if (!data.ok) { showErr(errEl, data.error || 'Login failed'); return; }
    okEl.textContent = '✓ Logged in! Redirecting...';
    okEl.style.display = 'block';
    setTimeout(()=>{ location.href = next; }, 800);
  } catch(e) { showErr(errEl,'Request failed: '+e.message); }
  finally { btn.disabled=false; btn.textContent='LOGIN'; }
}

async function doSignup() {
  const username = document.getElementById('signup-username').value.trim();
  const email    = document.getElementById('signup-email').value.trim();
  const password = document.getElementById('signup-password').value;
  const btn      = document.getElementById('signup-btn');
  const errEl    = document.getElementById('signup-error');
  const okEl     = document.getElementById('signup-success');

  errEl.style.display = 'none';
  okEl.style.display  = 'none';
  if (!username||!email||!password) { showErr(errEl,'Fill in all fields'); return; }

  btn.disabled=true; btn.textContent='CREATING...';
  try {
    const fd = new FormData();
    fd.append('action','signup'); fd.append('username',username);
    fd.append('email',email); fd.append('password',password);
    const res  = await fetch(AUTH_URL, {method:'POST', body:fd});
    const data = await res.json();
    if (!data.ok) { showErr(errEl, data.error||'Signup failed'); return; }
    okEl.textContent = '✓ Account created! Redirecting...';
    okEl.style.display='block';
    setTimeout(()=>{ location.href=next; },800);
  } catch(e) { showErr(errEl,'Request failed: '+e.message); }
  finally { btn.disabled=false; btn.textContent='CREATE ACCOUNT'; }
}

function showErr(el, msg) { el.textContent='⚠ '+msg; el.style.display='block'; }

// Enter key support
document.addEventListener('keydown', e=>{
  if (e.key!=='Enter') return;
  const active = document.querySelector('.form-section.active').id;
  if (active==='section-login') doLogin();
  else doSignup();
});
</script>
</body>
</html>