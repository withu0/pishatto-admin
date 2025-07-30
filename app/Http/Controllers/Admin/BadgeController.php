<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BadgeController extends Controller
{
    /**
     * Display a listing of the badges.
     */
    public function index(Request $request): Response
    {
        $query = Badge::query();

        // Search functionality
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $badges = $query->orderBy('created_at', 'desc')->paginate(20);

        return Inertia::render('admin/badges', [
            'badges' => $badges,
            'filters' => $request->only(['search'])
        ]);
    }

    /**
     * Show the form for creating a new badge.
     */
    public function create(): Response
    {
        return Inertia::render('admin/badges/create');
    }

    /**
     * Store a newly created badge in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:badges,name',
            'icon' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);

        Badge::create($validated);

        return redirect()->route('admin.badges.index')
            ->with('success', 'バッジが正常に作成されました。');
    }

    /**
     * Display the specified badge.
     */
    public function show(Badge $badge): Response
    {
        return Inertia::render('admin/badges/show', [
            'badge' => $badge
        ]);
    }

    /**
     * Show the form for editing the specified badge.
     */
    public function edit(Badge $badge): Response
    {
        return Inertia::render('admin/badges/edit', [
            'badge' => $badge
        ]);
    }

    /**
     * Update the specified badge in storage.
     */
    public function update(Request $request, Badge $badge)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:badges,name,' . $badge->id,
            'icon' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);

        $badge->update($validated);

        return redirect()->route('admin.badges.index')
            ->with('success', 'バッジが正常に更新されました。');
    }

    /**
     * Remove the specified badge from storage.
     */
    public function destroy(Badge $badge)
    {
        $badge->delete();

        return redirect()->route('admin.badges.index')
            ->with('success', 'バッジが正常に削除されました。');
    }
}