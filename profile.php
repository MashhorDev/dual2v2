<?php
require_once __DIR__ . '/backend/config.php';
require_once __DIR__ . '/backend/auth.php';

$slug = trim($_GET['slug'] ?? '');
if (!$slug) { header('Location: ' . SITE_URL . '/'); exit; }

$db = auth_db();

$st = $db->prepare('SELECT * FROM players WHERE vanity_slug=? LIMIT 1');
$st->execute([$slug]);
$player = $st->fetch();

if (!$player) {
    header('Location: ' . SITE_URL . '/');
    exit;
}

$mst = $db->prepare('SELECT * FROM matches WHERE player_id=? ORDER BY played_at DESC LIMIT 50');
$mst->execute([$player['id']]);
$matches = $mst->fetchAll();

$currentUser = current_user();
$claimState  = 'none';
if ($currentUser) $claimState = claim_state((int)$currentUser['id'], (int)$player['id']);

$tiers    = unserialize(TIERS);
$tier     = $tiers[$player['tier']] ?? $tiers[0];
$nextTier = $tiers[$player['tier'] + 1] ?? null;

$totalMatches = (int)$player['wins'] + (int)$player['losses'];
$winRate      = $totalMatches ? round($player['wins'] / $totalMatches * 100) : 0;
$cpInTier     = $player['placement_done'] ? ((int)$player['total_cp'] % (int)CP_PER_TIER) : 0;
$cpPct        = $player['placement_done'] ? min(100, $cpInTier) : 0;

// Aggregate match stats
$tk = $td = $ta = $ths = $tshots = 0;
foreach ($matches as $m) {
    $tk     += (int)$m['kills'];
    $td     += (int)$m['deaths'];
    $ta     += (int)$m['assists'];
    $ths    += (int)$m['headshots'];
    $tshots += (int)$m['headshots'] + (int)$m['bodyshots'] + (int)$m['legshots'];
}
$avgKda  = $td ? round(($tk + $ta * 0.5) / $td, 2) : $tk;
$hsPct   = $tshots ? round($ths / $tshots * 100) : 0;

