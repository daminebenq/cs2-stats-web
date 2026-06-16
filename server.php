<?php
// MUS SOU MANO — per-server page. /server.php?port=<port>
// Shows the live status of one server + every player seen on it, with their full stats.
declare(strict_types=1);

$cfg = require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';

$port = isset($_GET['port']) && ctype_digit((string)$_GET['port']) ? (int)$_GET['port'] : 0;

// Resolve the server from config (only allow configured ports).
$server = null;
foreach ($cfg['servers'] as $s) {
    if ((int)$s['port'] === $port) { $server = $s; break; }
}
if ($server === null) {
    http_response_code(404);
    $port = 0;
}

$error = null;
$players = [];
$live = null;

if ($server !== null) {
    // Live status via A2S
    $live = a2s_info($cfg['public_ip'], $port, 0.6);

    try {
        $pdo = db($cfg); // k4system connection; cross-references k4store.server_players
        $stmt = $pdo->prepare(
            "SELECT sp.steam_id, sp.name, sp.last_seen, sp.first_seen, sp.seen_count,
                    r.rank, r.points, s.kills, s.deaths, s.headshots
             FROM k4store.server_players sp
             LEFT JOIN k4ranks r ON r.steam_id = sp.steam_id
             LEFT JOIN k4stats s ON s.steam_id = sp.steam_id
             WHERE sp.server_port = :port
             ORDER BY sp.last_seen DESC
             LIMIT 200");
        $stmt->execute([':port' => $port]);
        $players = $stmt->fetchAll();
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

// Arena 1v1 ELO ladder (only on the Multi 1v1 Arena server).
$arena = [];
$isArena = ($server !== null && $port === 27018);
if ($isArena) {
    try {
        $arena = db($cfg)->query(
            "SELECT steamid64, name, rating, wins, losses, duels, streak, best_rating
             FROM k4store.arena_elo WHERE duels > 0 ORDER BY rating DESC LIMIT 100")->fetchAll();
    } catch (Throwable $e) { $arena = []; }
}

function ago2(?string $dt): string {
    if (!$dt) return '';
    $t = strtotime($dt);
    if ($t === false) return '';
    $d = time() - $t;
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d / 60) . 'm ago';
    if ($d < 86400) return floor($d / 3600) . 'h ago';
    return floor($d / 86400) . 'd ago';
}

$title = $server ? $server['name'] : 'Server';
$siteUrl = rtrim($cfg['site_url'] ?? 'https://stats.damineweb.work', '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> — <?= h($cfg['site_title']) ?></title>
<meta name="description" content="<?= h($title) ?> on MUS SOU MANO CS2 — live status, roster and player stats.">
<meta name="theme-color" content="#f0a500">
<meta property="og:title" content="<?= h($title) ?> — MUS SOU MANO CS2">
<meta property="og:description" content="Live status, roster and full player stats for the <?= h($title) ?> server.">
<meta property="og:image" content="<?= h($siteUrl) ?>/social.png">
<link rel="stylesheet" href="style.css">
<link rel="icon" href="favicon.svg" type="image/svg+xml">
</head>
<body>
<header class="hero compact">
    <div class="brand">
        <a class="logo-link" href="index.php"><img class="logo-img" src="avatar.jpg" alt="MUS SOU MANO" width="54" height="54"></a>
        <div>
            <h1><?= h($title) ?></h1>
            <p class="tagline">
                <?php if ($server): ?>
                    <span class="ranktag"><?= h($server['icon']) ?></span> &middot;
                    <?php if ($live): ?>
                        <span class="dot"></span><?= (int)$live['humans'] ?>/<?= (int)$live['max'] ?> online<?php if ((int)$live['bots'] > 0): ?> &middot; <?= (int)$live['bots'] ?> bots<?php endif; ?> &middot; <span class="map"><?= h($live['map']) ?></span>
                    <?php else: ?>
                        <span class="dot off"></span>offline
                    <?php endif; ?>
                <?php else: ?>Unknown server<?php endif; ?>
            </p>
        </div>
    </div>
    <?php if ($server && $live): ?>
    <div class="totals">
        <a class="join big" href="steam://connect/<?= h($cfg['public_ip']) ?>:<?= $port ?>">Join Server</a>
    </div>
    <?php endif; ?>
</header>

<main>
<div class="back"><a href="index.php">&laquo; Back to leaderboard</a></div>

<?php if ($server === null): ?>
    <div class="empty">Unknown server. <a href="index.php">Return to the leaderboard.</a></div>
<?php elseif ($error !== null): ?>
    <div class="error">Could not load roster.<br><small><?= h($error) ?></small></div>
<?php else: ?>
    <?php if ($isArena): ?>
    <section class="arena-ladder">
        <h2 class="sec-title">⚔ Arena Ladder <span class="sub">CS:GO-style 1v1 ELO &middot; everyone starts at 1500</span></h2>        <p class="ladder-note">Multiple 1v1 duels run at once in separate arenas. <strong>Win your duel and you climb an arena; lose and you drop down</strong> &mdash; so you always face someone near your skill. Every kill exchanges ELO with your opponent: beat a higher-rated player to gain more, and reach the top to be crowned <strong>Arena King</strong>.</p>        <?php if (empty($arena)): ?>
            <div class="empty">No ranked duels yet. Win a 1v1 here and you'll appear on the ladder! Type <code>!elo</code> / <code>!arenatop</code> in-game.</div>
        <?php else: ?>
        <table class="board">
            <thead><tr>
                <th class="r">#</th><th>Player</th>
                <th class="n">Rating</th><th class="n">W</th><th class="n">L</th><th class="n">Win%</th><th class="n">Duels</th><th class="n">Streak</th><th class="n">Best</th>
            </tr></thead>
            <tbody>
            <?php foreach ($arena as $i => $a):
                $place = $i + 1;
                $w = (int)$a['wins']; $l = (int)$a['losses']; $d = (int)$a['duels'];
                $winp = $d > 0 ? $w / $d * 100 : 0.0;
                $stk = (int)$a['streak'];
                $medal = $place === 1 ? 'gold' : ($place === 2 ? 'silver' : ($place === 3 ? 'bronze' : ''));
            ?>
                <tr>
                    <td class="r <?= $medal ?>"><?= $place ?></td>
                    <td class="player"><a href="player.php?id=<?= h((string)$a['steamid64']) ?>"><?= h($a['name'] ?: 'Unknown') ?></a></td>
                    <td class="n elshowcase"><?= number_format((int)$a['rating']) ?></td>
                    <td class="n"><?= number_format($w) ?></td>
                    <td class="n"><?= number_format($l) ?></td>
                    <td class="n"><?= number_format($winp, 0) ?>%</td>
                    <td class="n"><?= number_format($d) ?></td>
                    <td class="n"><?php if ($stk > 0): ?><span class="wstreak">W<?= $stk ?></span><?php elseif ($stk < 0): ?><span class="lstreak">L<?= -$stk ?></span><?php else: ?>&mdash;<?php endif; ?></td>
                    <td class="n muted"><?= number_format((int)$a['best_rating']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>
    <?php endif; ?>
    <p class="muted" style="margin:0 0 14px">
        Players who have played on <strong><?= h($title) ?></strong>
        (<?= count($players) ?> tracked). Stats shown are fleet-wide totals; presence is per-server.
    </p>
    <?php if (empty($players)): ?>
        <div class="empty">No players tracked on this server yet. As people play here, they'll appear with their stats. Jump in: <code><?= h($cfg['public_ip']) ?>:<?= $port ?></code></div>
    <?php else: ?>
    <table class="board">
        <thead><tr>
            <th>Player</th><th>Rank</th>
            <th class="n">Points</th><th class="n">Kills</th><th class="n">K/D</th><th class="n">HS%</th>
            <th class="n">Seen</th><th class="n">Last here</th>
        </tr></thead>
        <tbody>
        <?php foreach ($players as $row):
            $kills = (int)($row['kills'] ?? 0); $deaths = (int)($row['deaths'] ?? 0); $hs = (int)($row['headshots'] ?? 0);
            $kd = $deaths > 0 ? $kills / $deaths : (float)$kills;
            $hsp = $kills > 0 ? $hs / $kills * 100 : 0.0;
        ?>
            <tr>
                <td class="player"><a href="player.php?id=<?= h((string)$row['steam_id']) ?>"><?= h($row['name'] ?: 'Unknown') ?></a></td>
                <td><?php if (!empty($row['rank'])): ?><span class="ranktag"><?= h($row['rank']) ?></span><?php else: ?><span class="muted">—</span><?php endif; ?></td>
                <td class="n"><?= number_format((int)($row['points'] ?? 0)) ?></td>
                <td class="n"><?= number_format($kills) ?></td>
                <td class="n"><?= number_format($kd, 2) ?></td>
                <td class="n"><?= number_format($hsp, 1) ?>%</td>
                <td class="n"><?= number_format((int)$row['seen_count']) ?></td>
                <td class="n"><?= h(ago2($row['last_seen'] ?? null)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
<?php endif; ?>
</main>

<footer>
    <p>Other servers:
        <?php foreach ($cfg['servers'] as $s): ?>
            <a class="srvlink" href="server.php?port=<?= (int)$s['port'] ?>"><?= h($s['icon']) ?></a>
        <?php endforeach; ?>
    </p>
    <p class="small">Connect: <code><?= h($cfg['public_ip']) ?></code> &middot; Type <code>!rank</code> / <code>!web</code> in-game.</p>
</footer>
</body>
</html>
