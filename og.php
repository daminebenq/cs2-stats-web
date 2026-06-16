<?php
// MUS SOU MANO — dynamic per-player Open Graph image. /og.php?id=<steamid64>
// Renders a 1200x630 PNG (name + rank + stats) for rich social link previews.
// Cached to og_cache/<id>.png for a short TTL so crawler hits are cheap.
declare(strict_types=1);

$cfg = require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';

const OG_W = 1200, OG_H = 630, OG_TTL = 600;

$id = isset($_GET['id']) ? trim((string)$_GET['id']) : '';

// Invalid id → fall back to the static site card.
if (!ctype_digit($id)) {
    serve_static_fallback();
}

$cacheDir  = __DIR__ . '/og_cache';
$cacheFile = $cacheDir . '/' . $id . '.png';
if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < OG_TTL) {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=' . OG_TTL);
    readfile($cacheFile);
    exit;
}

// GD is required; if missing, fall back gracefully.
if (!function_exists('imagecreatetruecolor') || !function_exists('imagettftext')) {
    serve_static_fallback();
}

// ---- pull player data ----
$rankRow = $statRow = null;
$placement = null;
$totalPlayers = 0;
try {
    $pdo = db($cfg);
    $r = $pdo->prepare("SELECT * FROM k4ranks WHERE steam_id = :id");
    $r->execute([':id' => $id]);
    $rankRow = $r->fetch() ?: null;
    $s = $pdo->prepare("SELECT * FROM k4stats WHERE steam_id = :id");
    $s->execute([':id' => $id]);
    $statRow = $s->fetch() ?: null;
    $totalPlayers = (int)$pdo->query("SELECT COUNT(*) FROM k4ranks")->fetchColumn();
    if ($rankRow) {
        $pl = $pdo->prepare("SELECT COUNT(*) + 1 FROM k4ranks WHERE points > :p");
        $pl->execute([':p' => (int)$rankRow['points']]);
        $placement = (int)$pl->fetchColumn();
    }
} catch (Throwable $e) {
    serve_static_fallback();
}

// Unknown player → static card.
if (!$rankRow && !$statRow) {
    serve_static_fallback();
}

$name   = (string)($rankRow['name'] ?? $statRow['name'] ?? 'Unknown');
$rank   = (string)($rankRow['rank'] ?? 'Unranked');
$points = (int)($rankRow['points'] ?? 0);
$kills  = (int)($statRow['kills'] ?? 0);
$deaths = (int)($statRow['deaths'] ?? 0);
$hs     = (int)($statRow['headshots'] ?? 0);
$kd     = $deaths > 0 ? $kills / $deaths : (float)$kills;
$hsp    = $kills > 0 ? $hs / $kills * 100 : 0.0;

// ---- render ----
$im = render_card($name, $rank, $points, $kills, $kd, $hsp, $placement, $totalPlayers);

@mkdir($cacheDir, 0775, true);
@imagepng($im, $cacheFile);
header('Content-Type: image/png');
header('Cache-Control: public, max-age=' . OG_TTL);
imagepng($im);
imagedestroy($im);
exit;


// ============================ helpers ============================

function serve_static_fallback(): void
{
    $f = __DIR__ . '/social.png';
    if (is_file($f)) {
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=' . OG_TTL);
        readfile($f);
    } else {
        http_response_code(404);
    }
    exit;
}

function og_font(bool $bold): string
{
    $cands = $bold ? [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
    ] : [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
        '/System/Library/Fonts/Supplemental/Arial.ttf',
    ];
    foreach ($cands as $f) {
        if (is_file($f)) return $f;
    }
    return $cands[0]; // GD will error visibly if truly absent
}

/** Largest font size (<= $max) whose text width fits within $maxW. */
function fit_size(string $font, string $text, int $max, int $maxW): int
{
    for ($sz = $max; $sz > 10; $sz -= 2) {
        $bb = imagettfbbox($sz, 0, $font, $text);
        if (($bb[2] - $bb[0]) <= $maxW) return $sz;
    }
    return 12;
}

