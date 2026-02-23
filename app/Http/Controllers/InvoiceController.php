<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $query = Invoice::query()->with(['pppUser.profile', 'owner']);

        // Apply tenant data isolation
        $query->accessibleBy($user);

        $invoices = $query->latest()->paginate(15);

        return view('invoices.index', compact('invoices'));
    }

    public function show(Invoice $invoice): View
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && $invoice->owner_id !== $user->id) {
            abort(403);
        }

        $invoice->load(['pppUser.profile', 'owner', 'payment']);

        $bankAccounts = $invoice->owner?->bankAccounts()->active()->get() ?? collect();
        $settings = $invoice->owner?->getSettings();

        return view('invoices.show', compact('invoice', 'bankAccounts', 'settings'));
    }

    public function pay(Invoice $invoice): JsonResponse|RedirectResponse
    {
        $invoice->update(['status' => 'paid']);
        if ($invoice->pppUser) {
            $invoice->pppUser->update([
                'status_bayar' => 'sudah_bayar',
                'jatuh_tempo' => $this->extendDueDate($invoice),
            ]);
        }

        if (request()->wantsJson()) {
            return response()->json(['status' => 'Invoice dibayar.']);
        }

        return redirect()->back()->with('status', 'Invoice dibayar.');
    }

    public function renew(Invoice $invoice): JsonResponse|RedirectResponse
    {
        if ($invoice->status === 'paid') {
            if (request()->wantsJson()) {
                return response()->json(['error' => 'Invoice sudah dibayar.'], 422);
            }
            return redirect()->back()->with('status', 'Invoice sudah dibayar.');
        }

        if (! $invoice->created_at->equalTo($invoice->updated_at)) {
            if (request()->wantsJson()) {
                return response()->json(['error' => 'Layanan sudah diperpanjang untuk periode ini.'], 422);
            }
            return redirect()->back()->with('status', 'Layanan sudah diperpanjang untuk periode ini.');
        }

        $newDue = $this->extendDueDate($invoice);
        $invoice->update([
            'due_date' => $newDue,
            'status' => 'unpaid',
        ]);

        if ($invoice->pppUser) {
            $invoice->pppUser->update([
                'jatuh_tempo' => $newDue,
                'status_bayar' => 'belum_bayar',
            ]);
        }

        if (request()->wantsJson()) {
            return response()->json(['status' => 'Layanan diperpanjang, status bayar tetap BELUM BAYAR.']);
        }

        return redirect()->back()->with('status', 'Layanan diperpanjang, status bayar tetap BELUM BAYAR.');
    }

    public function destroy(Invoice $invoice): JsonResponse|RedirectResponse
    {
        $pppUser = $invoice->pppUser;
        $invoice->delete();

        if ($pppUser && $pppUser->status_bayar !== 'sudah_bayar') {
            $pppUser->update(['status_bayar' => 'belum_bayar']);
        }

        if (request()->wantsJson()) {
            return response()->json(['status' => 'Invoice dihapus.']);
        }

        return redirect()->back()->with('status', 'Invoice dihapus.');
    }

    private function extendDueDate(Invoice $invoice): Carbon
    {
        $base = $invoice->due_date ? Carbon::parse($invoice->due_date) : now();

        return $base->copy()->addMonthNoOverflow()->endOfDay();
    }
}
