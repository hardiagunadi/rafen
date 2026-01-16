<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use SplFileObject;
use Throwable;

class FreeRadiusSettingsController extends Controller
{
    public function __construct(private Filesystem $filesystem) {}

    public function index(): View
    {
        $clientsPath = (string) config('radius.clients_path');
        $logPath = (string) config('radius.log_path');
        $syncStatus = $this->resolveSyncStatus($clientsPath);
        $logPayload = $this->readLogTail($logPath, 200);

        return view('settings.freeradius', [
            'clientsPath' => $clientsPath,
            'logPath' => $logPath,
            'syncStatus' => $syncStatus,
            'logPayload' => $logPayload,
        ]);
    }

    /**
     * @return array{status: string, updated_at: ?string, size: ?int, message: string}
     */
    private function resolveSyncStatus(string $clientsPath): array
    {
        if ($clientsPath === '') {
            return [
                'status' => 'unknown',
                'updated_at' => null,
                'size' => null,
                'message' => 'Path clients.conf belum diatur.',
            ];
        }

        if (! $this->filesystem->exists($clientsPath)) {
            return [
                'status' => 'missing',
                'updated_at' => null,
                'size' => null,
                'message' => 'File clients belum ditemukan.',
            ];
        }

        $size = $this->filesystem->size($clientsPath);
        $updatedAt = Carbon::createFromTimestamp($this->filesystem->lastModified($clientsPath))
            ->format('Y-m-d H:i:s');

        if ($size === 0) {
            return [
                'status' => 'empty',
                'updated_at' => $updatedAt,
                'size' => $size,
                'message' => 'File clients masih kosong.',
            ];
        }

        return [
            'status' => 'ok',
            'updated_at' => $updatedAt,
            'size' => $size,
            'message' => 'File clients terisi.',
        ];
    }

    /**
     * @return array{lines: array<int, string>, error: ?string}
     */
    private function readLogTail(string $path, int $limit): array
    {
        if ($path === '') {
            return [
                'lines' => [],
                'error' => 'Path log belum diatur.',
            ];
        }

        if (! $this->filesystem->exists($path)) {
            return [
                'lines' => [],
                'error' => 'File log tidak ditemukan.',
            ];
        }

        try {
            $file = new SplFileObject($path, 'r');
            $file->setFlags(SplFileObject::DROP_NEW_LINE);
            $buffer = [];

            foreach ($file as $line) {
                if ($line === null) {
                    continue;
                }

                $buffer[] = $line;
                if (count($buffer) > $limit) {
                    array_shift($buffer);
                }
            }

            return [
                'lines' => $buffer,
                'error' => null,
            ];
        } catch (FileNotFoundException $exception) {
            return [
                'lines' => [],
                'error' => 'File log tidak ditemukan.',
            ];
        } catch (Throwable $exception) {
            return [
                'lines' => [],
                'error' => $exception->getMessage(),
            ];
        }
    }
}
