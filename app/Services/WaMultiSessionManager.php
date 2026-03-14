<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

class WaMultiSessionManager
{
    public function ensureRunning(): array
    {
        $status = $this->status();

        if (($status['running'] ?? false) === true) {
            return [
                'success' => true,
                'message' => 'wa-multi-session sudah berjalan.',
                'data' => $status,
            ];
        }

        $start = Process::timeout(30)->run($this->buildShellCommand($this->startCommand()));

        if (! $start->successful()) {
            return [
                'success' => false,
                'message' => 'Gagal menjalankan wa-multi-session via PM2: '.trim($start->errorOutput() ?: $start->output()),
                'data' => $this->status(),
            ];
        }

        usleep(600000);

        return [
            'success' => true,
            'message' => 'wa-multi-session berhasil dijalankan via PM2.',
            'data' => $this->status(),
        ];
    }

    public function status(): array
    {
        $process = $this->findProcess();

        return [
            'running' => $process !== null && ($process['pm2_env']['status'] ?? null) === 'online',
            'name' => (string) config('wa.multi_session.pm2_name', 'wa-multi-session'),
            'host' => config('wa.multi_session.host'),
            'port' => (int) config('wa.multi_session.port', 3100),
            'url' => $this->baseUrl(),
            'pm2_bin' => (string) config('wa.multi_session.pm2_bin', 'pm2'),
            'pm2_pid' => $process['pid'] ?? null,
            'pm2_status' => $process['pm2_env']['status'] ?? 'stopped',
            'log_file' => (string) config('wa.multi_session.log_file', storage_path('logs/wa-multi-session.log')),
        ];
    }

    public function restart(): array
    {
        $name = $this->pm2Name();
        $pm2 = $this->pm2Bin();

        $restart = Process::timeout(20)->run($this->buildShellCommand("{$pm2} restart {$name} --update-env"));

        if (! $restart->successful()) {
            return $this->ensureRunning();
        }

        usleep(600000);

        return [
            'success' => true,
            'message' => 'wa-multi-session berhasil di-restart via PM2.',
            'data' => $this->status(),
        ];
    }

    private function findProcess(): ?array
    {
        $pm2 = $this->pm2Bin();
        $result = Process::timeout(15)->run($this->buildShellCommand("{$pm2} jlist"));

        if (! $result->successful()) {
            return null;
        }

        $list = json_decode((string) $result->output(), true);

        if (! is_array($list)) {
            return null;
        }

        $targetName = $this->pm2Name();

        foreach ($list as $item) {
            if (is_array($item) && ($item['name'] ?? null) === $targetName) {
                return $item;
            }
        }

        return null;
    }

    private function startCommand(): string
    {
        $path = (string) config('wa.multi_session.path', base_path('wa-multi-session'));
        $script = (string) config('wa.multi_session.script', 'gateway-server.cjs');
        $logFile = (string) config('wa.multi_session.log_file', storage_path('logs/wa-multi-session.log'));
        $dbConnection = (string) config('database.default', 'mysql');
        $dbConfig = (array) config('database.connections.'.$dbConnection, []);

        $env = [
            'WA_MS_HOST' => (string) config('wa.multi_session.host', '127.0.0.1'),
            'WA_MS_PORT' => (string) config('wa.multi_session.port', 3100),
            'WA_MS_AUTH_TOKEN' => (string) config('wa.multi_session.auth_token', ''),
            'WA_MS_MASTER_KEY' => (string) config('wa.multi_session.master_key', ''),
            'WA_MS_DB_HOST' => (string) ($dbConfig['host'] ?? '127.0.0.1'),
            'WA_MS_DB_PORT' => (string) ($dbConfig['port'] ?? 3306),
            'WA_MS_DB_NAME' => (string) ($dbConfig['database'] ?? ''),
            'WA_MS_DB_USER' => (string) ($dbConfig['username'] ?? ''),
            'WA_MS_DB_PASSWORD' => (string) ($dbConfig['password'] ?? ''),
            'WA_MS_DB_TABLE' => (string) config('wa.multi_session.db_table', 'wa_multi_session_auth_store'),
            'WA_MS_WEBHOOK_URL' => (string) config('wa.multi_session.webhook_url', ''),
        ];

        $exports = collect($env)
            ->map(fn (string $value, string $key): string => $key.'='.escapeshellarg($value))
            ->implode(' ');

        $pm2 = $this->pm2Bin();
        $name = $this->pm2Name();

        return 'cd '.escapeshellarg($path).' && env '.$exports.' '.$pm2
            .' start '.escapeshellarg($script)
            .' --name '.escapeshellarg($name)
            .' --time'
            .' --output '.escapeshellarg($logFile)
            .' --error '.escapeshellarg($logFile);
    }

    private function buildShellCommand(string $inner): string
    {
        return 'bash -lc '.escapeshellarg($inner);
    }

    private function baseUrl(): string
    {
        return 'http://'.config('wa.multi_session.host', '127.0.0.1').':'.(int) config('wa.multi_session.port', 3100);
    }

    private function pm2Name(): string
    {
        return (string) config('wa.multi_session.pm2_name', 'wa-multi-session');
    }

    private function pm2Bin(): string
    {
        return (string) config('wa.multi_session.pm2_bin', 'pm2');
    }
}
