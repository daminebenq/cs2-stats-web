<?php
// MUS SOU MANO stats — shared helpers (DB + Source A2S_INFO live server query).
declare(strict_types=1);

function db(array $cfg): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset=utf8mb4";
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// Second connection for the store/credits database (k4store).
function store_db(array $cfg): ?PDO {
    static $pdo = null;
    static $tried = false;
    if ($tried) return $pdo;
    $tried = true;
    if (empty($cfg['store_database'])) return null;
    try {
        $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['store_database']};charset=utf8mb4";
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (Throwable $e) {
        $pdo = null;
    }
    return $pdo;
}

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function steam_profile(string $id): string {
    return ctype_digit($id) ? "https://steamcommunity.com/profiles/{$id}" : '#';
}

/**
 * Query a Source server via A2S_INFO (UDP). Returns null on timeout/failure.
 * Handles the S2C_CHALLENGE (0x41) handshake required by modern CS2 servers.
 */
function a2s_info(string $ip, int $port, float $timeout = 0.6): ?array {
    $errno = 0; $errstr = '';
    $fp = @fsockopen("udp://{$ip}", $port, $errno, $errstr, $timeout);
    if (!$fp) return null;
    stream_set_timeout($fp, 0, (int)($timeout * 1_000_000));

    $payload = "\xFF\xFF\xFF\xFFTSource Engine Query\x00";
    @fwrite($fp, $payload);
    $resp = @fread($fp, 4096);
    if ($resp === false || strlen($resp) < 5) { fclose($fp); return null; }

    // Challenge response -> resend with challenge appended
    if ($resp[4] === "\x41") {
        $challenge = substr($resp, 5, 4);
        @fwrite($fp, $payload . $challenge);
        $resp = @fread($fp, 4096);
        if ($resp === false || strlen($resp) < 5) { fclose($fp); return null; }
    }
    fclose($fp);

    if ($resp[4] !== "\x49") return null; // not an A2S_INFO reply
    $p = 5;
    $p++; // protocol byte
    $read_str = function (string $s, int &$i): string {
        $out = '';
        while ($i < strlen($s) && $s[$i] !== "\x00") { $out .= $s[$i]; $i++; }
        $i++;
        return $out;
    };
    $name = $read_str($resp, $p);
    $map  = $read_str($resp, $p);
    $read_str($resp, $p); // folder
    $read_str($resp, $p); // game
    $p += 2; // app id (short)
    $players = isset($resp[$p]) ? ord($resp[$p]) : 0; $p++;
    $maxp    = isset($resp[$p]) ? ord($resp[$p]) : 0; $p++;
    $bots    = isset($resp[$p]) ? ord($resp[$p]) : 0; $p++;

    return [
        'name'    => $name,
        'map'     => $map,
        'players' => $players,
        'bots'    => $bots,
        'max'     => $maxp,
        'humans'  => max(0, $players - $bots),
    ];
}
