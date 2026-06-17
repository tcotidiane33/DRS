<?php

return [
    'host'         => env('PROXMOX_HOST'),
    'port'         => env('PROXMOX_PORT', 8006),
    'user'         => env('PROXMOX_USER'),
    'token_id'     => env('PROXMOX_TOKEN_ID'),
    'token_secret' => env('PROXMOX_TOKEN_SECRET'),
    'verify_ssl'   => env('PROXMOX_VERIFY_SSL', false),
];
