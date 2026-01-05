<?php

return [
    'clients_path' => env('RADIUS_CLIENTS_PATH', '/etc/freeradius/clients.d/laravel.conf'),
    'reload_command' => env('RADIUS_RELOAD_COMMAND', 'systemctl reload freeradius'),
    'restart_command' => env('RADIUS_RESTART_COMMAND', 'systemctl restart freeradius'),
    'server_ip' => env('RADIUS_SERVER_IP', '127.0.0.1'),
];
