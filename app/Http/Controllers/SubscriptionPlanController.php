<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubscriptionPlanController extends Controller
{
    public function index()
    {
        $plans = SubscriptionPlan::orderBy('sort_order')->get();

        return view('subscription-plans.index', compact('plans'));
    }

    public function create()
    {
        return view('subscription-plans.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'max_mikrotik' => 'required|integer|min:-1',
            'max_ppp_users' => 'required|integer|min:-1',
            'features' => 'nullable|array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        // Check if slug already exists
        $slugCount = SubscriptionPlan::where('slug', 'like', $validated['slug'] . '%')->count();
        if ($slugCount > 0) {
            $validated['slug'] .= '-' . ($slugCount + 1);
        }

        SubscriptionPlan::create($validated);

        return redirect()->route('subscription-plans.index')
            ->with('success', 'Paket langganan berhasil dibuat.');
    }

    public function edit(SubscriptionPlan $subscriptionPlan)
    {
        return view('subscription-plans.edit', ['plan' => $subscriptionPlan]);
    }

    public function update(Request $request, SubscriptionPlan $subscriptionPlan)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'max_mikrotik' => 'required|integer|min:-1',
            'max_ppp_users' => 'required|integer|min:-1',
            'features' => 'nullable|array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $subscriptionPlan->update($validated);

        return redirect()->route('subscription-plans.index')
            ->with('success', 'Paket langganan berhasil diperbarui.');
    }

    public function destroy(SubscriptionPlan $subscriptionPlan)
    {
        // Check if there are active subscriptions
        if ($subscriptionPlan->subscriptions()->active()->exists()) {
            return back()->with('error', 'Tidak dapat menghapus paket yang masih memiliki langganan aktif.');
        }

        $subscriptionPlan->delete();

        return redirect()->route('subscription-plans.index')
            ->with('success', 'Paket langganan berhasil dihapus.');
    }

    public function toggleActive(SubscriptionPlan $subscriptionPlan)
    {
        $subscriptionPlan->update(['is_active' => !$subscriptionPlan->is_active]);

        return back()->with('success', 'Status paket langganan berhasil diubah.');
    }
}
