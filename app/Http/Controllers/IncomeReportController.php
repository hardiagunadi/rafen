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

        $authUser = $request->user();
        $owners = $authUser->isSuperAdmin()
            ? User::query()->where('is_super_admin', false)->whereNull('parent_id')->orderBy('name')->get()
            : User::query()->where('id', $authUser->effectiveOwnerId())->get();

        $report = [
            'total' => 0,
            'currency' => 'IDR',
            'items' => collect(),
        ];

        return view('reports.income', compact('filters', 'owners', 'report'));
    }
}
