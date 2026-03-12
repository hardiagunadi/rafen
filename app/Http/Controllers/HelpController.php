<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class HelpController extends Controller
{
    public function index(): View
    {
        return view('help.index');
    }

    public function topic(string $slug): View
    {
        $topics = [
            'freeradius' => 'help.topics.freeradius',
            'hotspot' => 'help.topics.hotspot',
            'pppoe' => 'help.topics.pppoe',
            'wireguard' => 'help.topics.wireguard',
            'voucher' => 'help.topics.voucher',
            'profil-paket' => 'help.topics.profil-paket',
            'session' => 'help.topics.session',
            'invoice' => 'help.topics.invoice',
            'troubleshoot' => 'help.topics.troubleshoot',
            'panduan-role' => 'help.topics.panduan-role',
            'fitur-operasional' => 'help.topics.fitur-operasional',
            'faq' => 'help.topics.faq',
            'whatsapp-gateway' => 'help.topics.whatsapp-gateway',
        ];

        abort_unless(isset($topics[$slug]), 404);

        return view($topics[$slug]);
    }
}
