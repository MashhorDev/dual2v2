<?php
require_once __DIR__ . '/backend/config.php';
require_once __DIR__ . '/backend/auth.php';
require_login();

$user   = current_user();
$db     = auth_db();

// Load claimed player
$claimedSt = $db->prepare('SELECT * FROM players WHERE claimed_by=? LIMIT 1');
$claimedSt->execute([$user['id']]);
$claimed = $claimedSt->fetch();

// Load pending request if no claimed profile
$pendingSt = $db->prepare("SELECT cr.*, p.riot_name, p.riot_tag, p.region, p.vanity_slug
    FROM claim_requests cr JOIN players p ON p.id=cr.player_id
    WHERE cr.user_id=? ORDER BY cr.requested_at DESC LIMIT 1");
$pendingSt->execute([$user['id']]);
$pending = $pendingSt->fetch();

// Handle settings save
$saveMsg = '';
$saveMsgType = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_settings'])) {
    if (!$claimed) { $saveMsg='No claimed profile.'; $saveMsgType='err'; }
    else {
        $nickname   = substr(trim($_POST['nickname']  ?? ''), 0, 40);
        $flag       = substr(trim($_POST['country_flag'] ?? ''), 0, 8);
        $hide_rank  = isset($_POST['hide_rank'])  ? 1 : 0;
        $hide_stats = isset($_POST['hide_stats']) ? 1 : 0;
        $db->prepare('UPDATE players SET nickname=?,country_flag=?,hide_rank=?,hide_stats=? WHERE id=?')
           ->execute([$nickname?:null, $flag?:null, $hide_rank, $hide_stats, $claimed['id']]);
        $saveMsg = 'Settings saved!'; $saveMsgType = 'ok';
        // Reload
        $claimedSt->execute([$user['id']]);
        $claimed = $claimedSt->fetch();
    }
}