$hideRank  = $player['hide_rank']  && !($currentUser && $claimState === 'approved');
$hideStats = $player['hide_stats'] && !($currentUser && $claimState === 'approved');

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title><?=e($player['riot_name']).'#'.e($player['riot_tag'])?> // DUAL 2V2</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Space+Mono:wght@400;700&family=Syne:wght@400;700;800&display=swap" rel="stylesheet">
<style>
:root{--red:#ff4655;--dark:#0f1014;--panel:#14161c;--border:#1e2130;--muted:#3a3f56;--text:#c8cfe0;--bright:#fff;--gold:#f5c842;--cyan:#00d4ff;--green:#15ff80}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{background:var(--dark);color:var(--text);font-family:'Space Mono',monospace;min-height:100vh}
body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(255,70,85,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(255,70,85,.03) 1px,transparent 1px);background-size:40px 40px;pointer-events:none;z-index:0}
header{position:relative;z-index:10;padding:20px 48px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border)}
.logo{font-family:'Bebas Neue',sans-serif;font-size:2rem;letter-spacing:4px;color:var(--bright)}
.logo span{color:var(--red)}
.nav-user{display:flex;align-items:center;gap:10px}
.nav-btn{font-family:'Bebas Neue',sans-serif;font-size:.85rem;letter-spacing:2px;background:var(--red);border:none;color:#fff;padding:6px 16px;cursor:pointer;clip-path:polygon(4px 0%,100% 0%,calc(100% - 4px) 100%,0% 100%);transition:background .2s;text-decoration:none;display:inline-block}
.nav-btn:hover{background:#e03040}
.nav-btn.outline{background:transparent;border:1px solid var(--border);color:var(--muted);clip-path:none}
.nav-btn.outline:hover{border-color:var(--red);color:var(--red)}
.nav-username{font-size:.65rem;letter-spacing:1px;color:var(--cyan)}
main{position:relative;z-index:5;max-width:1100px;margin:0 auto;padding:40px 24px 80px}

/* Profile card */
.profile-card{display:grid;grid-template-columns:auto 1fr auto;gap:24px;align-items:center;background:var(--panel);border:1px solid var(--border);padding:28px 32px;margin-bottom:20px;clip-path:polygon(0 0,calc(100% - 20px) 0,100% 20px,100% 100%,20px 100%,0 calc(100% - 20px))}
.player-avatar{width:72px;height:72px;border:2px solid var(--red);overflow:hidden;clip-path:polygon(10px 0%,100% 0%,calc(100% - 10px) 100%,0% 100%)}
.player-avatar img{width:100%;height:100%;object-fit:cover}
.player-info h2{font-family:'Syne',sans-serif;font-size:1.6rem;font-weight:800;color:var(--bright);line-height:1;margin-bottom:4px}
.player-info .tag{font-size:.7rem;color:var(--muted);letter-spacing:2px}
.player-info .sync-time{font-size:.58rem;color:var(--muted);letter-spacing:1px;margin-top:4px}
.player-nickname{font-size:.72rem;letter-spacing:2px;color:var(--gold);margin-bottom:6px;font-weight:700;text-transform:uppercase}
.vanity-copy{font-size:.62rem;letter-spacing:1px;color:var(--gold);margin-top:6px;cursor:pointer;text-decoration:none;display:inline-block}
.vanity-copy:hover{color:var(--bright)}
.rank-display{text-align:right}
.rank-icon{font-size:2.8rem;line-height:1}
.rank-name{font-family:'Bebas Neue',sans-serif;font-size:1.4rem;letter-spacing:3px;color:var(--bright)}
.rank-pts{font-size:.65rem;letter-spacing:2px;color:var(--muted)}

/* Claim badge */
.claim-badge{margin-bottom:20px}
.claim-status{font-size:.65rem;letter-spacing:2px;padding:10px 16px;display:inline-block}
.claim-status.approved{color:var(--green);background:rgba(21,255,128,.08);border:1px solid rgba(21,255,128,.25)}
.claim-status.pending{color:var(--gold);background:rgba(245,200,66,.08);border:1px solid rgba(245,200,66,.25)}
.claim-status.denied{color:var(--red);background:rgba(255,70,85,.08);border:1px solid rgba(255,70,85,.25)}
.claim-status.other{color:var(--muted);background:rgba(58,63,86,.1);border:1px solid var(--border)}
.claim-btn{font-family:'Bebas Neue',sans-serif;font-size:1rem;letter-spacing:3px;background:transparent;border:1px solid var(--cyan);color:var(--cyan);padding:10px 24px;cursor:pointer;transition:all .2s;clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);text-decoration:none;display:inline-block}
.claim-btn:hover{background:var(--cyan);color:var(--dark)}
.settings-btn{font-family:'Bebas Neue',sans-serif;font-size:1rem;letter-spacing:3px;background:transparent;border:1px solid var(--border);color:var(--muted);padding:10px 24px;cursor:pointer;transition:all .2s;margin-left:8px;clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%)}
.settings-btn:hover{border-color:var(--red);color:var(--red)}

/* Sync btn */
.sync-btn{font-family:'Bebas Neue',sans-serif;font-size:.85rem;letter-spacing:2px;background:transparent;border:1px solid var(--border);color:var(--muted);padding:6px 18px;cursor:pointer;transition:all .2s;margin-left:10px;clip-path:polygon(4px 0%,100% 0%,calc(100% - 4px) 100%,0% 100%)}
.sync-btn:hover{border-color:var(--cyan);color:var(--cyan)}
.sync-btn:disabled{opacity:.4;cursor:not-allowed}

/* CP bar */
.cp-bar-wrap{background:var(--panel);border:1px solid var(--border);padding:14px 24px;margin-bottom:20px;display:flex;align-items:center;gap:14px}
.cp-bar-label{font-size:.6rem;letter-spacing:2px;color:var(--muted);white-space:nowrap}
.cp-bar-track{flex:1;height:6px;background:var(--border)}
.cp-bar-fill{height:100%;transition:width .6s cubic-bezier(.4,0,.2,1)}
.cp-bar-pct{font-size:.6rem;min-width:36px;text-align:right}
.cp-bar-next{font-size:.6rem;color:var(--muted);white-space:nowrap}

