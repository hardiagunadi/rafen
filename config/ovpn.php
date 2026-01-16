<?php

return [
    'host' => env('OVPN_HOST', ''),
    'port' => env('OVPN_PORT', '1194'),
    'proto' => env('OVPN_PROTO', 'udp'),
    'username' => env('OVPN_USERNAME', ''),
    'password' => env('OVPN_PASSWORD', ''),
    'network' => env('OVPN_NETWORK', '10.8.0.0'),
    'netmask' => env('OVPN_NETMASK', '255.255.255.0'),
];
