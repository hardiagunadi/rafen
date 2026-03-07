<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\TenantSettings;
use App\Services\WaNotificationService;
use App\Traits\LogsActivity;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    use LogsActivity;
    public function index(Request $request): View
    {
        return view('invoices.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        $user   = $request->user();
        $search = $request->input('search.value', '');

        $query = Invoice::query()
            ->with(['pppUser.profile', 'owner'])
            ->accessibleBy($user)
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($search !== '', fn($q) => $q->where(function ($q2) use ($search) {
                $q2->where('invoice_number', 'like', "%{$search}%")
                   ->orWhere('customer_name', 'like', "%{$search}%")
                   ->orWhere('customer_id', 'like', "%{$search}%");
            }))
            ->orderByDesc('created_at');

        $total    = Invoice::query()->accessibleBy($user)->count();
        $filtered = $query->count();
        $rows     = $query->offset($request->integer('start'))
            ->limit(max(1, $request->integer('length', 20)))
            ->get();

        return response()->json([
            'draw'            => $request->integer('draw'),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $rows->map(fn($r) => [
                'id'              => $r->id,
                'invoice_number'  => $r->invoice_number,
                'customer_id'     => $r->customer_id ?? '-',
                'customer_name'   => $r->customer_name ?? '-',
                'tipe_service'    => strtoupper(str_replace('_', '/', $r->tipe_service ?? '')),
                'paket_langganan' => $r->paket_langganan ?? '-',
                'total'           => number_format($r->total, 0, ',', '.'),
                'due_date'        => $r->due_date ? \Carbon\Carbon::parse($r->due_date)->format('Y-m-d') : '-',
                'owner_name'      => $r->owner?->name ?? '-',
                'status'          => $r->status,
                'can_pay'         => $r->status === 'unpaid',
                'can_renew'       => $r->status === 'unpaid' && $r->created_at->equalTo($r->updated_at),
                'pay_url'         => route('invoices.pay', $r->id),
                'renew_url'       => route('invoices.renew', $r->id),
                'destroy_url'     => route('invoices.destroy', $r->id),
                'show_url'        => route('invoices.show', $r->id),
                'print_url'       => route('invoices.print', $r->id),
                'nota_url'        => route('invoices.nota', $r->id),
            ]),
        ]);
    }

    public function show(Invoice $invoice): View
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && $invoice->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $invoice->load(['pppUser.profile', 'owner', 'payment']);

        $bankAccounts = $invoice->owner?->bankAccounts()->active()->get() ?? collect();
        $settings = $invoice->owner?->getSettings();

        return view('invoices.show', compact('invoice', 'bankAccounts', 'settings'));
    }

    public function print(Invoice $invoice): View
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && $invoice->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $invoice->load(['pppUser.profile', 'owner', 'payment']);

        $bankAccounts = $invoice->owner?->bankAccounts()->active()->get() ?? collect();
        $settings = $invoice->owner?->getSettings();

        return view('invoices.print', compact('invoice', 'bankAccounts', 'settings'));
    }

    public function notaBulk(Request $request): View
    {
        $user = auth()->user();
        $ids  = array_filter(explode(',', $request->input('ids', '')), 'is_numeric');

        $invoices = Invoice::query()
            ->with(['pppUser', 'owner'])
            ->whereIn('id', $ids)
            ->accessibleBy($user)
            ->get();

        $settings = $invoices->first()?->owner?->getSettings();

        return view('invoices.nota-bulk', compact('invoices', 'settings'));
    }

    public function nota(Invoice $invoice): View
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && $invoice->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $invoice->load(['pppUser', 'owner', 'payment']);

        $bankAccounts = $invoice->owner?->bankAccounts()->active()->get() ?? collect();
        $settings = $invoice->owner?->getSettings();

        return view('invoices.nota', compact('invoice', 'bankAccounts', 'settings'));
    }

    public function pay(Invoice $invoice): JsonResponse|RedirectResponse
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $invoice->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $invoice->update(['status' => 'paid']);
        if ($invoice->pppUser) {
            $invoice->pppUser->update([
                'status_bayar' => 'sudah_bayar',
                'jatuh_tempo' => $this->extendDueDate($invoice),
            ]);
        }

        $this->logActivity('paid', 'Invoice', $invoice->id, $invoice->invoice_number, (int) $invoice->owner_id);

        $settings = TenantSettings::getOrCreate((int) $invoice->owner_id);
        WaNotificationService::notifyInvoicePaid($settings, $invoice->fresh()->load('pppUser'));

        if (request()->wantsJson()) {
            return response()->json(['status' => 'Invoice dibayar.']);
        }

        return redirect()->back()->with('status', 'Invoice dibayar.');
    }

    public function renew(Invoice $invoice): JsonResponse|RedirectResponse
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $invoice->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

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

        $this->logActivity('renewed', 'Invoice', $invoice->id, $invoice->invoice_number, (int) $invoice->owner_id);

        if (request()->wantsJson()) {
            return response()->json(['status' => 'Layanan diperpanjang, status bayar tetap BELUM BAYAR.']);
        }

        return redirect()->back()->with('status', 'Layanan diperpanjang, status bayar tetap BELUM BAYAR.');
    }

    public function destroy(Invoice $invoice): JsonResponse|RedirectResponse
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $invoice->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $this->logActivity('deleted', 'Invoice', $invoice->id, $invoice->invoice_number, (int) $invoice->owner_id);
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

    public function sendWa(Invoice $invoice): JsonResponse|RedirectResponse
    {
        $user = auth()->user();

        $canSendWa = $user->isSuperAdmin() || $user->isAdmin() || $user->role === 'keuangan';
        if (! $canSendWa) {
            abort(403);
        }

        if (! $user->isSuperAdmin() && $invoice->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $settings = TenantSettings::getOrCreate((int) $invoice->owner_id);

        if (! $settings->hasWaConfigured()) {
            if (request()->wantsJson()) {
                return response()->json(['error' => 'WhatsApp Gateway belum dikonfigurasi.'], 422);
            }
            return redirect()->back()->with('error', 'WhatsApp Gateway belum dikonfigurasi.');
        }

        $invoice->load('pppUser');

        $pppUser = $invoice->pppUser;
        $phone   = $pppUser->nomor_hp ?? '';

        if (! $pppUser || empty(trim($phone))) {
            if (request()->wantsJson()) {
                return response()->json(['error' => 'Pelanggan tidak ditemukan atau nomor HP tidak tersedia.'], 422);
            }
            return redirect()->back()->with('error', 'Pelanggan tidak ditemukan atau nomor HP tidak tersedia.');
        }

        // Kirim langsung (bypass toggle notifikasi otomatis — ini pengiriman manual admin)
        $waService = \App\Services\WaGatewayService::forTenant($settings);
        if (! $waService) {
            if (request()->wantsJson()) {
                return response()->json(['error' => 'WA Gateway tidak dapat diinisialisasi.'], 422);
            }
            return redirect()->back()->with('error', 'WA Gateway tidak dapat diinisialisasi.');
        }

        if ($invoice->isPaid()) {
            $template = $settings->getTemplate('payment');
            $paidAt   = $invoice->paid_at ? $invoice->paid_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i');
            $message  = str_replace(
                ['{name}', '{invoice_no}', '{total}', '{paid_at}'],
                [$invoice->customer_name, $invoice->invoice_number, number_format($invoice->total, 0, ',', '.'), $paidAt],
                $template
            );
        } else {
            $template = $settings->getTemplate('invoice');
            $message  = str_replace(
                ['{name}', '{invoice_no}', '{total}', '{due_date}'],
                [
                    $invoice->customer_name,
                    $invoice->invoice_number,
                    number_format($invoice->total, 0, ',', '.'),
                    $invoice->due_date ? $invoice->due_date->format('d/m/Y') : '-',
                ],
                $template
            );
        }

        $waService->sendMessage($phone, $message);

        $this->logActivity('send_wa', 'Invoice', $invoice->id, $invoice->invoice_number, (int) $invoice->owner_id);

        if (request()->wantsJson()) {
            return response()->json(['status' => 'Notifikasi WhatsApp berhasil dikirim ke ' . $phone]);
        }

        return redirect()->back()->with('status', 'Notifikasi WhatsApp berhasil dikirim ke ' . $phone);
    }

    private function extendDueDate(Invoice $invoice): Carbon
    {
        $base = $invoice->due_date ? Carbon::parse($invoice->due_date) : now();

        return $base->copy()->addMonthNoOverflow()->endOfDay();
    }
}