/* Stats */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:32px}
.stat-card{background:var(--panel);border:1px solid var(--border);padding:20px;position:relative;overflow:hidden;transition:border-color .2s}
.stat-card:hover{border-color:var(--red)}
.stat-card::before{content:'';position:absolute;top:0;left:0;width:3px;height:100%;background:var(--red)}
.stat-label{font-size:.6rem;letter-spacing:3px;text-transform:uppercase;color:var(--muted);margin-bottom:8px}
.stat-value{font-family:'Bebas Neue',sans-serif;font-size:2rem;color:var(--bright);line-height:1}
.stat-value.green{color:var(--green)}
.stat-value.gold{color:var(--gold)}

/* Tabs */
.tabs{display:flex;margin-bottom:32px;border-bottom:1px solid var(--border)}
.tab{font-family:'Space Mono',monospace;font-size:.65rem;letter-spacing:2px;text-transform:uppercase;padding:12px 24px;background:none;border:none;color:var(--muted);cursor:pointer;transition:color .2s;border-bottom:2px solid transparent;margin-bottom:-1px}
.tab:hover{color:var(--text)}
.tab.active{color:var(--red);border-bottom-color:var(--red)}
.tab-content{display:none}
.tab-content.active{display:block}

/* Match list */
.section-title{font-family:'Bebas Neue',sans-serif;font-size:1.4rem;letter-spacing:4px;color:var(--bright);margin-bottom:16px;display:flex;align-items:center;gap:12px}
.section-title::after{content:'';flex:1;height:1px;background:var(--border)}
.match-list{display:flex;flex-direction:column;gap:8px}
.match-row{background:var(--panel);border:1px solid var(--border);display:grid;grid-template-columns:90px 1fr 1fr 80px 110px 90px;align-items:center;padding:14px 20px;gap:12px;transition:border-color .2s,background .2s;clip-path:polygon(0 0,100% 0,100% calc(100% - 8px),calc(100% - 8px) 100%,0 100%)}
.match-row:hover{border-color:rgba(255,70,85,.4);background:rgba(255,70,85,.04)}
.match-result{font-family:'Bebas Neue',sans-serif;font-size:1.1rem;letter-spacing:2px;text-align:center}
.match-result.win{color:var(--green)}
.match-result.loss{color:var(--red)}
.match-map{font-size:.75rem;color:var(--bright);letter-spacing:1px}
.match-map small{display:block;font-size:.6rem;color:var(--muted);margin-top:2px}
.match-partner{font-size:.72rem;color:var(--text)}
.match-partner small{display:block;font-size:.6rem;color:var(--muted)}
.match-score{font-family:'Bebas Neue',sans-serif;font-size:1.1rem;text-align:center;color:var(--bright)}
.match-kda{font-size:.72rem;text-align:center;color:var(--cyan)}
.match-cp{font-family:'Bebas Neue',sans-serif;font-size:1rem;text-align:right;letter-spacing:1px}
.match-cp.pos{color:var(--green)}
.match-cp.neg{color:var(--red)}
.empty-notice{color:var(--muted);font-size:.75rem;padding:24px;letter-spacing:2px;border:1px dashed var(--border)}
.hidden-notice{color:var(--muted);font-size:.7rem;letter-spacing:2px;padding:24px;border:1px dashed var(--border);text-align:center;margin-bottom:24px}

