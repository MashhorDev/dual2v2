<?php
// ============================================================
//  DUAL // 2v2 — Installer
//  Visit this once, fill in credentials, then DELETE IT.
// ============================================================

$step  = (int)($_POST['step'] ?? 1);
$error = '';

if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host      = trim($_POST['db_host']       ?? 'localhost');
    $db_name      = trim($_POST['db_name']        ?? '');
    $db_user      = trim($_POST['db_user']        ?? '');
    $db_pass      = trim($_POST['db_pass']        ?? '');
    $henrik_key   = trim($_POST['henrik_key']     ?? '');
    $site_url     = rtrim(trim($_POST['site_url'] ?? ''), '/');
    $admin_user   = trim($_POST['admin_user']     ?? 'admin');
    $admin_email  = trim($_POST['admin_email']    ?? '');
    $admin_pass   = trim($_POST['admin_pass']     ?? '');

    if (!$db_name||!$db_user||!$henrik_key||!$site_url||!$admin_email||!$admin_pass) {
        $step=1; $error='All fields are required.'; goto render;
    }

    // 1. DB connect
    try {
        $pdo = new PDO("mysql:host={$db_host};charset=utf8mb4", $db_user, $db_pass,
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    } catch (PDOException $e) { $step=1; $error='DB connection failed: '.$e->getMessage(); goto render; }

    // 2. Create DB
    try { $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
          $pdo->exec("USE `{$db_name}`"); }
    catch (PDOException $e) { $step=1; $error='Could not create database: '.$e->getMessage(); goto render; }

    // 3. Tables
    $tables = [
    "CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(32) NOT NULL UNIQUE, email VARCHAR(120) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL, is_admin TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS players (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        puuid VARCHAR(80) NOT NULL UNIQUE, riot_name VARCHAR(64) NOT NULL,
        riot_tag VARCHAR(12) NOT NULL, region VARCHAR(10) NOT NULL DEFAULT 'na',
        card_url TEXT, total_cp INT NOT NULL DEFAULT 0, cp_in_tier SMALLINT NOT NULL DEFAULT 0,
        tier TINYINT NOT NULL DEFAULT 0, wins SMALLINT NOT NULL DEFAULT 0,
        losses SMALLINT NOT NULL DEFAULT 0, win_streak TINYINT NOT NULL DEFAULT 0,
        placement_done TINYINT(1) NOT NULL DEFAULT 0, last_sync DATETIME,
        claimed_by INT UNSIGNED NULL, claimed_at DATETIME NULL,
        vanity_slug VARCHAR(80) NULL UNIQUE,
        nickname VARCHAR(40) NULL, country_flag VARCHAR(8) NULL,
        hide_rank TINYINT(1) NOT NULL DEFAULT 0, hide_stats TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_cp (total_cp), INDEX idx_region (region),
        FOREIGN KEY (claimed_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS matches (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        player_id INT UNSIGNED NOT NULL, match_id VARCHAR(80) NOT NULL,
        map_name VARCHAR(40), queue_id VARCHAR(60),
        partner_puuid VARCHAR(80), partner_name VARCHAR(80),
        won TINYINT(1) NOT NULL DEFAULT 0, kills TINYINT NOT NULL DEFAULT 0,
        deaths TINYINT NOT NULL DEFAULT 0, assists TINYINT NOT NULL DEFAULT 0,
        headshots SMALLINT NOT NULL DEFAULT 0, bodyshots SMALLINT NOT NULL DEFAULT 0,
        legshots SMALLINT NOT NULL DEFAULT 0, rounds_won TINYINT NOT NULL DEFAULT 0,
        rounds_lost TINYINT NOT NULL DEFAULT 0, cp_delta SMALLINT NOT NULL DEFAULT 0,
        kda_ratio DECIMAL(5,2) NOT NULL DEFAULT 0, played_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_pm (player_id, match_id), INDEX idx_pl (player_id),
        FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS claim_requests (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL, player_id INT UNSIGNED NOT NULL,
        status ENUM('pending','approved','denied') NOT NULL DEFAULT 'pending',
        requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        resolved_at DATETIME NULL, resolved_by INT UNSIGNED NULL,
        UNIQUE KEY uq_up (user_id, player_id), INDEX idx_status (status),
        FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
        FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];

    try { foreach ($tables as $sql) $pdo->exec($sql); }
    catch (PDOException $e) { $step=1; $error='Table creation failed: '.$e->getMessage(); goto render; }

    // 4. Create admin account
    $hash = password_hash($admin_pass, PASSWORD_BCRYPT);
    try {
        $pdo->prepare("INSERT IGNORE INTO users (username,email,password_hash,is_admin) VALUES (?,?,?,1)")
            ->execute([$admin_user, $admin_email, $hash]);
    } catch (PDOException $e) { $step=1; $error='Admin user creation failed: '.$e->getMessage(); goto render; }

    // 5. Write config.php
    $secret = bin2hex(random_bytes(32));
    $config = <<<PHP
<?php
// Auto-generated by DUAL // 2v2 Installer
define('DB_HOST', '{$db_host}');
define('DB_NAME', '{$db_name}');
define('DB_USER', '{$db_user}');
define('DB_PASS', '{$db_pass}');
define('HENRIK_API_KEY', '{$henrik_key}');
define('SITE_URL', '{$site_url}');
define('SESSION_SECRET', '{$secret}');
define('ADMIN_USERNAME', '{$admin_user}');
define('ADMIN_EMAIL',    '{$admin_email}');
define('ADMIN_PASSWORD', '{$admin_pass}');
define('CP_PER_TIER',        100);
define('CP_WIN_BASE',         15);
define('CP_WIN_KDA_LOW',       3);
define('CP_WIN_KDA_HIGH',      5);
define('CP_WIN_DOMINANT',      2);
define('CP_WIN_FLAWLESS',      5);
define('CP_LOSS_BASE',        -15);
define('CP_LOSS_MITIGATION',   3);
define('CP_STREAK_BONUS', serialize([0, 0, 2, 3, 5]));
define('TIERS', serialize([
    0 => ['name' => 'IRON',      'icon' => '🔩', 'color' => '#8b8fa8'],
    1 => ['name' => 'BRONZE',    'icon' => '🥉', 'color' => '#cd7f32'],
    2 => ['name' => 'SILVER',    'icon' => '🥈', 'color' => '#c0c0c0'],
    3 => ['name' => 'GOLD',      'icon' => '🥇', 'color' => '#f5c842'],
    4 => ['name' => 'PLATINUM',  'icon' => '💎', 'color' => '#00d4ff'],
    5 => ['name' => 'DIAMOND',   'icon' => '🔷', 'color' => '#b770ff'],
    6 => ['name' => 'ASCENDANT', 'icon' => '🌸', 'color' => '#15ff80'],
    7 => ['name' => 'IMMORTAL',  'icon' => '👑', 'color' => '#ff4655'],
    8 => ['name' => 'RADIANT',   'icon' => '⭐', 'color' => '#fff5a0'],
]));
PHP;

    if (!is_dir(__DIR__.'/backend')) mkdir(__DIR__.'/backend',0755,true);
    if (file_put_contents(__DIR__.'/backend/config.php', $config) === false) {
        $step=1; $error='Could not write backend/config.php — check folder permissions.'; goto render;
    }

    // 6. Test Henrik key
    $henrik_ok=false; $henrik_msg='';
    $ch=curl_init('https://api.henrikdev.xyz/valorant/v1/account/TenZ/0505');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>8,CURLOPT_HTTPHEADER=>['Authorization: '.$henrik_key]]);
    $hbody=curl_exec($ch); $hcode=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if ($hcode===200||$hcode===404) { $henrik_ok=true; $henrik_msg='API key verified ✓'; }
    elseif ($hcode===403) $henrik_msg='API key rejected (403)';
    else { $henrik_ok=true; $henrik_msg="Henrik returned HTTP {$hcode} — key saved anyway"; }

    $step = 3;
}

render:
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>DUAL // 2v2 Installer</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#0f1014;color:#c8cfe0;font-family:'Space Mono',monospace;min-height:100vh;display:flex;align-items:flex-start;justify-content:center;padding:40px 20px}
body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(255,70,85,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(255,70,85,.03) 1px,transparent 1px);background-size:40px 40px;pointer-events:none}
.box{position:relative;background:#14161c;border:1px solid #1e2130;width:100%;max-width:560px;padding:48px 44px;clip-path:polygon(0 0,calc(100% - 24px) 0,100% 24px,100% 100%,24px 100%,0 calc(100% - 24px))}
.logo{font-family:'Bebas Neue',sans-serif;font-size:2rem;letter-spacing:4px;color:#fff;margin-bottom:4px}.logo span{color:#ff4655}
.subtitle{font-size:.58rem;letter-spacing:3px;text-transform:uppercase;color:#ff4655;margin-bottom:36px}
.section-divider{font-size:.55rem;letter-spacing:3px;text-transform:uppercase;color:#3a3f56;margin:28px 0 12px;border-top:1px solid #1e2130;padding-top:18px}
label{display:block;font-size:.58rem;letter-spacing:2px;text-transform:uppercase;color:#3a3f56;margin-bottom:6px;margin-top:14px}
input{width:100%;background:#0f1014;border:1px solid #1e2130;color:#fff;font-family:'Space Mono',monospace;font-size:.82rem;padding:11px 14px;outline:none;letter-spacing:1px;transition:border-color .2s}
input:focus{border-color:#ff4655}
.hint{font-size:.58rem;color:#3a3f56;letter-spacing:1px;margin-top:4px;line-height:1.6}
.hint a{color:#ff4655;text-decoration:none}
.btn{margin-top:28px;width:100%;background:#ff4655;border:none;color:#fff;font-family:'Bebas Neue',sans-serif;font-size:1.1rem;letter-spacing:4px;padding:14px;cursor:pointer;clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);transition:background .2s}
.btn:hover{background:#e03040}
.error{background:rgba(255,70,85,.08);border:1px solid rgba(255,70,85,.3);color:#ff4655;padding:14px;font-size:.7rem;letter-spacing:1px;margin-bottom:20px;line-height:1.7}
.done{text-align:center;padding:8px 0}
.big-check{font-size:3rem;margin-bottom:12px}
.done h2{font-family:'Bebas Neue',sans-serif;font-size:2rem;letter-spacing:4px;color:#15ff80;margin-bottom:16px}
.check-list{text-align:left;background:#0f1014;border:1px solid #1e2130;padding:18px 20px;margin:16px 0;font-size:.7rem;line-height:2.4;letter-spacing:1px}
.ok{color:#15ff80}.warn{color:#f5c842}
.go-btn{display:block;text-align:center;background:#ff4655;color:#fff;font-family:'Bebas Neue',sans-serif;font-size:1.1rem;letter-spacing:4px;padding:14px;text-decoration:none;clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);margin-bottom:12px;transition:background .2s}
.go-btn:hover{background:#e03040}
.delete-warn{background:rgba(245,200,66,.08);border:1px solid rgba(245,200,66,.3);color:#f5c842;padding:12px 14px;font-size:.62rem;letter-spacing:1px;line-height:1.7;margin-top:14px}
</style>
</head>
<body>
<div class="box">
<?php if ($step===1||($step===2&&$error)): ?>
<div class="logo">DUAL<span>//</span>2V2</div>
<div class="subtitle">Installer — Fill in once, then delete this file</div>
<?php if ($error): ?><div class="error">⚠ <?=htmlspecialchars($error)?></div><?php endif; ?>
<form method="POST">
<input type="hidden" name="step" value="2"/>

<div class="section-divider">Database</div>
<label>Host</label>
<input type="text" name="db_host" value="<?=htmlspecialchars($_POST['db_host']??'localhost')?>"/>
<label>Database Name</label>
<input type="text" name="db_name" value="<?=htmlspecialchars($_POST['db_name']??'')?>" required/>
<div class="hint">On cPanel this is usually <strong>yourusername_dual2v2</strong></div>
<label>DB Username</label>
<input type="text" name="db_user" value="<?=htmlspecialchars($_POST['db_user']??'')?>" required/>
<label>DB Password</label>
<input type="password" name="db_pass" value="<?=htmlspecialchars($_POST['db_pass']??'')?>"/>

<div class="section-divider">Admin Account</div>
<label>Admin Username</label>
<input type="text" name="admin_user" value="<?=htmlspecialchars($_POST['admin_user']??'admin')?>" required/>
<label>Admin Email</label>
<input type="email" name="admin_email" value="<?=htmlspecialchars($_POST['admin_email']??'')?>" required/>
<label>Admin Password</label>
<input type="password" name="admin_pass" value="" required/>
<div class="hint">Use this to log in at <strong>/login.php</strong> then go to <strong>/admin/</strong></div>

<div class="section-divider">API & Site</div>
<label>Henrik Dev API Key</label>
<input type="text" name="henrik_key" value="<?=htmlspecialchars($_POST['henrik_key']??'')?>" required/>
<div class="hint">Get free key → <a href="https://discord.com/invite/X3GaVkX2YN" target="_blank">Henrik Dev Discord</a></div>
<label>Site URL</label>
<input type="text" name="site_url" value="<?=htmlspecialchars($_POST['site_url']??(isset($_SERVER['HTTPS'])?'https':'http').'://'.($_SERVER['HTTP_HOST']??'localhost').'/dual2v2')?>" required/>

<button class="btn" type="submit">INSTALL NOW</button>
</form>

<?php elseif ($step===3): ?>
<div class="done">
  <div class="big-check">✅</div>
  <h2>INSTALLED!</h2>
  <div class="check-list">
    <div class="ok">✓ Database &amp; tables created</div>
    <div class="ok">✓ Admin account created</div>
    <div class="ok">✓ backend/config.php written</div>
    <div class="<?=$henrik_ok?'ok':'warn'?>"><?=$henrik_ok?'✓':'⚠'?> <?=htmlspecialchars($henrik_msg)?></div>
  </div>
  <a class="go-btn" href="index.html">→ OPEN SITE</a>
  <a class="go-btn" href="login.php" style="background:#1e2130;margin-top:4px;">→ LOGIN AS ADMIN</a>
  <div class="delete-warn">⚠ <strong>DELETE installer.php NOW</strong> — it contains your credentials in plain text.</div>
</div>
<?php endif; ?>
</div>
</body>
</html>
