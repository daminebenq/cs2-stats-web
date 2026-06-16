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
- **Branding** — MSM logo + favicon, optional Discord button.

## Files

| File          | Purpose                                            |
|---------------|----------------------------------------------------|
| `index.php`   | Leaderboard + live servers + side panels           |
| `player.php`  | Per-player detail page                             |
| `api.php`     | JSON live-server endpoint (for auto-refresh)       |
| `lib.php`     | DB connections + `a2s_info()` Source query         |
| `config.php`  | DB credentials + server list + branding (template) |
| `style.css`   | Styling (dark theme)                               |
| `favicon.svg` | MSM monogram favicon                               |

## Deploy

1. Copy the files to the web root (e.g. `/var/www/cs2stats`).
2. Replace `__DB_PASSWORD__` in `config.php` with a **read-only** DB user's password.
3. Point nginx + php-fpm at the web root; deny `config.php` and `lib.php`.

The website only needs `SELECT` on the `k4system` and `k4store` databases.

## Security

- Use a dedicated read-only MySQL user (`SELECT` only).
- `config.php` and `lib.php` are denied at the web server (return 404).
- All DB access uses prepared statements; all output is HTML-escaped.