/* Modal */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:100;align-items:center;justify-content:center}
.modal-bg.open{display:flex}
.modal{background:var(--panel);border:1px solid var(--border);width:100%;max-width:480px;padding:40px;margin:20px;clip-path:polygon(0 0,calc(100% - 20px) 0,100% 20px,100% 100%,20px 100%,0 calc(100% - 20px))}
.modal h3{font-family:'Bebas Neue',sans-serif;font-size:1.4rem;letter-spacing:4px;color:var(--bright);margin-bottom:24px}
.modal label{display:block;font-size:.58rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:6px;margin-top:16px}
.modal label:first-of-type{margin-top:0}
.modal input[type=text]{width:100%;background:var(--dark);border:1px solid var(--border);color:var(--bright);font-family:'Space Mono',monospace;font-size:.82rem;padding:10px 14px;outline:none;letter-spacing:1px;transition:border-color .2s}
.modal input[type=text]:focus{border-color:var(--red)}
.modal-toggle{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--border)}
.modal-toggle span{font-size:.7rem;letter-spacing:1px}
.toggle{position:relative;width:40px;height:22px}
.toggle input{opacity:0;width:0;height:0}
.toggle-track{position:absolute;inset:0;background:var(--border);border-radius:11px;cursor:pointer;transition:background .2s}
.toggle input:checked+.toggle-track{background:var(--red)}
.toggle-track::before{content:'';position:absolute;width:16px;height:16px;left:3px;top:3px;background:white;border-radius:50%;transition:transform .2s}
.toggle input:checked+.toggle-track::before{transform:translateX(18px)}
.modal-btns{display:flex;gap:10px;margin-top:28px}
.modal-save{flex:1;font-family:'Bebas Neue',sans-serif;font-size:1rem;letter-spacing:3px;background:var(--red);border:none;color:white;padding:12px;cursor:pointer;clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);transition:background .2s}
.modal-save:hover{background:#e03040}
.modal-cancel{font-family:'Bebas Neue',sans-serif;font-size:1rem;letter-spacing:3px;background:transparent;border:1px solid var(--border);color:var(--muted);padding:12px 20px;cursor:pointer;transition:all .2s}
.modal-cancel:hover{border-color:var(--red);color:var(--red)}
.modal-msg{font-size:.68rem;letter-spacing:1px;margin-top:12px;display:none}
.modal-msg.ok{color:var(--green)}
.modal-msg.err{color:var(--red)}

footer{position:relative;z-index:5;text-align:center;padding:32px;border-top:1px solid var(--border);font-size:.6rem;color:var(--muted);letter-spacing:2px}
footer span{color:var(--red)}
@media(max-width:768px){header{padding:16px 20px;flex-wrap:wrap;gap:12px}.profile-card{grid-template-columns:auto 1fr}.rank-display{grid-column:span 2;text-align:left}.match-row{grid-template-columns:70px 1fr 80px}.match-partner,.match-kda{display:none}}
</style>
</head>
<body>

<header>
  <a href="<?=SITE_URL?>/" style="text-decoration:none">
    <div class="logo">DUAL<span>//</span>2V2</div>
  </a>
  <div class="nav-user">
    <?php if ($currentUser): ?>
      <span class="nav-username"><?=e($currentUser['username'])?></span>
      <a class="nav-btn outline" href="<?=SITE_URL?>/dashboard.php">DASHBOARD</a>
      <?php if ($currentUser['is_admin']): ?>
        <a class="nav-btn outline" href="<?=SITE_URL?>/admin/" style="border-color:var(--red);color:var(--red)">ADMIN</a>
      <?php endif; ?>
      <a class="nav-btn outline" href="<?=SITE_URL?>/auth/handler.php?action=logout"
         onclick="fetch('<?=SITE_URL?>/auth/handler.php',{method:'POST',body:new URLSearchParams({action:'logout'})}).then(()=>location.reload());return false">LOGOUT</a>
    <?php else: ?>
      <a class="nav-btn" href="<?=SITE_URL?>/login.php?next=<?=urlencode($_SERVER['REQUEST_URI'])?>">LOGIN / SIGN UP</a>
    <?php endif; ?>
  </div>
</header>

<main>

  <!-- Profile Card -->
  <div class="profile-card">
    <div class="player-avatar">
      <?php if ($player['card_url']): ?>
        <img src="<?=e($player['card_url'])?>" alt="card"/>
      <?php endif; ?>
    </div>
    <div class="player-info">
      <?php if ($player['nickname'] || $player['country_flag']): ?>
        <div class="player-nickname">
          <?=e(trim(($player['country_flag'] ? $player['country_flag'].' ' : '') . ($player['nickname'] ?? '')))?>
        </div>
      <?php endif; ?>
      <h2><?=e($player['riot_name'])?></h2>
      <div class="tag">#<?=e($player['riot_tag'])?> · <?=e(strtoupper($player['region']))?></div>
      <div class="sync-time">
        <?=$player['last_sync'] ? '↻ Updated ' . htmlspecialchars(date('M j, Y H:i', strtotime($player['last_sync']))) : '↻ Never synced'?>
        <button class="sync-btn" id="sync-btn" onclick="syncProfile()">↻ SYNC</button>
      </div>
      <div style="margin-top:6px">
        <a class="vanity-copy" href="<?=SITE_URL?>/@<?=e($slug)?>">🔗 @<?=e($slug)?></a>
      </div>
    </div>
    <div class="rank-display">
      <div class="rank-icon"><?=$hideRank ? '🔒' : ($player['placement_done'] ? e($tier['icon']) : '⏳')?></div>
      <div class="rank-name" style="color:<?=$hideRank ? 'var(--muted)' : ($player['placement_done'] ? e($tier['color']) : 'var(--gold)')?>">
        <?=$hideRank ? 'HIDDEN' : ($player['placement_done'] ? e($tier['name']) : 'PLACEMENT')?>
      </div>
      <div class="rank-pts"><?=$hideRank ? '—' : e($player['total_cp']) . ' CP'?></div>
    </div>
  </div>

  <!-- Claim area -->
  <div class="claim-badge">
    <?php if ($claimState === 'approved'): ?>
      <span class="claim-status approved">✓ YOUR PROFILE</span>
      <button class="settings-btn" onclick="openModal()">⚙ SETTINGS</button>
    <?php elseif ($claimState === 'claimed_by_other' || (!empty($player['claimed_by']) && $claimState !== 'approved')): ?>
      <span class="claim-status other">🔒 CLAIMED</span>
    <?php elseif ($claimState === 'pending'): ?>
      <span class="claim-status pending">⏳ CLAIM REQUEST PENDING — Waiting for admin approval</span>
    <?php elseif ($claimState === 'denied'): ?>
      <span class="claim-status denied">✗ CLAIM DENIED</span>
    <?php elseif (!$currentUser): ?>
      <a class="claim-btn" href="<?=SITE_URL?>/login.php?next=<?=urlencode($_SERVER['REQUEST_URI'])?>">CLAIM THIS PROFILE</a>
    <?php else: ?>
      <button class="claim-btn" id="do-claim-btn" onclick="doClaim()">CLAIM THIS PROFILE</button>
    <?php endif; ?>
    <span id="claim-msg" style="font-size:.7rem;letter-spacing:1px;margin-left:12px;display:none"></span>
  </div>

  <!-- CP Bar -->
  <div class="cp-bar-wrap">
    <div class="cp-bar-label"><?=$player['placement_done'] ? e($tier['name']) : 'IN PLACEMENT'?></div>
    <div class="cp-bar-track">
      <div class="cp-bar-fill" style="width:<?=$cpPct?>%;background:<?=$player['placement_done'] ? e($tier['color']) : 'var(--gold)'?>"></div>
    </div>
    <div class="cp-bar-pct" style="color:<?=$player['placement_done'] ? e($tier['color']) : 'var(--gold)'?>"><?=$hideRank ? '—' : $cpPct.'%'?></div>
    <div class="cp-bar-next">
      <?php if ($player['placement_done']): ?>
        <?=$nextTier ? '→ ' . e($nextTier['name']) : '★ MAX RANK'?>
      <?php else: ?>
        <?=max(0, 5 - count($matches))?> MORE MATCHES
      <?php endif; ?>
    </div>
  </div>

  <!-- Stats -->
  <?php if ($hideStats): ?>
    <div class="hidden-notice">🔒 THIS PLAYER HAS HIDDEN THEIR STATS AND MATCH HISTORY</div>
  <?php else: ?>
  <div class="stats-grid">
    <div class="stat-card"><div class="stat-label">2v2 Matches</div><div class="stat-value"><?=$totalMatches?></div></div>
    <div class="stat-card"><div class="stat-label">Win Rate</div><div class="stat-value green"><?=$winRate?>%</div></div>
    <div class="stat-card"><div class="stat-label">Avg KDA</div><div class="stat-value gold"><?=$avgKda?></div></div>
    <div class="stat-card"><div class="stat-label">Total CP</div><div class="stat-value"><?=$hideRank ? '—' : number_format((int)$player['total_cp'])?></div></div>
    <div class="stat-card"><div class="stat-label">Headshot%</div><div class="stat-value"><?=$hsPct?>%</div></div>
    <div class="stat-card"><div class="stat-label">Win Streak</div><div class="stat-value"><?=(int)$player['win_streak']?></div></div>
  </div>

  <div class="tabs">
    <button class="tab active" onclick="switchTab('matches',this)">MATCH HISTORY</button>
    <button class="tab" onclick="switchTab('partners',this)">TOP PARTNERS</button>
  </div>

  <!-- Match history -->
  <div class="tab-content active" id="tab-matches">
    <div class="section-title">2v2 Match History</div>
    <div class="match-list">
      <?php if (!$matches): ?>
        <div class="empty-notice">NO 2V2 MATCHES RECORDED YET</div>
      <?php else: foreach ($matches as $m):
        $won   = (int)$m['won'] === 1;
        $shots = (int)$m['headshots'] + (int)$m['bodyshots'] + (int)$m['legshots'];
        $hs    = $shots ? round((int)$m['headshots'] / $shots * 100) : 0;
        $cp    = (int)$m['cp_delta'];
        $date  = $m['played_at'] ? date('M j', strtotime($m['played_at'])) : '';
      ?>
        <div class="match-row">
          <div class="match-result <?=$won?'win':'loss'?>"><?=$won?'WIN':'LOSS'?></div>
          <div class="match-map"><?=e($m['map_name'] ?: '—')?><small><?=$date?></small></div>
          <div class="match-partner"><?=e($m['partner_name'] ?: '—')?><small>DUO PARTNER</small></div>
          <div class="match-score"><?=(int)$m['rounds_won']?>–<?=(int)$m['rounds_lost']?></div>
          <div class="match-kda"><?=(int)$m['kills']?>/<?=(int)$m['deaths']?>/<?=(int)$m['assists']?> · <?=$hs?>%HS</div>
          <div class="match-cp <?=$cp>=0?'pos':'neg'?>"><?=$cp>=0?'+'.$cp:$cp?> CP</div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- Top partners -->
  <div class="tab-content" id="tab-partners">
    <div class="section-title">Best Duo Partners</div>
    <div style="display:flex;flex-direction:column;gap:8px">
      <?php
      $pm = [];
      foreach ($matches as $m) {
          $n = $m['partner_name'] ?? '';
          if (!$n || $n === '—') continue;
          if (!isset($pm[$n])) $pm[$n] = ['name'=>$n,'games'=>0,'wins'=>0];
          $pm[$n]['games']++;
          if ((int)$m['won'] === 1) $pm[$n]['wins']++;
      }
      usort($pm, fn($a,$b) => $b['games'] - $a['games']);
      $pm = array_slice($pm, 0, 10);
      if (!$pm): ?>
        <div class="empty-notice">NO PARTNER DATA YET</div>
      <?php else: foreach ($pm as $i => $p2):
        $wr2 = round($p2['wins'] / $p2['games'] * 100);
      ?>
        <div class="match-row" style="grid-template-columns:40px 1fr 80px 80px 80px">
          <div style="font-family:'Bebas Neue',sans-serif;color:var(--muted);font-size:1.1rem"><?=$i+1?></div>
          <div class="match-map"><?=e($p2['name'])?></div>
          <div style="font-size:.7rem;color:var(--muted)"><?=$p2['games']?> games</div>
          <div style="font-size:.7rem;color:var(--green)"><?=$wr2?>% WR</div>
          <div style="font-family:'Bebas Neue',sans-serif;color:var(--gold)"><?=$p2['wins']?>W</div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
  <?php endif; ?>

</main>

<footer>
  DUAL // 2V2 RANK TRACKER &nbsp;|&nbsp; COMMUNITY PROJECT &nbsp;|&nbsp; NOT AFFILIATED WITH <span>RIOT GAMES</span>
</footer>

<!-- Settings Modal (only for profile owner) -->
<?php if ($claimState === 'approved'): ?>
<div class="modal-bg" id="settings-modal">
  <div class="modal">
    <h3>PROFILE SETTINGS</h3>
    <label>Nickname <span style="color:var(--muted);font-size:.55rem">(displayed above your Name#TAG)</span></label>
    <input type="text" id="set-nickname" value="<?=e($player['nickname'] ?? '')?>" maxlength="40"/>
    <label>Country Flag <span style="color:var(--muted);font-size:.55rem">(paste a flag emoji e.g. 🇸🇦)</span></label>
    <input type="text" id="set-flag" value="<?=e($player['country_flag'] ?? '')?>" maxlength="4"/>
    <div class="modal-toggle" style="margin-top:16px">
      <span>Hide my rank publicly</span>
      <label class="toggle"><input type="checkbox" id="set-hide-rank" <?=$player['hide_rank']?'checked':''?>>
        <span class="toggle-track"></span></label>
    </div>
    <div class="modal-toggle">
      <span>Hide my stats publicly</span>
      <label class="toggle"><input type="checkbox" id="set-hide-stats" <?=$player['hide_stats']?'checked':''?>>
        <span class="toggle-track"></span></label>
    </div>
    <div class="modal-msg" id="settings-msg"></div>
    <div class="modal-btns">
      <button class="modal-cancel" onclick="closeModal()">CANCEL</button>
      <button class="modal-save" onclick="saveSettings()">SAVE CHANGES</button>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
const SITE    = <?=json_encode(SITE_URL)?>;
const API     = SITE + '/api/?action=';
const PLAYER  = <?=json_encode(['name'=>$player['riot_name'],'tag'=>$player['riot_tag'],'region'=>$player['region']])?>;
const PLAYER_ID = <?=(int)$player['id']?>;

function switchTab(name, btn) {
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
  if (btn) btn.classList.add('active');
  const el = document.getElementById('tab-' + name);
  if (el) el.classList.add('active');
}

async function syncProfile() {
  const btn = document.getElementById('sync-btn');
  btn.disabled = true; btn.textContent = '↻ SYNCING...';
  try {
    const url = `${API}search&name=${encodeURIComponent(PLAYER.name)}&tag=${encodeURIComponent(PLAYER.tag)}&region=${encodeURIComponent(PLAYER.region)}`;
    const res  = await fetch(url);
    const data = await res.json();
    if (data.ok) {
      location.reload();
    } else {
      btn.disabled = false; btn.textContent = '↻ SYNC';
      alert(data.error || 'Sync failed');
    }
  } catch(e) {
    btn.disabled = false; btn.textContent = '↻ SYNC';
  }
}

async function doClaim() {
  const btn = document.getElementById('do-claim-btn');
  const msg = document.getElementById('claim-msg');
  if (!btn) return;
  btn.disabled = true; btn.textContent = 'SENDING...';
  try {
    const res  = await fetch(`${API}claim&player_id=${PLAYER_ID}`);
    const data = await res.json();
    if (data.ok) {
      location.reload();
    } else {
      btn.disabled = false; btn.textContent = 'CLAIM THIS PROFILE';
      msg.textContent = '⚠ ' + (data.error || 'Could not send claim request');
      msg.style.color = 'var(--red)'; msg.style.display = 'inline';
    }
  } catch(e) {
    btn.disabled = false; btn.textContent = 'CLAIM THIS PROFILE';
  }
}

<?php if ($claimState === 'approved'): ?>
function openModal() { document.getElementById('settings-modal').classList.add('open'); }
function closeModal() { document.getElementById('settings-modal').classList.remove('open'); }

async function saveSettings() {
  const msg = document.getElementById('settings-msg');
  msg.style.display = 'none';
  const fd = new FormData();
  fd.append('nickname',   document.getElementById('set-nickname').value.trim());
  fd.append('country_flag', document.getElementById('set-flag').value.trim());
  fd.append('hide_rank',  document.getElementById('set-hide-rank').checked  ? '1' : '0');
  fd.append('hide_stats', document.getElementById('set-hide-stats').checked ? '1' : '0');
  try {
    const res  = await fetch(`${API}profile_update`, {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      msg.textContent = '✓ Saved!'; msg.className = 'modal-msg ok'; msg.style.display = 'block';
      setTimeout(() => location.reload(), 800);
    } else {
      msg.textContent = '⚠ ' + (data.error || 'Save failed'); msg.className = 'modal-msg err'; msg.style.display = 'block';
    }
  } catch(e) {
    msg.textContent = '⚠ Request failed'; msg.className = 'modal-msg err'; msg.style.display = 'block';
  }
}
document.getElementById('settings-modal').addEventListener('click', e => { if (e.target === e.currentTarget) closeModal(); });
<?php endif; ?>
</script>
</body>
</html>