function render_card(string $name, string $rank, int $points, int $kills, float $kd, float $hsp, ?int $place, int $total)
{
    $im = imagecreatetruecolor(OG_W, OG_H);
    imagealphablending($im, true);
    imagesavealpha($im, true);

    // palette (matches the website + scoreboard banner)
    $amber  = imagecolorallocate($im, 240, 165, 0);
    $orange = imagecolorallocate($im, 255, 107, 53);
    $text   = imagecolorallocate($im, 230, 237, 243);
    $muted  = imagecolorallocate($im, 139, 148, 158);
    $ink    = imagecolorallocate($im, 6, 16, 31);
    $panel  = imagecolorallocate($im, 28, 34, 42);

    // vertical gradient background (panel -> dark)
    for ($y = 0; $y < OG_H; $y++) {
        $t = $y / (OG_H - 1);
        $r = (int)round(22 + (13 - 22) * $t);
        $g = (int)round(27 + (17 - 27) * $t);
        $b = (int)round(34 + (23 - 34) * $t);
        $c = imagecolorallocate($im, $r, $g, $b);
        imageline($im, 0, $y, OG_W, $y, $c);
        imagecolordeallocate($im, $c);
    }

    // soft amber glow upper-left
    for ($i = 26; $i > 0; $i--) {
        $a = (int)round(120 - $i * 4);
        if ($a < 0) $a = 0;
        $gc = imagecolorallocatealpha($im, 240, 165, 0, 127 - (int)($a / 6));
        imagefilledellipse($im, 150, 120, $i * 22, $i * 22, $gc);
        imagecolordeallocate($im, $gc);
    }

    $fb = og_font(true);
    $fr = og_font(false);
    $pad = 72;

    // brand row (top)
    imagettftext($im, 26, 0, $pad, 86, $amber, $fb, 'MUS SOU MANO');
    imagettftext($im, 14, 0, $pad + 2, 116, $muted, $fr, 'CS2 COMMUNITY  ·  stats.damineweb.work');

    // amber divider
    imagefilledrectangle($im, $pad, 142, OG_W - $pad, 146, $amber);

    // player name (hero, auto-fit)
    $nameSize = fit_size($fb, $name, 92, OG_W - $pad * 2);
    imagettftext($im, $nameSize, 0, $pad, 300, $text, $fb, $name);

    // rank pill + placement
    $rankSize = 30;
    $rb = imagettfbbox($rankSize, 0, $fb, $rank);
    $rw = $rb[2] - $rb[0];
    $px1 = $pad; $py1 = 340; $pillH = 58; $pillPad = 26;
    rounded_rect($im, $px1, $py1, $px1 + $rw + $pillPad * 2, $py1 + $pillH, 12, $amber);
    imagettftext($im, $rankSize, 0, $px1 + $pillPad, $py1 + 40, $ink, $fb, $rank);
    $metaX = $px1 + $rw + $pillPad * 2 + 24;
    $placeTxt = $place ? ('#' . number_format($place) . ' of ' . number_format($total)) : 'Unranked';
    imagettftext($im, 24, 0, $metaX, $py1 + 38, $muted, $fr, $placeTxt);
    imagettftext($im, 20, 0, $metaX, $py1 + 4, $muted, $fr, number_format($points) . ' pts');

    // stat tiles
    $tiles = [
        ['KILLS', number_format($kills)],
        ['K / D', number_format($kd, 2)],
        ['HS %',  number_format($hsp, 1) . '%'],
        ['POINTS', number_format($points)],
    ];
    $tileY = 446; $tileH = 116; $gap = 22;
    $tileW = (int)((OG_W - $pad * 2 - $gap * 3) / 4);
    $tx = $pad;
    foreach ($tiles as $t) {
        rounded_rect($im, $tx, $tileY, $tx + $tileW, $tileY + $tileH, 14, $panel);
        imagefilledrectangle($im, $tx, $tileY, $tx + 6, $tileY + $tileH, $amber);
        $vs = fit_size($fb, $t[1], 44, $tileW - 40);
        imagettftext($im, $vs, 0, $tx + 26, $tileY + 62, $text, $fb, $t[1]);
        imagettftext($im, 16, 0, $tx + 26, $tileY + 96, $muted, $fr, $t[0]);
        $tx += $tileW + $gap;
    }

    // bottom accent bar (amber -> orange)
    for ($x = 0; $x < OG_W; $x++) {
        $t = $x / (OG_W - 1);
        $r = (int)round(240 + (255 - 240) * $t);
        $g = (int)round(165 + (107 - 165) * $t);
        $b = (int)round(0 + (53 - 0) * $t);
        $c = imagecolorallocate($im, $r, $g, $b);
        imageline($im, $x, OG_H - 8, $x, OG_H - 1, $c);
        imagecolordeallocate($im, $c);
    }

    return $im;
}

function rounded_rect($im, int $x1, int $y1, int $x2, int $y2, int $rad, int $color): void
{
    imagefilledrectangle($im, $x1 + $rad, $y1, $x2 - $rad, $y2, $color);
    imagefilledrectangle($im, $x1, $y1 + $rad, $x2, $y2 - $rad, $color);
    imagefilledellipse($im, $x1 + $rad, $y1 + $rad, $rad * 2, $rad * 2, $color);
    imagefilledellipse($im, $x2 - $rad, $y1 + $rad, $rad * 2, $rad * 2, $color);
    imagefilledellipse($im, $x1 + $rad, $y2 - $rad, $rad * 2, $rad * 2, $color);
    imagefilledellipse($im, $x2 - $rad, $y2 - $rad, $rad * 2, $rad * 2, $color);
}
