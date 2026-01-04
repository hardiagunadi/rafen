<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IncomeReportController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = [
            'tipe_user' => $request->input('tipe_user', 'semua'),
            'service_type' => $request->input('service_type', ''),
            'owner_id' => $request->input('owner_id'),
        ];

        $owners = User::query()->orderBy('name')->get();

        $report = [
            'total' => 0,
            'currency' => 'IDR',
            'items' => collect(),
        ];

        return view('reports.income', compact('filters', 'owners', 'report'));
    }
}
