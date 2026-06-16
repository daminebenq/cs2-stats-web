<?php
// MUS SOU MANO — per-player detail page. /player.php?id=<steamid64>
declare(strict_types=1);

$cfg = require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';

$id = isset($_GET['id']) ? trim((string)$_GET['id']) : '';
if (!ctype_digit($id)) {
    http_response_code(400);
    $id = '';
}

$error = null;
$rankRow = null;
$statRow = null;
$timeRow = null;
$placement = null;
$totalPlayers = 0;

if ($id !== '') {
    try {
        $pdo = db($cfg);

        $r = $pdo->prepare("SELECT * FROM k4ranks WHERE steam_id = :id");
        $r->execute([':id' => $id]);
        $rankRow = $r->fetch() ?: null;

        $s = $pdo->prepare("SELECT * FROM k4stats WHERE steam_id = :id");
        $s->execute([':id' => $id]);
        $statRow = $s->fetch() ?: null;

        // k4times may not exist on all schemas; guard it
        try {
            $t = $pdo->prepare("SELECT * FROM k4times WHERE steam_id = :id");
            $t->execute([':id' => $id]);
            $timeRow = $t->fetch() ?: null;
        } catch (Throwable $e) { $timeRow = null; }

        $totalPlayers = (int)$pdo->query("SELECT COUNT(*) FROM k4ranks")->fetchColumn();
        if ($rankRow) {
            $pl = $pdo->prepare("SELECT COUNT(*) + 1 FROM k4ranks WHERE points > :p");
            $pl->execute([':p' => (int)$rankRow['points']]);
            $placement = (int)$pl->fetchColumn();
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

function fmt_secs(int $s): string {
    if ($s <= 0) return '0m';
    $h = intdiv($s, 3600);
    $m = intdiv($s % 3600, 60);
    if ($h > 0) return "{$h}h {$m}m";
    return "{$m}m";
}

$kills   = (int)($statRow['kills'] ?? 0);
$deaths  = (int)($statRow['deaths'] ?? 0);
$assists = (int)($statRow['assists'] ?? 0);
$hs      = (int)($statRow['headshots'] ?? 0);
$shoots  = (int)($statRow['shoots'] ?? 0);
$hitGiven= (int)($statRow['hits_given'] ?? 0);
$kd      = $deaths > 0 ? $kills / $deaths : (float)$kills;
$hsp     = $kills > 0 ? $hs / $kills * 100 : 0.0;
$acc     = $shoots > 0 ? $hitGiven / $shoots * 100 : 0.0;
$name    = $rankRow['name'] ?? ($statRow['name'] ?? 'Unknown');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($name) ?> — <?= h($cfg['site_title']) ?></title>
<link rel="stylesheet" href="style.css">
<link rel="icon" href="favicon.svg" type="image/svg+xml">
</head>
<body>
<header class="hero compact">
    <div class="brand">
        <a class="logo" href="index.php">MSM</a>
        <div>
            <h1><?= h($name) ?></h1>
            <p class="tagline">
                <?php if ($rankRow): ?><span class="ranktag"><?= h($rankRow['rank']) ?></span> &middot; <?= number_format((int)$rankRow['points']) ?> points<?php else: ?>Player profile<?php endif; ?>
            </p>
        </div>
    </div>
</header>

<main>
<div class="back"><a href="index.php">&laquo; Back to leaderboard</a></div>

<?php if ($error !== null): ?>
    <div class="error">Database error.<br><small><?= h($error) ?></small></div>
<?php elseif ($id === ''): ?>
    <div class="empty">No player selected.</div>
<?php elseif (!$rankRow && !$statRow): ?>
    <div class="empty">Player not found. They may not have played yet.</div>
<?php else: ?>

<div class="cards">
    <div class="card hi">
        <span class="card-lbl">Rank</span>
        <span class="card-val"><?= $rankRow ? h($rankRow['rank']) : '—' ?></span>
        <?php if ($placement): ?><span class="card-sub">#<?= $placement ?> of <?= number_format($totalPlayers) ?></span><?php endif; ?>
    </div>
    <div class="card"><span class="card-lbl">Points</span><span class="card-val"><?= $rankRow ? number_format((int)$rankRow['points']) : '0' ?></span></div>
    <div class="card"><span class="card-lbl">Kills</span><span class="card-val"><?= number_format($kills) ?></span></div>
    <div class="card"><span class="card-lbl">Deaths</span><span class="card-val"><?= number_format($deaths) ?></span></div>
    <div class="card"><span class="card-lbl">K/D</span><span class="card-val"><?= number_format($kd, 2) ?></span></div>
    <div class="card"><span class="card-lbl">Headshot %</span><span class="card-val"><?= number_format($hsp, 1) ?>%</span></div>
    <div class="card"><span class="card-lbl">Assists</span><span class="card-val"><?= number_format($assists) ?></span></div>
    <div class="card"><span class="card-lbl">Accuracy</span><span class="card-val"><?= number_format($acc, 1) ?>%</span></div>
</div>

<?php if ($statRow): ?>
<div class="detail-grid">
    <div class="panel">
        <h3>Combat</h3>
        <table class="kv">
            <tr><td>First bloods</td><td><?= number_format((int)($statRow['firstblood'] ?? 0)) ?></td></tr>
            <tr><td>Headshots</td><td><?= number_format($hs) ?></td></tr>
            <tr><td>Shots fired</td><td><?= number_format($shoots) ?></td></tr>
            <tr><td>Shots hit</td><td><?= number_format($hitGiven) ?></td></tr>
            <tr><td>Hits taken</td><td><?= number_format((int)($statRow['hits_taken'] ?? 0)) ?></td></tr>
        </table>
    </div>
    <?php if ($timeRow): ?>
    <div class="panel">
        <h3>Playtime</h3>
        <table class="kv">
            <?php
            $known = ['all' => 'Total', 'ct' => 'CT side', 't' => 'T side', 'spec' => 'Spectator', 'alive' => 'Alive', 'dead' => 'Dead'];
            foreach ($known as $col => $label):
                if (isset($timeRow[$col])):
            ?>
            <tr><td><?= h($label) ?></td><td><?= h(fmt_secs((int)$timeRow[$col])) ?></td></tr>
            <?php endif; endforeach; ?>
        </table>
    </div>
    <?php endif; ?>
    <div class="panel">
        <h3>Profile</h3>
        <table class="kv">
            <tr><td>Last seen</td><td><?= h($rankRow['lastseen'] ?? ($statRow['lastseen'] ?? '—')) ?></td></tr>
            <tr><td>Steam</td><td><a href="<?= h(steam_profile($id)) ?>" target="_blank" rel="noopener">View profile &rarr;</a></td></tr>
        </table>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>
</main>

<footer>
    <p>Connect: <code><?= h($cfg['public_ip']) ?></code></p>
    <p class="small">Type <code>!rank</code> / <code>!top</code> in-game.</p>
</footer>
</body>
</html>
