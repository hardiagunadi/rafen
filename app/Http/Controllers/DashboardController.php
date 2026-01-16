<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\MikrotikConnection;
use App\Models\PppUser;
use App\Models\RadiusAccount;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $pppAccounts = RadiusAccount::query()->where('service', 'pppoe')->where('is_active', true)->count();
        $hotspotAccounts = RadiusAccount::query()->where('service', 'hotspot')->where('is_active', true)->count();

        $routers = MikrotikConnection::query()->select('is_online')->get();
        $systemInfo = $this->systemMetrics();
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        $invoicesMonth = Invoice::query()
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->get();
        $incomeToday = $invoicesMonth->where('status', 'paid')->whereBetween('updated_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()])->sum('total');
        $invoiceCountMonth = $invoicesMonth->count();

        $stats = [
            'income_today' => $incomeToday,
            'invoice_count' => $invoiceCountMonth,
            'ppp_online' => $pppAccounts,
            'hotspot_online' => $hotspotAccounts,
            'router_total' => $routers->count(),
            'router_online' => $routers->where('is_online', true)->count(),
            'router_offline' => $routers->where('is_online', false)->count(),
            'ppp_users' => PppUser::query()->count(),
        ];

        $serviceInfo = Collection::make([
            [
                'label' => 'CORE RADIUS',
                'status' => 'Running',
                'color' => 'success',
                'action' => 'Restart',
                'action_route' => route('radius.restart'),
                'count' => null,
            ],
            [
                'label' => 'MIKROTIK',
                'status' => $stats['router_online'].' / '.$stats['router_total'].' online',
                'color' => $stats['router_online'] > 0 ? 'info' : 'danger',
                'action' => null,
                'count' => $stats['router_total'],
            ],
            [
                'label' => 'SESSION',
                'status' => ($stats['ppp_online'] + $stats['hotspot_online']).' akun aktif',
                'color' => 'info',
                'action' => null,
                'count' => $stats['ppp_online'] + $stats['hotspot_online'],
            ],
            [
                'label' => 'PELANGGAN',
                'status' => $stats['ppp_users'].' terdaftar',
                'color' => 'primary',
                'action' => null,
                'count' => $stats['ppp_users'],
            ],
        ]);

        $owners = User::query()->orderBy('name')->get();

        return view('dashboard', compact('stats', 'serviceInfo', 'owners', 'systemInfo'));
    }

    public function apiDashboard(Request $request): View
    {
        $connections = MikrotikConnection::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $selectedConnection = $this->resolveConnection($request->integer('connection_id'), $connections);
        $resource = $this->apiDashboardPayload($selectedConnection);

        return view('api-dashboard', compact('connections', 'selectedConnection', 'resource'));
    }

    public function apiDashboardData(Request $request): JsonResponse
    {
        $connection = $this->resolveConnection($request->integer('connection_id'));

        return response()->json([
            'data' => $this->apiDashboardPayload($connection),
        ]);
    }

    public function restartRadius(Request $request): JsonResponse|RedirectResponse
    {
        $command = config('radius.reload_command', 'systemctl reload freeradius');
        $result = Process::timeout(30)->run($command);
        $error = trim($result->errorOutput() ?: $result->output());

        if ($request->wantsJson()) {
            if ($result->successful()) {
                return response()->json(['status' => 'ok', 'message' => 'Core Radius berhasil direload.']);
            }

            return response()->json(['status' => 'error', 'message' => 'Gagal reload Core Radius: '.$error], 500);
        }

        if ($result->successful()) {
            return redirect()->route('dashboard')->with('status', 'Core Radius berhasil direload.');
        }

        return redirect()->route('dashboard')->with('error', 'Gagal reload Core Radius: '.$error);
    }

    /**
     * @return array<string, string>
     */
    private function systemMetrics(): array
    {
        return [
            'uptime' => $this->formatUptime($this->uptimeSeconds()),
            'ram_total' => $this->formatBytes($this->memoryInfo('MemTotal')),
            'ram_free' => $this->formatBytes($this->memoryInfo('MemAvailable')),
            'disk_total' => $this->formatBytes(@disk_total_space('/') ?: 0),
            'disk_free' => $this->formatBytes(@disk_free_space('/') ?: 0),
        ];
    }

    /**
     * @return array{uptime:int, ram_total:int, ram_free:int, disk_total:int, disk_free:int}
     */
    private function systemMetricsRaw(): array
    {
        return [
            'uptime' => $this->uptimeSeconds(),
            'ram_total' => $this->memoryInfo('MemTotal'),
            'ram_free' => $this->memoryInfo('MemAvailable'),
            'disk_total' => (int) (@disk_total_space('/') ?: 0),
            'disk_free' => (int) (@disk_free_space('/') ?: 0),
        ];
    }

    private function uptimeSeconds(): int
    {
        $contents = @file_get_contents('/proc/uptime');

        if (! $contents) {
            return 0;
        }

        $parts = explode(' ', trim($contents));

        return (int) ($parts[0] ?? 0);
    }

    private function memoryInfo(string $key): int
    {
        $contents = @file('/proc/meminfo');
        if (! $contents) {
            return 0;
        }

        foreach ($contents as $line) {
            if (str_starts_with($line, $key)) {
                $parts = preg_split('/\s+/', trim($line));
                $valueKb = (int) ($parts[1] ?? 0);

                return $valueKb * 1024;
            }
        }

        return 0;
    }

    /**
     * @return array{model:?string, cores:int, mhz:?int}
     */
    private function cpuMetrics(): array
    {
        $contents = @file('/proc/cpuinfo');
        if (! $contents) {
            return [
                'model' => null,
                'cores' => 1,
                'mhz' => null,
            ];
        }

        $model = null;
        $mhz = null;
        $cores = 0;
        foreach ($contents as $line) {
            if (str_starts_with($line, 'model name')) {
                $model = trim(explode(':', $line, 2)[1] ?? '');
            }
            if (str_starts_with($line, 'cpu MHz')) {
                $value = trim(explode(':', $line, 2)[1] ?? '');
                $mhz = is_numeric($value) ? (int) round((float) $value) : $mhz;
            }
            if (str_starts_with($line, 'processor')) {
                $cores++;
            }
        }

        return [
            'model' => $model ?: null,
            'cores' => max(1, $cores),
            'mhz' => $mhz,
        ];
    }

    private function cpuLoadPercent(int $cores): ?float
    {
        $load = sys_getloadavg();
        if (! $load || $cores <= 0) {
            return null;
        }

        return min(100, max(0, ($load[0] / $cores) * 100));
    }

    private function formatUptime(int $seconds): string
    {
        if ($seconds <= 0) {
            return 'N/A';
        }

        $days = intdiv($seconds, 86400);
        $seconds %= 86400;
        $hours = intdiv($seconds, 3600);
        $seconds %= 3600;
        $minutes = intdiv($seconds, 60);

        $parts = [];
        if ($days > 0) {
            $parts[] = $days.'d';
        }
        if ($hours > 0) {
            $parts[] = $hours.'h';
        }
        if ($minutes > 0) {
            $parts[] = $minutes.'m';
        }

        return implode(' ', $parts) ?: '0m';
    }

    private function formatPercent(?float $value): string
    {
        if ($value === null) {
            return 'N/A';
        }

        return number_format($value, 3, '.', '').'%';
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return number_format($value, 1).' '.$units[$power];
    }

    private function buildTimestamp(): ?int
    {
        $candidates = [
            base_path('bootstrap/cache/config.php'),
            base_path('composer.lock'),
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                $timestamp = filemtime($path);

                return $timestamp ?: null;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, MikrotikConnection>|null  $connections
     */
    private function resolveConnection(?int $connectionId, ?Collection $connections = null): ?MikrotikConnection
    {
        if ($connections) {
            if ($connectionId) {
                return $connections->firstWhere('id', $connectionId) ?? $connections->first();
            }

            return $connections->first();
        }

        $query = MikrotikConnection::query()->where('is_active', true)->orderBy('name');
        if ($connectionId) {
            $selected = $query->whereKey($connectionId)->first();

            return $selected ?: MikrotikConnection::query()->where('is_active', true)->orderBy('name')->first();
        }

        return $query->first();
    }

    /**
     * @return array{
     *     platform_vendor:string,
     *     platform_model:string,
     *     routeros:string,
     *     cpu_type:string,
     *     cpu_cores:string,
     *     cpu_mhz:string,
     *     cpu_load:string,
     *     ram_free_percent:string,
     *     disk_free_percent:string,
     *     build_date:string,
     *     build_time:string,
     *     uptime:string
     * }
     */
    private function apiDashboardPayload(?MikrotikConnection $connection): array
    {
        $systemRaw = $this->systemMetricsRaw();
        $cpu = $this->cpuMetrics();
        $cpuLoad = $this->cpuLoadPercent($cpu['cores']);
        $ramPercent = $systemRaw['ram_total'] > 0
            ? ($systemRaw['ram_free'] / $systemRaw['ram_total']) * 100
            : null;
        $diskPercent = $systemRaw['disk_total'] > 0
            ? ($systemRaw['disk_free'] / $systemRaw['disk_total']) * 100
            : null;
        $buildTimestamp = $this->buildTimestamp();

        $platformModel = $connection?->name ?? 'Belum ada router';
        $routeros = $connection?->ros_version ? 'ROS '.$connection->ros_version : 'N/A';

        return [
            'platform_vendor' => 'MikroTik',
            'platform_model' => $platformModel,
            'routeros' => $routeros,
            'cpu_type' => $cpu['model'] ?? 'N/A',
            'cpu_cores' => $cpu['cores'].' core(s)',
            'cpu_mhz' => $cpu['mhz'] ? $cpu['mhz'].' MHz' : 'N/A',
            'cpu_load' => $this->formatPercent($cpuLoad),
            'ram_free_percent' => $this->formatPercent($ramPercent),
            'disk_free_percent' => $this->formatPercent($diskPercent),
            'build_date' => $buildTimestamp ? date('Y-m-d', $buildTimestamp) : 'N/A',
            'build_time' => $buildTimestamp ? date('H:i:s', $buildTimestamp) : 'N/A',
            'uptime' => $this->formatUptime($systemRaw['uptime']),
        ];
    }
}
