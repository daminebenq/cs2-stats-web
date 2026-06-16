<?php
// MUS SOU MANO stats — config (DB + server list + branding).
// The real password is injected at deploy time (kept out of git).
return [
    'host'       => '127.0.0.1',
    'port'       => 3306,
    'database'   => 'k4system',
    'store_database' => 'k4store',
    'user'       => 'k4arenas',
    'pass'       => '__DB_PASSWORD__',
    'site_title' => 'MUS SOU MANO',
    'site_sub'   => 'CS2 Community &middot; Live Rankings',
    'public_ip'  => '__PUBLIC_IP__',
    // Canonical public URL (used for social link previews / og:image)
    'site_url'   => 'https://stats.damineweb.work',
    // Optional Discord invite (leave '' to hide the button)
    'discord'    => 'https://discord.gg/',
    'servers'    => [
        ['name' => 'Zombie Escape',     'port' => 27016, 'icon' => 'ZE'],
        ['name' => 'Multi 1v1 Arena',   'port' => 27018, 'icon' => '1v1'],
        ['name' => 'HS-Only Deathmatch','port' => 27020, 'icon' => 'DM'],
        ['name' => 'KZ Climb',          'port' => 27021, 'icon' => 'KZ'],
        ['name' => 'AWP Only',          'port' => 27025, 'icon' => 'AWP'],
    ],
];