$tiers = unserialize(TIERS);
$tier  = $claimed ? ($tiers[$claimed['tier']] ?? $tiers[0]) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>DUAL // Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#0f1014;color:#c8cfe0;font-family:'Space Mono',monospace;min-height:100vh}
body::before{content:'';position:fixed;inset:0;
  background-image:linear-gradient(rgba(255,70,85,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(255,70,85,.03) 1px,transparent 1px);
  background-size:40px 40px;pointer-events:none}
header{position:relative;z-index:10;padding:20px 40px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #1e2130}
.logo{font-family:'Bebas Neue',sans-serif;font-size:1.8rem;letter-spacing:4px;color:#fff}.logo span{color:#ff4655}
nav a{color:#3a3f56;text-decoration:none;font-size:.65rem;letter-spacing:2px;text-transform:uppercase;margin-left:16px;transition:color .2s}
nav a:hover{color:#ff4655}
nav a.red{color:#ff4655}
main{max-width:900px;margin:0 auto;padding:48px 24px}
.page-title{font-family:'Bebas Neue',sans-serif;font-size:2.2rem;letter-spacing:4px;color:#fff;margin-bottom:4px}
.page-sub{font-size:.6rem;letter-spacing:3px;text-transform:uppercase;color:#ff4655;margin-bottom:40px}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
@media(max-width:640px){.grid{grid-template-columns:1fr}}
.card{background:#14161c;border:1px solid #1e2130;padding:28px;clip-path:polygon(0 0,calc(100% - 16px) 0,100% 16px,100% 100%,16px 100%,0 calc(100% - 16px))}
.card-title{font-family:'Bebas Neue',sans-serif;font-size:1.1rem;letter-spacing:3px;color:#fff;margin-bottom:20px;display:flex;align-items:center;gap:8px}
.card-title::after{content:'';flex:1;height:1px;background:#1e2130}
.profile-name{font-family:'Bebas Neue',sans-serif;font-size:1.8rem;color:#fff;line-height:1}
.profile-tag{font-size:.65rem;color:#3a3f56;letter-spacing:2px;margin-top:4px}
.profile-rank{display:flex;align-items:center;gap:10px;margin-top:16px;padding-top:16px;border-top:1px solid #1e2130}
.rank-icon-big{font-size:2rem}
.rank-info .rname{font-family:'Bebas Neue',sans-serif;font-size:1.2rem;letter-spacing:2px}
.rank-info .rcp{font-size:.62rem;color:#3a3f56;letter-spacing:1px;margin-top:2px}
.vanity-box{margin-top:14px;padding:10px 14px;background:#0f1014;border:1px solid #1e2130;font-size:.7rem;letter-spacing:1px}
.vanity-box a{color:#f5c842;text-decoration:none}.vanity-box a:hover{color:#fff}
.stat-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid rgba(30,33,48,.6);font-size:.7rem}
.stat-row:last-child{border-bottom:none}
.stat-row .val{font-family:'Bebas Neue',sans-serif;font-size:1.1rem;color:#fff}
.stat-row .val.green{color:#15ff80}
.stat-row .val.red{color:#ff4655}
label{display:block;font-size:.58rem;letter-spacing:2px;text-transform:uppercase;color:#3a3f56;margin-bottom:6px;margin-top:16px}
label:first-of-type{margin-top:0}
input[type=text]{width:100%;background:#0f1014;border:1px solid #1e2130;color:#fff;font-family:'Space Mono',monospace;font-size:.82rem;padding:10px 14px;outline:none;letter-spacing:1px;transition:border-color .2s}
input[type=text]:focus{border-color:#ff4655}
.toggle-row{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid #1e2130;font-size:.7rem;letter-spacing:1px}
.toggle{position:relative;width:40px;height:22px}
.toggle input{opacity:0;width:0;height:0}
.toggle-track{position:absolute;inset:0;background:#1e2130;border-radius:11px;cursor:pointer;transition:background .2s}
.toggle input:checked+.toggle-track{background:#ff4655}
.toggle-track::before{content:'';position:absolute;width:16px;height:16px;left:3px;top:3px;background:white;border-radius:50%;transition:transform .2s}
.toggle input:checked+.toggle-track::before{transform:translateX(18px)}
.save-btn{margin-top:20px;width:100%;background:#ff4655;border:none;color:#fff;font-family:'Bebas Neue',sans-serif;font-size:1rem;letter-spacing:3px;padding:12px;cursor:pointer;clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);transition:background .2s}
.save-btn:hover{background:#e03040}
.msg{font-size:.68rem;letter-spacing:1px;margin-top:12px;padding:10px 14px}
.msg.ok{background:rgba(21,255,128,.08);border:1px solid rgba(21,255,128,.3);color:#15ff80}
.msg.err{background:rgba(255,70,85,.08);border:1px solid rgba(255,70,85,.3);color:#ff4655}
.status-box{padding:20px;text-align:center}
.status-icon{font-size:2.5rem;margin-bottom:10px}
.status-label{font-family:'Bebas Neue',sans-serif;font-size:1.1rem;letter-spacing:3px}
.status-sub{font-size:.62rem;letter-spacing:1px;color:#3a3f56;margin-top:6px;line-height:1.8}
.go-search{display:inline-block;margin-top:16px;font-family:'Bebas Neue',sans-serif;font-size:.9rem;letter-spacing:2px;background:transparent;border:1px solid #1e2130;color:#3a3f56;padding:8px 18px;text-decoration:none;transition:all .2s}
.go-search:hover{border-color:#ff4655;color:#ff4655}
.account-card{grid-column:span 2}
@media(max-width:640px){.account-card{grid-column:span 1}}
.info-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid rgba(30,33,48,.6);font-size:.7rem}
.info-row:last-child{border-bottom:none}
.info-row .key{color:#3a3f56;letter-spacing:1px}
.info-row .val{color:#fff}
</style>
</head>
<body>
<header>
  <div class="logo">DUAL<span>//</span>2V2</div>
  <nav>
    <a href="<?=SITE_URL?>/">← Back to Site</a>
    <span style="color:#3a3f56;font-size:.65rem;letter-spacing:1px;margin-left:16px;"><?=htmlspecialchars($user['username'])?></span>
    <a href="<?=SITE_URL?>/auth/handler.php" style="margin-left:16px"
       onclick="fetch('<?=SITE_URL?>/auth/handler.php',{method:'POST',body:new URLSearchParams({action:'logout'})}).then(()=>location.href='<?=SITE_URL?>/');return false">
      Logout
    </a>
  </nav>
</header>

<main>
  <div class="page-title">DASHBOARD</div>
  <div class="page-sub">// <?=htmlspecialchars($user['username'])?>'s Account</div>

  <div class="grid">

    <!-- ── Profile Status ─────────────────────────────────── -->
    <div class="card">
      <div class="card-title">Claimed Profile</div>
      <?php if ($claimed): ?>
        <?php if ($claimed['nickname']): ?>
          <div style="font-size:.72rem;color:#f5c842;letter-spacing:2px;margin-bottom:4px;text-transform:uppercase;">
            <?=htmlspecialchars($claimed['country_flag']??'')?> <?=htmlspecialchars($claimed['nickname'])?>
          </div>
        <?php endif; ?>
        <div class="profile-name"><?=htmlspecialchars($claimed['riot_name'])?></div>
        <div class="profile-tag">#<?=htmlspecialchars($claimed['riot_tag'])?> · <?=strtoupper($claimed['region'])?></div>
        <div class="profile-rank">
          <div class="rank-icon-big"><?=$claimed['placement_done']?($tier['icon']??'🔩'):'⏳'?></div>
          <div class="rank-info">
            <div class="rname" style="color:<?=$tier['color']??'#8b8fa8'?>"><?=$claimed['placement_done']?htmlspecialchars($tier['name']??'IRON'):'IN PLACEMENT'?></div>
            <div class="rcp"><?=(int)$claimed['total_cp']?> CP</div>
          </div>
        </div>
        <?php if ($claimed['vanity_slug']): ?>
        <div class="vanity-box">
          🔗 Your link: <a href="<?=SITE_URL?>/@<?=htmlspecialchars($claimed['vanity_slug'])?>" target="_blank">
            <?=SITE_URL?>/@<?=htmlspecialchars($claimed['vanity_slug'])?>
          </a>
        </div>
        <?php endif; ?>

      <?php elseif ($pending): ?>
        <div class="status-box">
          <?php if ($pending['status']==='pending'): ?>
            <div class="status-icon">⏳</div>
            <div class="status-label" style="color:#f5c842">CLAIM PENDING</div>
            <div class="status-sub">
              Your request for <strong><?=htmlspecialchars($pending['riot_name'])?>#<?=htmlspecialchars($pending['riot_tag'])?></strong><br>
              is waiting for admin approval.
            </div>
          <?php elseif ($pending['status']==='denied'): ?>
            <div class="status-icon">✗</div>
            <div class="status-label" style="color:#ff4655">CLAIM DENIED</div>
            <div class="status-sub">Your last claim request was denied by admin.<br>Search another profile to try again.</div>
            <a class="go-search" href="<?=SITE_URL?>/">SEARCH PLAYERS</a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="status-box">
          <div class="status-icon">🔍</div>
          <div class="status-label" style="color:#3a3f56">NO PROFILE CLAIMED</div>
          <div class="status-sub">Search for your Valorant profile<br>and click "Claim This Profile".</div>
          <a class="go-search" href="<?=SITE_URL?>/">SEARCH PLAYERS</a>
        </div>
      <?php endif; ?>
    </div>

    <!-- ── Stats ──────────────────────────────────────────── -->
    <div class="card">
      <div class="card-title">Stats</div>
      <?php if ($claimed): ?>
        <?php
        $total = $claimed['wins'] + $claimed['losses'];
        $wr    = $total ? round($claimed['wins'] / $total * 100) : 0;
        ?>
        <div class="stat-row"><span>2v2 Matches</span><span class="val"><?=$total?></span></div>
        <div class="stat-row"><span>Wins</span><span class="val green"><?=$claimed['wins']?></span></div>
        <div class="stat-row"><span>Losses</span><span class="val red"><?=$claimed['losses']?></span></div>
        <div class="stat-row"><span>Win Rate</span><span class="val"><?=$wr?>%</span></div>
        <div class="stat-row"><span>Win Streak</span><span class="val"><?=$claimed['win_streak']?></span></div>
        <div class="stat-row"><span>Total CP</span><span class="val"><?=(int)$claimed['total_cp']?></span></div>
      <?php else: ?>
        <div style="color:#3a3f56;font-size:.72rem;letter-spacing:2px;padding:20px 0;">CLAIM A PROFILE TO SEE STATS</div>
      <?php endif; ?>
    </div>

    <!-- ── Profile Settings ───────────────────────────────── -->
    <?php if ($claimed): ?>
    <div class="card" style="grid-column:span 2">
      <div class="card-title">Profile Settings</div>
      <?php if ($saveMsg): ?>
        <div class="msg <?=$saveMsgType?>"><?=htmlspecialchars($saveMsg)?></div>
      <?php endif; ?>
      <form method="POST">
        <input type="hidden" name="save_settings" value="1"/>
        <label>Nickname <span style="color:#3a3f56;font-size:.55rem;">(shown above your Name#TAG on your profile)</span></label>
        <input type="text" name="nickname" value="<?=htmlspecialchars($claimed['nickname']??'')?>" placeholder="Your nickname" maxlength="40"/>
        <label>Country Flag <span style="color:#3a3f56;font-size:.55rem;">(paste a flag emoji e.g. 🇸🇦)</span></label>
        <input type="text" name="country_flag" value="<?=htmlspecialchars($claimed['country_flag']??'')?>" placeholder="🇸🇦" maxlength="4"/>
        <div class="toggle-row" style="margin-top:16px;">
          <span>Hide my rank publicly</span>
          <label class="toggle">
            <input type="checkbox" name="hide_rank" <?=$claimed['hide_rank']?'checked':''?>>
            <span class="toggle-track"></span>
          </label>
        </div>
        <div class="toggle-row">
          <span>Hide my stats publicly</span>
          <label class="toggle">
            <input type="checkbox" name="hide_stats" <?=$claimed['hide_stats']?'checked':''?>>
            <span class="toggle-track"></span>
          </label>
        </div>
        <button class="save-btn" type="submit">SAVE CHANGES</button>
      </form>
    </div>
    <?php endif; ?>

    <!-- ── Account Info ───────────────────────────────────── -->
    <div class="card <?=$claimed?'':'account-card'?>">
      <div class="card-title">Account</div>
      <div class="info-row"><span class="key">Username</span><span class="val"><?=htmlspecialchars($user['username'])?></span></div>
      <div class="info-row"><span class="key">Email</span><span class="val"><?=htmlspecialchars($user['email'])?></span></div>
    </div>

  </div>
</main>
</body>
</html>