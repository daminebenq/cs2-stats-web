<?php
// MUS SOU MANO — CS2 Fleet Stats. Leaderboard + live servers + stat leaders + recent.
declare(strict_types=1);

$cfg = require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';

$page    = isset($_GET['page']) && ctype_digit((string)$_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 25;
$offset  = ($page - 1) * $perPage;
$q       = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

$error = null;
$rows = [];
$total = 0;
$totals = ['players' => 0, 'kills' => 0, 'points' => 0];
$topFraggers = [];
$bestAim = [];
$recent = [];
$topCredits = [];
$vips = [];
$cheaters = [];

try {
    $pdo = db($cfg);

    $totals['players'] = (int)$pdo->query("SELECT COUNT(*) FROM k4ranks")->fetchColumn();
    $totals['points']  = (int)$pdo->query("SELECT COALESCE(SUM(points),0) FROM k4ranks")->fetchColumn();
    $totals['kills']   = (int)$pdo->query("SELECT COALESCE(SUM(kills),0) FROM k4stats")->fetchColumn();

    // Main leaderboard (rank points)
    if ($q !== '') {
        $c = $pdo->prepare("SELECT COUNT(*) FROM k4ranks WHERE name LIKE :q");
        $c->execute([':q' => "%{$q}%"]);
        $total = (int)$c->fetchColumn();
        $stmt = $pdo->prepare(
            "SELECT r.steam_id, r.name, r.rank, r.points, s.kills, s.deaths, s.headshots
             FROM k4ranks r LEFT JOIN k4stats s ON s.steam_id = r.steam_id
             WHERE r.name LIKE :q ORDER BY r.points DESC LIMIT :lim OFFSET :off");
        $stmt->bindValue(':q', "%{$q}%", PDO::PARAM_STR);
    } else {
        $total = $totals['players'];
        $stmt = $pdo->prepare(
            "SELECT r.steam_id, r.name, r.rank, r.points, s.kills, s.deaths, s.headshots
             FROM k4ranks r LEFT JOIN k4stats s ON s.steam_id = r.steam_id
             ORDER BY r.points DESC LIMIT :lim OFFSET :off");
    }
    $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    // Side panels (only on first page, no search)
    if ($q === '' && $page === 1) {
        $topFraggers = $pdo->query(
            "SELECT name, steam_id, kills FROM k4stats WHERE kills > 0 ORDER BY kills DESC LIMIT 5")->fetchAll();
        $bestAim = $pdo->query(
            "SELECT name, steam_id, ROUND(headshots / kills * 100, 1) AS hsp
             FROM k4stats WHERE kills >= 50 ORDER BY hsp DESC LIMIT 5")->fetchAll();
        $recent = $pdo->query(
            "SELECT name, steam_id, rank, points, lastseen FROM k4ranks ORDER BY lastseen DESC LIMIT 8")->fetchAll();

        // Top credits (separate store DB, optional)
        $sdb = store_db($cfg);
        if ($sdb) {
            try {
                $topCredits = $sdb->query(
                    "SELECT PlayerName AS name, SteamID AS steam_id, Credits, Vip
                     FROM store_players ORDER BY Credits DESC LIMIT 5")->fetchAll();
            } catch (Throwable $e) { $topCredits = []; }
            try {
                $vips = $sdb->query(
                    "SELECT PlayerName AS name, SteamID AS steam_id, Credits
                     FROM store_players WHERE Vip = 1 ORDER BY Credits DESC LIMIT 8")->fetchAll();
            } catch (Throwable $e) { $vips = []; }
            try {
                $cheaters = $sdb->query(
                    "SELECT steam_id, MAX(name) AS name,
                            GROUP_CONCAT(DISTINCT reason SEPARATOR ' \u00b7 ') AS reasons
                     FROM cheater_flags WHERE status = 'review'
                     GROUP BY steam_id ORDER BY MAX(flagged_at) DESC LIMIT 6")->fetchAll();
            } catch (Throwable $e) { $cheaters = []; }
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$totalPages = max(1, (int)ceil($total / $perPage));

// Live server status (A2S) — degrade gracefully if unreachable
$liveServers = [];
$liveHumans = 0;
foreach ($cfg['servers'] as $srv) {
    $info = a2s_info($cfg['public_ip'], (int)$srv['port'], 0.5);
    $liveServers[] = ['cfg' => $srv, 'info' => $info];
    if ($info) $liveHumans += (int)$info['humans'];
}

function ago(?string $dt): string {
    if (!$dt) return '';
    $t = strtotime($dt);
    if ($t === false) return '';
    $d = time() - $t;
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d / 60) . 'm ago';
    if ($d < 86400) return floor($d / 3600) . 'h ago';
    return floor($d / 86400) . 'd ago';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($cfg['site_title']) ?> — CS2 Stats</title>
<?php
$siteUrl = rtrim($cfg['site_url'] ?? 'https://stats.damineweb.work', '/');
$ogImg   = $siteUrl . '/social.png';
$ogDesc  = 'Live rankings, stats & leaderboards for the MUS SOU MANO CS2 community fleet — Zombie, Multi-1v1 Arena, HS-Only Deathmatch, KZ & AWP. Connect and climb the ranks.';
?>
<meta name="description" content="<?= h($ogDesc) ?>">
<meta name="theme-color" content="#f0a500">
<meta property="og:type" content="website">
<meta property="og:site_name" content="MUS SOU MANO">
<meta property="og:title" content="MUS SOU MANO — CS2 Fleet Stats">
<meta property="og:description" content="<?= h($ogDesc) ?>">
<meta property="og:url" content="<?= h($siteUrl) ?>/">
<meta property="og:image" content="<?= h($ogImg) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="MUS SOU MANO — CS2 Fleet Stats">
<meta name="twitter:description" content="<?= h($ogDesc) ?>">
<meta name="twitter:image" content="<?= h($ogImg) ?>">
<link rel="stylesheet" href="style.css">
<link rel="icon" href="favicon.svg" type="image/svg+xml">
</head>
<body>
<header class="hero">
    <div class="brand">
        <img class="logo-img" src="avatar.jpg" alt="MUS SOU MANO" width="60" height="60">
        <div>
            <h1><?= h($cfg['site_title']) ?></h1>
            <p class="tagline"><?= $cfg['site_sub'] ?></p>
        </div>
    </div>
    <div class="totals">
        <div class="stat"><span class="num"><?= number_format($totals['players']) ?></span><span class="lbl">Ranked Players</span></div>
        <div class="stat"><span class="num live"><?= number_format($liveHumans) ?></span><span class="lbl">Online Now</span></div>
        <div class="stat"><span class="num"><?= number_format($totals['kills']) ?></span><span class="lbl">Total Kills</span></div>
        <div class="stat"><span class="num"><?= number_format($totals['points']) ?></span><span class="lbl">Total Points</span></div>
    </div>
</header>

<section class="servers">
    <?php foreach ($liveServers as $ls):
        $i = $ls['info']; $c = $ls['cfg'];
        $on = $i !== null;
        $connect = "steam://connect/{$cfg['public_ip']}:{$c['port']}";
    ?>
    <div class="srv <?= $on ? 'up' : 'down' ?>" data-port="<?= (int)$c['port'] ?>">
        <div class="srv-ic"><?= h($c['icon']) ?></div>
        <div class="srv-body">
            <div class="srv-name"><a href="server.php?port=<?= (int)$c['port'] ?>"><?= h($c['name']) ?></a></div>
            <div class="srv-meta">
                <?php if ($on): ?>
                    <span class="dot"></span><?= (int)$i['humans'] ?>/<?= (int)$i['max'] ?> players<?php if ((int)$i['bots'] > 0): ?> <span class="bots">+<?= (int)$i['bots'] ?> bots</span><?php endif; ?> &middot; <span class="map"><?= h($i['map']) ?></span>
                <?php else: ?>
                    <span class="dot off"></span>offline
                <?php endif; ?>
            </div>
        </div>
        <?php if ($on): ?><a class="join" href="<?= h($connect) ?>">Join</a><?php endif; ?>
    </div>
    <?php endforeach; ?>
</section>

<main>
<div class="layout">
    <div class="col-main">
        <form class="search" method="get" action="index.php">
            <input type="text" name="q" placeholder="Search player name&hellip;" value="<?= h($q) ?>" autocomplete="off">
            <button type="submit">Search</button>
            <?php if ($q !== ''): ?><a class="clear" href="index.php">Clear</a><?php endif; ?>
        </form>

        <?php if ($error !== null): ?>
            <div class="error">Database error. Check <code>config.php</code>.<br><small><?= h($error) ?></small></div>
        <?php elseif (empty($rows)): ?>
            <div class="empty">No ranked players yet. Jump on a server and start fragging to appear here!</div>
        <?php else: ?>
        <table class="board">
            <thead><tr>
                <th class="r">#</th><th>Player</th><th>Rank</th>
                <th class="n">Points</th><th class="n">Kills</th><th class="n">Deaths</th><th class="n">K/D</th><th class="n">HS%</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $i => $row):
                $place = $offset + $i + 1;
                $kills = (int)($row['kills'] ?? 0); $deaths = (int)($row['deaths'] ?? 0); $hs = (int)($row['headshots'] ?? 0);
                $kd = $deaths > 0 ? $kills / $deaths : (float)$kills;
                $hsp = $kills > 0 ? $hs / $kills * 100 : 0.0;
                $medal = $place === 1 ? 'gold' : ($place === 2 ? 'silver' : ($place === 3 ? 'bronze' : ''));
            ?>
                <tr>
                    <td class="r <?= $medal ?>"><?= $place ?></td>
                    <td class="player"><a href="player.php?id=<?= h((string)$row['steam_id']) ?>"><?= h($row['name']) ?></a></td>
                    <td><span class="ranktag"><?= h($row['rank']) ?></span></td>
                    <td class="n"><?= number_format((int)$row['points']) ?></td>
                    <td class="n"><?= number_format($kills) ?></td>
                    <td class="n"><?= number_format($deaths) ?></td>
                    <td class="n"><?= number_format($kd, 2) ?></td>
                    <td class="n"><?= number_format($hsp, 1) ?>%</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($totalPages > 1): $qs = $q !== '' ? '&q=' . urlencode($q) : ''; ?>
        <nav class="pager">
            <?php if ($page > 1) echo '<a href="?page=' . ($page - 1) . $qs . '">&laquo; Prev</a>'; ?>
            <span class="cur">Page <?= $page ?> / <?= $totalPages ?></span>
            <?php if ($page < $totalPages) echo '<a href="?page=' . ($page + 1) . $qs . '">Next &raquo;</a>'; ?>
        </nav>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if ($q === '' && $page === 1 && $error === null): ?>
    <aside class="col-side">
        <div class="panel">
            <h3>Top Fraggers</h3>
            <?php if (empty($topFraggers)): ?><p class="muted">No data yet.</p><?php else: ?>
            <ol class="mini">
                <?php foreach ($topFraggers as $p): ?>
                <li><a href="player.php?id=<?= h((string)$p['steam_id']) ?>"><?= h($p['name']) ?></a><span><?= number_format((int)$p['kills']) ?> K</span></li>
                <?php endforeach; ?>
            </ol>
            <?php endif; ?>
        </div>
        <?php if (!empty($cheaters)): ?>
        <div class="panel flagged">
            <h3>⚠ Under Review</h3>
            <ul class="mini">
                <?php foreach ($cheaters as $p): ?>
                <li><a href="player.php?id=<?= h((string)$p['steam_id']) ?>"><?= h($p['name'] ?: 'Unknown') ?></a><span class="flag"><?= h($p['reasons']) ?></span></li>
                <?php endforeach; ?>
            </ul>
            <p class="muted small">Auto-flagged by stat checks &middot; pending admin review.</p>
        </div>
        <?php endif; ?>
        <?php if (!empty($topCredits)): ?>
        <div class="panel">
            <h3>Richest Players</h3>
            <ol class="mini">
                <?php foreach ($topCredits as $p): ?>
                <li><a href="player.php?id=<?= h((string)$p['steam_id']) ?>"><?= h($p['name'] ?? 'Unknown') ?><?php if (!empty($p['Vip'])): ?> <span class="vip">VIP</span><?php endif; ?></a><span class="credits"><?= number_format((int)$p['Credits']) ?></span></li>
                <?php endforeach; ?>
            </ol>
        </div>
        <?php endif; ?>
        <?php if (!empty($vips)): ?>
        <div class="panel">
            <h3>VIP Members</h3>
            <ul class="mini">
                <?php foreach ($vips as $p): ?>
                <li><a href="player.php?id=<?= h((string)$p['steam_id']) ?>"><?= h($p['name'] ?? 'Unknown') ?> <span class="vip">VIP</span></a><span class="credits"><?= number_format((int)$p['Credits']) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        <div class="panel">
            <h3>Best Aim (HS%)</h3>
            <?php if (empty($bestAim)): ?><p class="muted">Need 50+ kills.</p><?php else: ?>
            <ol class="mini">
                <?php foreach ($bestAim as $p): ?>
                <li><a href="player.php?id=<?= h((string)$p['steam_id']) ?>"><?= h($p['name']) ?></a><span><?= number_format((float)$p['hsp'], 1) ?>%</span></li>
                <?php endforeach; ?>
            </ol>
            <?php endif; ?>
        </div>
        <div class="panel">
            <h3>Recently Active</h3>
            <?php if (empty($recent)): ?><p class="muted">No data yet.</p><?php else: ?>
            <ul class="mini recent">
                <?php foreach ($recent as $p): ?>
                <li><a href="player.php?id=<?= h((string)$p['steam_id']) ?>"><?= h($p['name']) ?></a><span><?= h(ago($p['lastseen'] ?? null)) ?></span></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </aside>
    <?php endif; ?>
</div>
</main>

<footer>
    <p>Connect: <code><?= h($cfg['public_ip']) ?></code> &middot; Zombie &middot; Multi 1v1 Arena &middot; HS Deathmatch &middot; KZ &middot; AWP</p>
    <p class="small">Type <code>!rank</code> / <code>!top</code> / <code>!store</code> in-game. Stats update live as you play.</p>
    <?php if (!empty($cfg['discord']) && $cfg['discord'] !== 'https://discord.gg/'): ?>
    <p><a class="discord" href="<?= h($cfg['discord']) ?>" target="_blank" rel="noopener">Join our Discord</a></p>
    <?php endif; ?>
</footer>
<script>
// Auto-refresh the live server bar every 30s without reloading the page.
(function () {
    var hero = document.querySelector('.num.live');
    function esc(s){ return String(s).replace(/[&<>"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
    function refresh() {
        fetch('api.php', { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (d) {
            if (hero && typeof d.online === 'number') hero.textContent = d.online.toLocaleString();
            (d.servers || []).forEach(function (s) {
                var el = document.querySelector('.srv[data-port="' + s.port + '"]');
                if (!el) return;
                el.classList.toggle('up', s.up);
                el.classList.toggle('down', !s.up);
                var meta = el.querySelector('.srv-meta');
                if (!meta) return;
                if (s.up) {
                    var bots = s.bots > 0 ? ' <span class="bots">+' + s.bots + ' bots</span>' : '';
                    meta.innerHTML = '<span class="dot"></span>' + s.humans + '/' + s.max + ' players' + bots + ' &middot; <span class="map">' + esc(s.map) + '</span>';
                } else {
                    meta.innerHTML = '<span class="dot off"></span>offline';
                }
            });
        }).catch(function () {});
    }
    setInterval(refresh, 30000);
})();
</script>
</body>
</html>
