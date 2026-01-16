<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class OvpnSettingsController extends Controller
{
    public function index(): View
    {
        return view('settings.ovpn', [
            'ovpn' => [
                'host' => (string) config('ovpn.host'),
                'port' => (string) config('ovpn.port'),
                'proto' => (string) config('ovpn.proto'),
                'username' => (string) config('ovpn.username'),
                'password' => (string) config('ovpn.password'),
                'network' => (string) config('ovpn.network'),
                'netmask' => (string) config('ovpn.netmask'),
            ],
        ]);
    }
}
