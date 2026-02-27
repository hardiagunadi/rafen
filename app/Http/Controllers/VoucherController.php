<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVoucherBatchRequest;
use App\Models\HotspotProfile;
use App\Models\Voucher;
use App\Services\VoucherGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VoucherController extends Controller
{
    public function __construct(private readonly VoucherGeneratorService $generator) {}

    public function index(Request $request): View
    {
        $perPage = (int) $request->input('per_page', 20);
        $search = $request->input('search');
        $status = $request->input('status');
        $batch = $request->input('batch');
        $currentUser = $request->user();

        $query = Voucher::query()->with(['hotspotProfile'])->accessibleBy($currentUser);

        if ($search) {
            $query->where('code', 'like', "%{$search}%");
        }

        if ($status && in_array($status, ['unused', 'used', 'expired'])) {
            $query->where('status', $status);
        }

        if ($batch) {
            $query->where('batch_name', $batch);
        }

        $vouchers = $query->latest()->paginate($perPage > 0 ? $perPage : 20)->withQueryString();

        $stats = [
            'unused'  => Voucher::query()->accessibleBy($currentUser)->where('status', 'unused')->count(),
            'used'    => Voucher::query()->accessibleBy($currentUser)->where('status', 'used')->count(),
            'expired' => Voucher::query()->accessibleBy($currentUser)->where('status', 'expired')->count(),
        ];

        $batches = Voucher::query()->accessibleBy($currentUser)->whereNotNull('batch_name')->distinct()->pluck('batch_name');
        $profiles = HotspotProfile::query()->orderBy('name')->get();

        return view('vouchers.index', compact('vouchers', 'stats', 'batches', 'profiles', 'perPage', 'search', 'status', 'batch'));
    }

    public function create(): View
    {
        $profiles = HotspotProfile::query()->orderBy('name')->get();

        return view('vouchers.create', compact('profiles'));
    }

    public function store(StoreVoucherBatchRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $profile = HotspotProfile::findOrFail($validated['hotspot_profile_id']);
        $currentUser = $request->user();

        $this->generator->generateBatch(
            profile: $profile,
            count: (int) $validated['jumlah'],
            batchName: $validated['batch_name'],
            owner: $currentUser
        );

        return redirect()->route('vouchers.index')->with('status', "Batch voucher '{$validated['batch_name']}' berhasil dibuat.");
    }

    public function printBatch(Request $request, string $batch): View
    {
        $currentUser = $request->user();
        $vouchers = Voucher::query()
            ->accessibleBy($currentUser)
            ->where('batch_name', $batch)
            ->with('hotspotProfile')
            ->get();

        return view('vouchers.print', compact('vouchers', 'batch'));
    }

    public function destroy(Voucher $voucher): JsonResponse|RedirectResponse
    {
        if ($voucher->status !== 'unused') {
            if (request()->wantsJson()) {
                return response()->json(['error' => 'Hanya voucher unused yang dapat dihapus.'], 422);
            }

            return redirect()->route('vouchers.index')->with('error', 'Hanya voucher unused yang dapat dihapus.');
        }

        $voucher->delete();

        if (request()->wantsJson()) {
            return response()->json(['status' => 'Voucher dihapus.']);
        }

        return redirect()->route('vouchers.index')->with('status', 'Voucher dihapus.');
    }

    public function bulkDestroy(Request $request): JsonResponse|RedirectResponse
    {
        $ids = $request->input('ids', []);
        if (! empty($ids)) {
            Voucher::query()->whereIn('id', $ids)->where('status', 'unused')->delete();
        }

        if ($request->wantsJson()) {
            return response()->json(['status' => 'Voucher terpilih dihapus.']);
        }

        return redirect()->route('vouchers.index')->with('status', 'Voucher terpilih dihapus.');
    }
}
