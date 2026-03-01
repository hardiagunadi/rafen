<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\LoginLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LogController extends Controller
{
    // ── Log Login ─────────────────────────────────────────────────────────────

    public function loginIndex(): View
    {
        return view('logs.login');
    }

    public function loginDatatable(Request $request): JsonResponse
    {
        $search = $request->input('search.value', $request->input('search', ''));
        $query = LoginLog::with('user')
            ->when($request->filled('event'), fn($q) => $q->where('event', $request->event))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(fn($q2) => $q2->where('email', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%"));
            })
            ->orderByDesc('created_at');

        $total    = LoginLog::count();
        $filtered = $query->count();
        $rows     = $query->offset($request->integer('start'))
            ->limit(max(1, $request->integer('length', 20)))
            ->get();

        return response()->json([
            'draw'            => $request->integer('draw'),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $rows->map(fn($r) => [
                'id'         => $r->id,
                'event'      => $r->event,
                'email'      => $r->email ?? '-',
                'name'       => $r->user?->name ?? '-',
                'ip_address' => $r->ip_address ?? '-',
                'user_agent' => $r->user_agent ?? '-',
                'created_at' => $r->created_at?->format('Y-m-d H:i:s') ?? '-',
            ]),
        ]);
    }

    // ── Log Aktivitas User ────────────────────────────────────────────────────

    public function activityIndex(): View
    {
        return view('logs.activity');
    }

    public function activityData(Request $request): JsonResponse
    {
        $user   = auth()->user();
        $search = $request->input('search.value', $request->input('search', ''));

        $query = ActivityLog::with('user')
            ->when(! $user->isSuperAdmin(), fn($q) => $q->where('owner_id', $user->id))
            ->when($request->filled('action'), fn($q) => $q->where('action', $request->action))
            ->when($request->filled('subject_type'), fn($q) => $q->where('subject_type', $request->subject_type))
            ->when($search !== '', fn($q) => $q->where(function ($q2) use ($search) {
                $q2->where('subject_label', 'like', "%{$search}%")
                   ->orWhereHas('user', fn($q3) => $q3->where('name', 'like', "%{$search}%")
                       ->orWhere('email', 'like', "%{$search}%"));
            }))
            ->orderByDesc('created_at');

        $total    = (clone $query)->count();
        $filtered = $total;
        $rows     = $query->offset($request->integer('start'))
            ->limit(max(1, $request->integer('length', 25)))
            ->get();

        return response()->json([
            'draw'            => $request->integer('draw'),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $rows->map(fn($r) => [
                'id'            => $r->id,
                'created_at'    => $r->created_at?->format('Y-m-d H:i:s') ?? '-',
                'user_name'     => $r->user?->name ?? '-',
                'user_email'    => $r->user?->email ?? '-',
                'action'        => $r->action,
                'subject_type'  => $r->subject_type,
                'subject_label' => $r->subject_label ?? '-',
                'ip_address'    => $r->ip_address ?? '-',
            ]),
        ]);
    }

    // ── Log BG Process (jobs & failed_jobs) ───────────────────────────────────

    public function bgProcessIndex(): View
    {
        $stats = [
            'pending'    => \DB::table('jobs')->count(),
            'failed'     => \DB::table('failed_jobs')->count(),
            'batches'    => \DB::table('job_batches')->count(),
        ];

        return view('logs.bg-process', compact('stats'));
    }

    public function bgProcessDatatable(Request $request): JsonResponse
    {
        $type = $request->input('type', 'failed');

        $search = $request->input('search.value', $request->input('search', ''));

        if ($type === 'pending') {
            $query = \DB::table('jobs')
                ->when($search !== '', fn($q) => $q->where('queue', 'like', '%'.$search.'%'))
                ->orderByDesc('created_at');

            $total    = \DB::table('jobs')->count();
            $filtered = $query->count();
            $rows     = $query->offset($request->integer('start'))
                ->limit(max(1, $request->integer('length', 20)))->get();

            $data = $rows->map(fn($r) => [
                'id'           => $r->id,
                'queue'        => $r->queue,
                'attempts'     => $r->attempts,
                'payload_name' => $this->extractJobName($r->payload),
                'created_at'   => date('Y-m-d H:i:s', $r->created_at),
                'available_at' => date('Y-m-d H:i:s', $r->available_at),
            ]);
        } else {
            $query = \DB::table('failed_jobs')
                ->when($search !== '', fn($q) => $q->where('queue', 'like', '%'.$search.'%')
                    ->orWhere('exception', 'like', '%'.$search.'%'))
                ->orderByDesc('failed_at');

            $total    = \DB::table('failed_jobs')->count();
            $filtered = $query->count();
            $rows     = $query->offset($request->integer('start'))
                ->limit(max(1, $request->integer('length', 20)))->get();

            $data = $rows->map(fn($r) => [
                'id'           => $r->id,
                'uuid'         => $r->uuid,
                'queue'        => $r->queue,
                'payload_name' => $this->extractJobName($r->payload),
                'exception'    => mb_substr($r->exception, 0, 200),
                'failed_at'    => $r->failed_at,
            ]);
        }

        return response()->json([
            'draw'            => $request->integer('draw'),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $data,
        ]);
    }

    // ── Log Auth Radius ───────────────────────────────────────────────────────

    public function radiusAuthIndex(): View
    {
        return view('logs.radius-auth');
    }

    public function radiusAuthDatatable(Request $request): JsonResponse
    {
        $search = $request->input('search.value', $request->input('search', ''));

        $query = \DB::table('radpostauth')
            ->when($request->filled('reply'), fn($q) => $q->where('reply', $request->reply))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(fn($q2) => $q2->where('username', 'like', "%{$search}%")
                    ->orWhere('reply', 'like', "%{$search}%"));
            })
            ->orderByDesc('authdate');

        $total    = \DB::table('radpostauth')->count();
        $filtered = $query->count();
        $rows     = $query->offset($request->integer('start'))
            ->limit(max(1, $request->integer('length', 20)))->get();

        return response()->json([
            'draw'            => $request->integer('draw'),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $rows->map(fn($r) => [
                'id'       => $r->id,
                'username' => $r->username,
                'reply'    => $r->reply,
                'authdate' => $r->authdate,
            ]),
        ]);
    }

    // ── Log WA Blast ─────────────────────────────────────────────────────────

    public function waBlastIndex(): View
    {
        return view('logs.wa-blast');
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function extractJobName(string $payload): string
    {
        $data = json_decode($payload, true);
        if (isset($data['displayName'])) {
            return class_basename($data['displayName']);
        }
        if (isset($data['job'])) {
            return class_basename($data['job']);
        }

        return '-';
    }
}
