<?php

return [
    'host' => env('OVPN_HOST', ''),
    'port' => env('OVPN_PORT', '1194'),
    'proto' => env('OVPN_PROTO', 'udp'),
    'username' => env('OVPN_USERNAME', ''),
    'password' => env('OVPN_PASSWORD', ''),
    'network' => env('OVPN_NETWORK', '10.8.0.0'),
    'netmask' => env('OVPN_NETMASK', '255.255.255.0'),
    'pool_start' => env('OVPN_POOL_START', '10.8.0.2'),
    'pool_end' => env('OVPN_POOL_END', '10.8.0.254'),
    'ccd_path' => env('OVPN_CCD_PATH', '/etc/openvpn/ccd'),
    'auth_users_path' => env('OVPN_AUTH_USERS_PATH', '/etc/openvpn/ovpn-users'),
    'route_dst' => env('OVPN_ROUTE_DST', ''),
];
