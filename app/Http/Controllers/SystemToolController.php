<?php

namespace App\Http\Controllers;

use App\Models\HotspotUser;
use App\Models\Invoice;
use App\Models\PppUser;
use App\Models\Transaction;
use App\Traits\LogsActivity;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SystemToolController extends Controller
{
    use LogsActivity;

    // ─── Cek Pemakaian ───────────────────────────────────────────────────────

    public function usageIndex(): View
    {
        return view('system_tools.usage');
    }

    public function usageData(Request $request): JsonResponse
    {
        $user   = $request->user();
        $search = $request->input('search', '');
        $type   = $request->input('type', 'ppp'); // ppp | hotspot

        if ($type === 'hotspot') {
            $query = HotspotUser::query()
                ->accessibleBy($user)
                ->when($search !== '', fn ($q) => $q->where(function ($q2) use ($search) {
                    $q2->where('username', 'like', "%{$search}%")
                       ->orWhere('customer_name', 'like', "%{$search}%");
                }));

            $rows = $query->orderBy('customer_name')->get()->map(function (HotspotUser $u) {
                $acct = DB::table('radacct')
                    ->where('username', $u->username)
                    ->orderByDesc('acctstarttime')
                    ->first();

                return [
                    'username'      => $u->username ?? '-',
                    'customer_name' => $u->customer_name,
                    'upload'        => $acct ? $this->formatBytes((int) $acct->acctinputoctets) : '-',
                    'download'      => $acct ? $this->formatBytes((int) $acct->acctoutputoctets) : '-',
                    'session_time'  => $acct ? $this->formatDuration((int) $acct->acctsessiontime) : '-',
                    'last_seen'     => $acct?->acctstoptime ?? $acct?->acctupdatetime ?? '-',
                    'ip_address'    => $acct?->framedipaddress ?? '-',
                    'online'        => $acct && ! $acct->acctstoptime ? true : false,
                ];
            });
        } else {
            $query = PppUser::query()
                ->accessibleBy($user)
                ->when($search !== '', fn ($q) => $q->where(function ($q2) use ($search) {
                    $q2->where('username', 'like', "%{$search}%")
                       ->orWhere('customer_name', 'like', "%{$search}%");
                }));

            $rows = $query->orderBy('customer_name')->get()->map(function (PppUser $u) {
                $acct = DB::table('radacct')
                    ->where('username', $u->username)
                    ->orderByDesc('acctstarttime')
                    ->first();

                return [
                    'username'      => $u->username ?? '-',
                    'customer_name' => $u->customer_name,
                    'upload'        => $acct ? $this->formatBytes((int) $acct->acctinputoctets) : '-',
                    'download'      => $acct ? $this->formatBytes((int) $acct->acctoutputoctets) : '-',
                    'session_time'  => $acct ? $this->formatDuration((int) $acct->acctsessiontime) : '-',
                    'last_seen'     => $acct?->acctstoptime ?? $acct?->acctupdatetime ?? '-',
                    'ip_address'    => $acct?->framedipaddress ?? '-',
                    'online'        => $acct && ! $acct->acctstoptime ? true : false,
                ];
            });
        }

        return response()->json(['data' => $rows]);
    }

    // ─── Impor User ──────────────────────────────────────────────────────────

    public function importIndex(): View
    {
        return view('system_tools.import');
    }

    public function importTemplate(string $type): Response
    {
        $pppHeaders      = ['customer_id', 'customer_name', 'nik', 'nomor_hp', 'email', 'alamat', 'username', 'ppp_password', 'status_akun', 'status_bayar', 'jatuh_tempo', 'tipe_service', 'catatan'];
        $hotspotHeaders  = ['customer_id', 'customer_name', 'nik', 'nomor_hp', 'email', 'alamat', 'username', 'hotspot_password', 'status_akun', 'status_bayar', 'jatuh_tempo', 'catatan'];
        $headers         = $type === 'hotspot' ? $hotspotHeaders : $pppHeaders;
        $filename        = "template_{$type}_users.csv";

        $output = fopen('php://output', 'w');
        ob_start();
        fputcsv($output, $headers);
        fclose($output);
        $csv = ob_get_clean();

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function importStore(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'type' => 'required|in:ppp,hotspot',
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $user = $request->user();
        $type = $request->input('type');
        $file = $request->file('file');

        $handle  = fopen($file->getRealPath(), 'r');
        $headers = array_map('trim', fgetcsv($handle) ?: []);

        $inserted = 0;
        $errors   = [];
        $row      = 1;

        while (($line = fgetcsv($handle)) !== false) {
            $row++;
            if (empty(array_filter($line))) {
                continue;
            }

            $data = array_combine($headers, array_map('trim', $line));
            if ($data === false) {
                $errors[] = "Baris {$row}: kolom tidak sesuai.";
                continue;
            }

            $data['owner_id'] = $user->effectiveOwnerId();

            try {
                if ($type === 'ppp') {
                    $this->importPppRow($data);
                } else {
                    $this->importHotspotRow($data);
                }
                $inserted++;
            } catch (\Throwable $e) {
                $errors[] = "Baris {$row}: " . $e->getMessage();
            }
        }

        fclose($handle);

        $this->logActivity('imported', ucfirst($type) . 'User', 0, "{$inserted} records", $user->effectiveOwnerId());

        if ($request->wantsJson()) {
            return response()->json([
                'inserted' => $inserted,
                'errors'   => $errors,
            ]);
        }

        $msg = "Berhasil mengimpor {$inserted} data.";
        if (count($errors)) {
            $msg .= ' ' . count($errors) . ' baris gagal.';
        }

        return redirect()->route('tools.import')->with('status', $msg);
    }

    // ─── Ekspor User ─────────────────────────────────────────────────────────

    public function exportUsersIndex(): View
    {
        return view('system_tools.export_users');
    }

    public function exportUsersDownload(Request $request): Response
    {
        $request->validate([
            'type'   => 'required|in:ppp,hotspot',
            'status' => 'nullable|string',
        ]);

        $user   = $request->user();
        $type   = $request->input('type');
        $status = $request->input('status');

        if ($type === 'hotspot') {
            $rows = HotspotUser::query()
                ->accessibleBy($user)
                ->when($status, fn ($q) => $q->where('status_akun', $status))
                ->orderBy('customer_name')
                ->get();

            $headers = ['customer_id', 'customer_name', 'nik', 'nomor_hp', 'email', 'alamat', 'username', 'hotspot_password', 'status_akun', 'status_bayar', 'jatuh_tempo', 'catatan'];
        } else {
            $rows = PppUser::query()
                ->accessibleBy($user)
                ->when($status, fn ($q) => $q->where('status_akun', $status))
                ->orderBy('customer_name')
                ->get();

            $headers = ['customer_id', 'customer_name', 'nik', 'nomor_hp', 'email', 'alamat', 'username', 'ppp_password', 'status_akun', 'status_bayar', 'jatuh_tempo', 'tipe_service', 'catatan'];
        }

        $filename = "export_{$type}_users_" . now()->format('Ymd_His') . '.csv';

        $output = fopen('php://output', 'w');
        ob_start();

        fputcsv($output, $headers);
        foreach ($rows as $r) {
            $row = [];
            foreach ($headers as $col) {
                $val = $r->$col ?? '';
                if ($val instanceof \Carbon\Carbon) {
                    $val = $val->format('Y-m-d');
                }
                $row[] = (string) $val;
            }
            fputcsv($output, $row);
        }

        fclose($output);
        $csv = ob_get_clean();

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ─── Ekspor Transaksi ────────────────────────────────────────────────────

    public function exportTransactionsIndex(): View
    {
        return view('system_tools.export_transactions');
    }

    public function exportTransactionsDownload(Request $request): Response
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
            'status'    => 'nullable|in:paid,unpaid',
        ]);

        $user     = $request->user();
        $dateFrom = $request->input('date_from') ? Carbon::parse($request->input('date_from'))->startOfDay() : null;
        $dateTo   = $request->input('date_to') ? Carbon::parse($request->input('date_to'))->endOfDay() : null;
        $status   = $request->input('status');

        $rows = Invoice::query()
            ->with('owner')
            ->accessibleBy($user)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($dateFrom, fn ($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('created_at', '<=', $dateTo))
            ->orderByDesc('created_at')
            ->get();

        $filename = "export_transaksi_" . now()->format('Ymd_His') . '.csv';
        $headers  = ['invoice_number', 'customer_id', 'customer_name', 'tipe_service', 'paket_langganan', 'harga_dasar', 'ppn_percent', 'ppn_amount', 'total', 'status', 'due_date', 'paid_at', 'payment_method', 'created_at'];

        $output = fopen('php://output', 'w');
        ob_start();

        fputcsv($output, $headers);
        foreach ($rows as $r) {
            fputcsv($output, [
                $r->invoice_number,
                $r->customer_id ?? '',
                $r->customer_name ?? '',
                $r->tipe_service ?? '',
                $r->paket_langganan ?? '',
                $r->harga_dasar,
                $r->ppn_percent,
                $r->ppn_amount,
                $r->total,
                $r->status,
                $r->due_date?->format('Y-m-d') ?? '',
                $r->paid_at?->format('Y-m-d H:i:s') ?? '',
                $r->payment_method ?? '',
                $r->created_at->format('Y-m-d H:i:s'),
            ]);
        }

        fclose($output);
        $csv = ob_get_clean();

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ─── Backup & Restore DB ─────────────────────────────────────────────────

    public function backupIndex(): View
    {
        $this->requireSuperAdmin();

        $files = collect(Storage::disk('local')->files('backups'))
            ->filter(fn ($f) => str_ends_with($f, '.sql.gz'))
            ->map(fn ($f) => [
                'name'     => basename($f),
                'path'     => $f,
                'size'     => $this->formatBytes(Storage::disk('local')->size($f)),
                'modified' => Carbon::createFromTimestamp(Storage::disk('local')->lastModified($f))->format('Y-m-d H:i:s'),
            ])
            ->sortByDesc('modified')
            ->values();

        return view('system_tools.backup', compact('files'));
    }

    public function backupDownload(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->requireSuperAdmin();

        $filename = $request->input('file');
        $path     = 'backups/' . basename($filename);

        if (! Storage::disk('local')->exists($path)) {
            abort(404, 'File backup tidak ditemukan.');
        }

        return Storage::disk('local')->download($path);
    }

    public function backupCreate(): JsonResponse
    {
        $this->requireSuperAdmin();

        $dbName = config('database.connections.mariadb.database', config('database.connections.mysql.database'));
        $dbUser = config('database.connections.mariadb.username', config('database.connections.mysql.username'));
        $dbPass = config('database.connections.mariadb.password', config('database.connections.mysql.password'));
        $dbHost = config('database.connections.mariadb.host', config('database.connections.mysql.host', '127.0.0.1'));

        $backupDir = storage_path('app/backups');
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $filename = 'backup_' . now()->format('Ymd_His') . '.sql.gz';
        $path     = $backupDir . '/' . $filename;

        $cmd = sprintf(
            'mysqldump --host=%s --user=%s --password=%s --single-transaction --routines %s | gzip > %s 2>&1',
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            escapeshellarg($dbPass),
            escapeshellarg($dbName),
            escapeshellarg($path)
        );

        exec($cmd, $output, $code);

        if ($code !== 0 || ! file_exists($path) || filesize($path) < 100) {
            return response()->json(['error' => 'Backup gagal. Periksa konfigurasi database.'], 500);
        }

        $this->logActivity('backup_created', 'Database', 0, $filename, auth()->id());

        return response()->json(['status' => 'Backup berhasil dibuat.', 'file' => $filename]);
    }

    public function backupRestore(Request $request): JsonResponse
    {
        $this->requireSuperAdmin();

        $request->validate([
            'file' => 'required|file|mimes:gz|max:102400',
        ]);

        $dbName = config('database.connections.mariadb.database', config('database.connections.mysql.database'));
        $dbUser = config('database.connections.mariadb.username', config('database.connections.mysql.username'));
        $dbPass = config('database.connections.mariadb.password', config('database.connections.mysql.password'));
        $dbHost = config('database.connections.mariadb.host', config('database.connections.mysql.host', '127.0.0.1'));

        $uploadedFile = $request->file('file');
        $tmpPath      = $uploadedFile->getRealPath();

        $cmd = sprintf(
            'gunzip -c %s | mysql --host=%s --user=%s --password=%s %s 2>&1',
            escapeshellarg($tmpPath),
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            escapeshellarg($dbPass),
            escapeshellarg($dbName)
        );

        exec($cmd, $output, $code);

        if ($code !== 0) {
            return response()->json(['error' => 'Restore gagal: ' . implode(' ', $output)], 500);
        }

        $this->logActivity('backup_restored', 'Database', 0, $uploadedFile->getClientOriginalName(), auth()->id());

        return response()->json(['status' => 'Database berhasil direstore.']);
    }

    public function backupDelete(Request $request): JsonResponse
    {
        $this->requireSuperAdmin();

        $filename = $request->input('file');
        $path     = 'backups/' . basename($filename);

        Storage::disk('local')->delete($path);

        return response()->json(['status' => 'File backup dihapus.']);
    }

    // ─── Reset Laporan ───────────────────────────────────────────────────────

    public function resetReportIndex(): View
    {
        $this->requireSuperAdmin();
        return view('system_tools.reset_report');
    }

    public function resetReportExecute(Request $request): JsonResponse
    {
        $this->requireSuperAdmin();

        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year'  => 'required|integer|min:2020|max:2099',
        ]);

        $month = (int) $request->input('month');
        $year  = (int) $request->input('year');

        $deleted = Invoice::whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->delete();

        $this->logActivity('reset_report', 'Invoice', 0, "{$year}-{$month} ({$deleted} records)", auth()->id());

        return response()->json(['status' => "Reset laporan {$year}-{$month} berhasil. {$deleted} invoice dihapus."]);
    }

    // ─── Reset Database ──────────────────────────────────────────────────────

    public function resetDatabaseIndex(): View
    {
        $this->requireSuperAdmin();
        return view('system_tools.reset_database');
    }

    public function resetDatabaseExecute(Request $request): JsonResponse
    {
        $this->requireSuperAdmin();

        $request->validate([
            'confirmation' => ['required', 'in:HAPUS SEMUA DATA'],
            'tenant_id'    => 'nullable|exists:users,id',
        ]);

        $tenantId = $request->input('tenant_id');

        if ($tenantId) {
            // Reset data satu tenant
            $this->resetTenantData((int) $tenantId);
            $this->logActivity('reset_database', 'Tenant', $tenantId, "Tenant ID {$tenantId}", auth()->id());
            return response()->json(['status' => "Data tenant ID {$tenantId} berhasil dihapus."]);
        }

        // Reset semua data operasional (bukan users/subscription)
        DB::table('radacct')->delete();
        DB::table('radpostauth')->delete();
        PppUser::query()->delete();
        HotspotUser::query()->delete();
        Invoice::query()->delete();
        DB::table('transactions')->delete();

        $this->logActivity('reset_database', 'Database', 0, 'All operational data', auth()->id());

        return response()->json(['status' => 'Semua data operasional berhasil direset.']);
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function requireSuperAdmin(): void
    {
        if (! auth()->user()->isSuperAdmin()) {
            abort(403);
        }
    }

    private function resetTenantData(int $tenantId): void
    {
        PppUser::where('owner_id', $tenantId)->delete();
        HotspotUser::where('owner_id', $tenantId)->delete();
        Invoice::where('owner_id', $tenantId)->delete();
        DB::table('transactions')->where('owner_id', $tenantId)->delete();
    }

    private function importPppRow(array $data): void
    {
        $required = ['username', 'customer_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Kolom '{$field}' wajib diisi.");
            }
        }

        PppUser::create([
            'owner_id'        => $data['owner_id'],
            'username'        => $data['username'],
            'ppp_password'    => $data['ppp_password'] ?? '',
            'customer_name'   => $data['customer_name'],
            'customer_id'     => $data['customer_id'] ?? null,
            'nik'             => $data['nik'] ?? null,
            'nomor_hp'        => $data['nomor_hp'] ?? null,
            'email'           => $data['email'] ?? null,
            'alamat'          => $data['alamat'] ?? null,
            'status_akun'     => in_array($data['status_akun'] ?? '', ['enable', 'disable', 'isolir']) ? $data['status_akun'] : 'enable',
            'status_bayar'    => in_array($data['status_bayar'] ?? '', ['sudah_bayar', 'belum_bayar']) ? $data['status_bayar'] : 'belum_bayar',
            'tipe_service'    => $data['tipe_service'] ?? 'pppoe',
            'jatuh_tempo'     => ! empty($data['jatuh_tempo']) ? Carbon::parse($data['jatuh_tempo'])->endOfDay() : null,
            'catatan'         => $data['catatan'] ?? null,
            'metode_login'    => 'pppoe',
        ]);
    }

    private function importHotspotRow(array $data): void
    {
        $required = ['username', 'customer_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Kolom '{$field}' wajib diisi.");
            }
        }

        HotspotUser::create([
            'owner_id'          => $data['owner_id'],
            'username'          => $data['username'],
            'hotspot_password'  => $data['hotspot_password'] ?? '',
            'customer_name'     => $data['customer_name'],
            'customer_id'       => $data['customer_id'] ?? null,
            'nik'               => $data['nik'] ?? null,
            'nomor_hp'          => $data['nomor_hp'] ?? null,
            'email'             => $data['email'] ?? null,
            'alamat'            => $data['alamat'] ?? null,
            'status_akun'       => in_array($data['status_akun'] ?? '', ['enable', 'disable', 'isolir']) ? $data['status_akun'] : 'enable',
            'status_bayar'      => in_array($data['status_bayar'] ?? '', ['sudah_bayar', 'belum_bayar']) ? $data['status_bayar'] : 'belum_bayar',
            'jatuh_tempo'       => ! empty($data['jatuh_tempo']) ? Carbon::parse($data['jatuh_tempo'])->endOfDay() : null,
            'catatan'           => $data['catatan'] ?? null,
        ]);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }

    private function formatDuration(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }
}
