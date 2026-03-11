<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPaidInvoiceSideEffectsJob;
use App\Models\Invoice;
use App\Models\PppUser;
use App\Models\TenantSettings;
use App\Services\IsolirSynchronizer;
use App\Services\RadiusReplySynchronizer;
use App\Services\WaNotificationService;
use App\Traits\LogsActivity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
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
        $user = $request->user();
        $isTeknisi = $user->role === 'teknisi';
        $search = $request->input('search.value', '');
        $statusFilter = (string) $request->input('status', '');

        $query = $this->applyDatatableStatusFilter(
            Invoice::query()
                ->with(['pppUser.profile', 'owner'])
                ->withCount(['payments as pending_count' => fn ($q) => $q->where('status', 'pending')])
                ->accessibleBy($user),
            $statusFilter
        )
            ->when($search !== '', fn ($q) => $q->where(function ($q2) use ($search) {
                $q2->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_id', 'like', "%{$search}%");
            }))
            ->orderByDesc('created_at');

        $total = Invoice::query()->accessibleBy($user)->count();
        $filtered = $query->count();
        $rows = $query->offset($request->integer('start'))
            ->limit(max(1, $request->integer('length', 20)))
            ->get();

        $this->enforceOverdueIsolationForInvoices($rows);

        return response()->json([
            'draw' => $request->integer('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows->map(function (Invoice $r) use ($isTeknisi): array {
                [$statusLabel, $statusVariant] = $this->resolveInvoiceStatusDisplay($r);
                $isRenewedWithoutPayment = $r->status === 'unpaid' && (bool) $r->renewed_without_payment;
                $canMarkAsPaid = $r->status === 'unpaid' && ($r->pending_count ?? 0) === 0 && $isRenewedWithoutPayment;

                return [
                    'id' => $r->id,
                    'invoice_number' => $r->invoice_number,
                    'customer_id' => $r->customer_id ?? '-',
                    'customer_name' => $r->customer_name ?? '-',
                    'tipe_service' => strtoupper(str_replace('_', '/', $r->tipe_service ?? '')),
                    'paket_langganan' => $r->paket_langganan ?? '-',
                    'total' => number_format($r->total, 0, ',', '.'),
                    'due_date' => $r->due_date?->format('d-m-Y') ?? '-',
                    'owner_name' => $r->owner?->name ?? '-',
                    'status' => $r->status,
                    'status_label' => $statusLabel,
                    'status_variant' => $statusVariant,
                    'has_pending' => ($r->pending_count ?? 0) > 0,
                    'can_pay' => $r->status === 'unpaid' && ($r->pending_count ?? 0) === 0 && ! $isRenewedWithoutPayment,
                    'can_mark_paid' => $canMarkAsPaid,
                    'can_renew' => $r->status === 'unpaid' && ! $isRenewedWithoutPayment,
                    'can_nota' => ! $isTeknisi,
                    'can_delete' => ! $isTeknisi,
                    'pay_url' => route('invoices.pay', $r->id),
                    'renew_url' => route('invoices.renew', $r->id),
                    'destroy_url' => route('invoices.destroy', $r->id),
                    'show_url' => route('invoices.show', $r->id),
                    'print_url' => route('invoices.print', $r->id),
                    'nota_url' => route('invoices.nota', $r->id),
                ];
            }),
        ]);
    }

    private function applyDatatableStatusFilter(Builder $query, string $statusFilter): Builder
    {
        return match ($statusFilter) {
            'paid' => $query->where('status', 'paid'),
            'unpaid' => $query->where('status', 'unpaid'),
            'active_unpaid' => $query->where('status', 'unpaid')
                ->whereHas('pppUser', fn (Builder $pppUserQuery) => $pppUserQuery->where('status_akun', 'enable')),
            'isolated_unpaid' => $query->where('status', 'unpaid')
                ->whereHas('pppUser', fn (Builder $pppUserQuery) => $pppUserQuery->where('status_akun', 'isolir')),
            default => $query,
        };
    }

    public function show(Invoice $invoice): View
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $invoice->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $invoice->load(['pppUser.profile', 'owner', 'payment']);

        $bankAccounts = $invoice->owner?->bankAccounts()->active()->get() ?? collect();
        $settings = $invoice->owner?->getSettings();
        $pendingPayment = \App\Models\Payment::where('invoice_id', $invoice->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        return view('invoices.show', compact('invoice', 'bankAccounts', 'settings', 'pendingPayment'));
    }

    public function print(Invoice $invoice): View
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $invoice->owner_id !== $user->effectiveOwnerId()) {
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
        $ids = array_filter(explode(',', $request->input('ids', '')), 'is_numeric');

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

        if (! $user->isSuperAdmin() && $invoice->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        $invoice->load(['pppUser', 'owner', 'payment', 'paidBy']);

        $bankAccounts = $invoice->owner?->bankAccounts()->active()->get() ?? collect();
        $settings = $invoice->owner?->getSettings();

        return view('invoices.nota', compact('invoice', 'bankAccounts', 'settings'));
    }

    public function pay(Request $request, Invoice $invoice): JsonResponse|RedirectResponse
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $invoice->owner_id !== $user->effectiveOwnerId()) {
            abort(403);
        }

        if ($invoice->status === 'paid') {
            if (request()->wantsJson()) {
                return response()->json(['status' => 'Invoice sudah dibayar.']);
            }

            return redirect()->back()->with('status', 'Invoice sudah dibayar.');
        }

        $paidAt = now();
        $cashReceived = (float) ($request->input('cash_received') ?: 0);
        $hasCashReceived = $cashReceived > 0;
        $wasOnProcess = false;
        $wasIsolir = false;
        $pppUserId = null;

        $invoice->update([
            'status' => 'paid',
            'renewed_without_payment' => false,
            'paid_at' => $paidAt,
            'paid_by' => $user->id,
            'cash_received' => $request->input('cash_received') ?: null,
            'transfer_amount' => $request->input('transfer_amount') ?: null,
            'payment_note' => $request->input('payment_note') ?: null,
        ]);
        if ($invoice->pppUser) {
            $pppUser = $invoice->pppUser;
            $pppUserId = $pppUser->id;
            $wasOnProcess = $pppUser->status_registrasi === 'on_process';
            $wasIsolir = $pppUser->status_akun === 'isolir';

            $pppUser->update([
                'status_bayar' => 'sudah_bayar',
                'status_akun' => 'enable',
                'jatuh_tempo' => $this->extendDueDate($invoice),
            ]);

            if ($wasOnProcess) {
                $pppUser->update(['status_registrasi' => 'aktif']);
            }
        }

        $this->logActivity('paid', 'Invoice', $invoice->id, $invoice->invoice_number, (int) $invoice->owner_id);

        ProcessPaidInvoiceSideEffectsJob::dispatchAfterResponse(
            invoiceId: (int) $invoice->id,
            ownerId: (int) $invoice->owner_id,
            paidByUserId: (int) $user->id,
            pppUserId: $pppUserId,
            wasOnProcess: $wasOnProcess,
            wasIsolir: $wasIsolir,
            hasCashReceived: $hasCashReceived,
            paidDate: $paidAt->toDateString(),
        );

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

        if ($invoice->renewed_without_payment) {
            if (request()->wantsJson()) {
                return response()->json(['error' => 'Layanan sudah diperpanjang untuk periode ini.'], 422);
            }

            return redirect()->back()->with('status', 'Layanan sudah diperpanjang untuk periode ini.');
        }

        $newDue = $this->extendDueDate($invoice);
        $invoice->update([
            'due_date' => $newDue,
            'status' => 'unpaid',
            'renewed_without_payment' => true,
        ]);

        if ($invoice->pppUser) {
            $pppUser = $invoice->pppUser;
            $wasIsolir = $pppUser->status_akun === 'isolir';

            $pppUser->update([
                'jatuh_tempo' => $newDue,
                'status_bayar' => 'belum_bayar',
                'status_akun' => 'enable',
            ]);

            try {
                $pppUser->refresh();
                app(RadiusReplySynchronizer::class)->syncSingleUser($pppUser);

                if ($wasIsolir) {
                    app(IsolirSynchronizer::class)->deisolate($pppUser);
                }
            } catch (\Throwable $exception) {
                Log::warning('Invoice renew side effects failed', [
                    'invoice_id' => $invoice->id,
                    'ppp_user_id' => $pppUser->id,
                    'was_isolir' => $wasIsolir,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->logActivity('renewed', 'Invoice', $invoice->id, $invoice->invoice_number, (int) $invoice->owner_id);

        $settings = TenantSettings::getOrCreate((int) $invoice->owner_id);
        $freshInvoice = $invoice->fresh()->load('pppUser');
        if ($freshInvoice->pppUser) {
            WaNotificationService::notifyInvoiceCreated($settings, $freshInvoice, $freshInvoice->pppUser);
        }

        if (request()->wantsJson()) {
            return response()->json(['status' => 'Layanan diperpanjang. Status: Aktif - Belum Bayar.']);
        }

        return redirect()->back()->with('status', 'Layanan diperpanjang. Status: Aktif - Belum Bayar.');
    }

    public function destroy(Invoice $invoice): JsonResponse|RedirectResponse
    {
        $user = auth()->user();

        if ($user->role === 'teknisi') {
            abort(403);
        }

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
        $phone = $pppUser->nomor_hp ?? '';

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
            $paidAt = $invoice->paid_at ? $invoice->paid_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i');
            $customerId = $invoice->customer_id ?? ($pppUser->customer_id ?? '-');
            $profileName = $invoice->paket_langganan ?? ($pppUser->profile?->name ?? '-');
            $serviceType = $invoice->tipe_service ? strtoupper($invoice->tipe_service) : strtoupper((string) $pppUser->tipe_service);
            $csNumber = $settings->business_phone ?? '-';
            $message = str_replace(
                ['{name}', '{invoice_no}', '{total}', '{paid_at}', '{customer_id}', '{profile}', '{service}', '{cs_number}'],
                [
                    $invoice->customer_name ?? 'Pelanggan',
                    $invoice->invoice_number,
                    'Rp '.number_format($invoice->total, 0, ',', '.'),
                    $paidAt,
                    $customerId,
                    $profileName,
                    $serviceType,
                    $csNumber,
                ],
                $template
            );
        } else {
            $template = $settings->getTemplate('invoice');
            $customerId = $invoice->customer_id ?? ($pppUser->customer_id ?? '-');
            $profileName = $invoice->paket_langganan ?? ($pppUser->profile?->name ?? '-');
            $serviceType = $invoice->tipe_service ? strtoupper($invoice->tipe_service) : strtoupper((string) $pppUser->tipe_service);
            $csNumber = $settings->business_phone ?? '-';
            $bankAccounts = $invoice->owner?->bankAccounts()->active()->get()
                ?? \App\Models\BankAccount::where('user_id', $invoice->owner_id)->where('is_active', true)->get();
            $bankLines = $bankAccounts->map(fn ($b) => $b->bank_name.' '.$b->account_number.' a/n '.$b->account_name)->join("\n");
            if (empty(trim($bankLines))) {
                $bankLines = '-';
            }

            if (empty($invoice->payment_token)) {
                $invoice->update(['payment_token' => Invoice::generatePaymentToken()]);
            }
            $paymentLink = route('customer.invoice', $invoice->payment_token);

            $message = str_replace(
                ['{name}', '{invoice_no}', '{total}', '{due_date}', '{customer_id}', '{profile}', '{service}', '{cs_number}', '{bank_account}', '{payment_link}'],
                [
                    $invoice->customer_name ?? 'Pelanggan',
                    $invoice->invoice_number,
                    'Rp '.number_format($invoice->total, 0, ',', '.'),
                    $invoice->due_date ? $invoice->due_date->format('d/m/Y') : '-',
                    $customerId,
                    $profileName,
                    $serviceType,
                    $csNumber,
                    $bankLines,
                    $paymentLink,
                ],
                $template
            );
        }

        $waService->sendMessage($phone, $message);

        $this->logActivity('send_wa', 'Invoice', $invoice->id, $invoice->invoice_number, (int) $invoice->owner_id);

        if (request()->wantsJson()) {
            return response()->json(['status' => 'Notifikasi WhatsApp berhasil dikirim ke '.$phone]);
        }

        return redirect()->back()->with('status', 'Notifikasi WhatsApp berhasil dikirim ke '.$phone);
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     */
    private function enforceOverdueIsolationForInvoices(Collection $invoices): void
    {
        $today = now()->toDateString();

        $candidates = $invoices
            ->pluck('pppUser')
            ->filter(static fn ($pppUser): bool => $pppUser instanceof PppUser)
            ->unique('id')
            ->filter(static function (PppUser $pppUser) use ($today): bool {
                if ($pppUser->status_akun !== 'enable') {
                    return false;
                }

                if ($pppUser->status_bayar !== 'belum_bayar') {
                    return false;
                }

                if ($pppUser->aksi_jatuh_tempo !== 'isolir') {
                    return false;
                }

                if (! $pppUser->jatuh_tempo) {
                    return false;
                }

                return $pppUser->jatuh_tempo->toDateString() <= $today;
            });

        if ($candidates->isEmpty()) {
            return;
        }

        $settingsCache = [];
        $radiusSync = app(RadiusReplySynchronizer::class);
        $isolirSync = app(IsolirSynchronizer::class);

        foreach ($candidates as $pppUser) {
            $ownerId = (int) $pppUser->owner_id;

            if (! isset($settingsCache[$ownerId])) {
                $settingsCache[$ownerId] = TenantSettings::getOrCreate($ownerId);
            }

            if (! $settingsCache[$ownerId]->auto_isolate_unpaid) {
                continue;
            }

            try {
                $pppUser->update(['status_akun' => 'isolir']);
                $pppUser->refresh();

                $radiusSync->syncSingleUser($pppUser);
                $isolirSync->isolate($pppUser);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveInvoiceStatusDisplay(Invoice $invoice): array
    {
        if ($invoice->status === 'paid') {
            return ['Lunas', 'success'];
        }

        if ($invoice->pppUser?->status_akun === 'isolir') {
            return ['Belum Bayar - Terisolir', 'danger'];
        }

        if ($invoice->status === 'unpaid' && $invoice->pppUser?->status_akun === 'enable') {
            return ['Aktif - Belum Bayar', 'warning'];
        }

        return ['Belum Bayar', 'warning'];
    }

    private function extendDueDate(Invoice $invoice): Carbon
    {
        $base = $invoice->due_date ? Carbon::parse($invoice->due_date) : now();

        // Jika due_date sudah lewat, hitung dari hari ini agar tidak extend ke masa lalu
        if ($base->isPast()) {
            $base = now();
        }

        return $base->copy()->addMonthNoOverflow()->endOfDay();
    }
}
