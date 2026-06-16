# MUS SOU MANO — CS2 Stats Website

A self-contained PHP stats site for the MUS SOU MANO Counter-Strike 2 fleet.
Reads the [K4-System](https://github.com/KitsuneLab-Development/K4-System) and
[cs2-store](https://github.com/schwarper/cs2-store) MySQL databases and shows a
live leaderboard, per-player profiles, a credits leaderboard, and real-time
server status.

Live: https://stats.damineweb.work

## Features

- **Leaderboard** — ranked by points, with K/D, HS%, search and pagination.
- **Live server bar** — queries each server via Source `A2S_INFO` (UDP, with the
  CS2 challenge handshake), shows players/map and a `steam://connect` Join button.
  Auto-refreshes every 30s via `api.php` (no page reload).
- **Side panels** — Top Fraggers, Richest Players (store credits), Best Aim (HS%),
  Recently Active.
- **Player profiles** — `player.php?id=<steamid64>`: rank, placement, K/D, HS%,
  accuracy, combat and playtime breakdowns.
- **VIP panel** — lists current VIP members (store flag) alongside the credits board.
- **Social link previews** — Open Graph + Twitter Card tags on every page. The
  home page uses a 1200×630 brand card (`social.png`); player pages generate a
  **dynamic** card per player via `og.php?id=<steamid64>` (name, rank, K/D, HS%,
  placement) so a shared profile link unfurls with that player's live stats.
- **Branding** — circular Steam-avatar logo, favicon, optional Discord button,
  and a matching in-game scoreboard banner (see `banner/`).

## Files

| File          | Purpose                                            |
|---------------|----------------------------------------------------|
| `index.php`   | Leaderboard + live servers + side panels           |
| `player.php`  | Per-player detail page                             |
| `og.php`      | Dynamic per-player 1200×630 OG image (PHP GD)      |
| `api.php`     | JSON live-server endpoint (for auto-refresh)       |
| `lib.php`     | DB connections + `a2s_info()` Source query         |
| `config.php`  | DB credentials + server list + branding (template) |
| `style.css`   | Styling (dark theme)                               |
| `favicon.svg` | MSM monogram favicon                               |
| `social.png`  | Static site-level social card                      |
| `avatar.jpg`  | Steam-avatar logo                                  |
| `banner/`     | Pillow generators for the scoreboard + social art  |

## Deploy

1. Copy the files to the web root (e.g. `/var/www/cs2stats`).
2. In `config.php`, replace the placeholders: `__DB_PASSWORD__` with a
   **read-only** DB user's password, and `__PUBLIC_IP__` with the public
   server IP. (These are kept out of git and injected at deploy time.)
3. Point nginx + php-fpm at the web root; deny `config.php` and `lib.php`.
4. Ensure `php-gd` is installed (for `og.php`) and `og_cache/` is writable
   by the web user.

The website only needs `SELECT` on the `k4system` and `k4store` databases.

## Security

- Use a dedicated read-only MySQL user (`SELECT` only).
- `config.php` and `lib.php` are denied at the web server (return 404).
- All DB access uses prepared statements; all output is HTML-escaped.
- No secrets, keys, or real IPs are committed — `config.php` ships with
  `__DB_PASSWORD__` / `__PUBLIC_IP__` placeholders only.

## License

[MIT](LICENSE) © daminebenq (MUS SOU MANO)
