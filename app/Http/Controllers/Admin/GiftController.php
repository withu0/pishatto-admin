<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gift;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GiftController extends Controller
{
    /**
     * Display a listing of the gifts.
     */
    public function index(Request $request): Response
    {
        $query = Gift::query();

        // Search functionality
        if ($request->has('search') && $request->get('search') !== '') {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if ($request->has('category') && $request->get('category') !== '' && $request->get('category') !== 'all') {
            $query->where('category', $request->get('category'));
        }

        $gifts = $query->orderBy('created_at', 'desc')->paginate(20);

        return Inertia::render('admin/gifts/index', [
            'gifts' => $gifts,
            'filters' => $request->only(['search', 'category']),
            'categories' => [
                'standard' => '標準',
                'regional' => '地域限定',
                'grade' => 'グレード',
                'mygift' => 'マイギフト'
            ]
        ]);
    }

    /**
     * Show the form for creating a new gift.
     */
    public function create(): Response
    {
        return Inertia::render('admin/gifts/create', [
            'categories' => [
                'standard' => '標準',
                'regional' => '地域限定',
                'grade' => 'グレード',
                'mygift' => 'マイギフト'
            ]
        ]);
    }

    /**
     * Store a newly created gift in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'category' => 'required|in:standard,regional,grade,mygift',
            'points' => 'required|integer|min:0',
            'icon' => 'nullable|string|max:255',
        ]);

        Gift::create($validated);

        return redirect()->route('admin.gifts.index')
            ->with('success', 'ギフトが正常に作成されました。');
    }

    /**
     * Display the specified gift.
     */
    public function show(Gift $gift): Response
    {
        return Inertia::render('admin/gifts/show', [
            'gift' => $gift,
            'categories' => [
                'standard' => '標準',
                'regional' => '地域限定',
                'grade' => 'グレード',
                'mygift' => 'マイギフト'
            ]
        ]);
    }

    /**
     * Show the form for editing the specified gift.
     */
    public function edit(Gift $gift): Response
    {
        return Inertia::render('admin/gifts/edit', [
            'gift' => $gift,
            'categories' => [
                'standard' => '標準',
                'regional' => '地域限定',
                'grade' => 'グレード',
                'mygift' => 'マイギフト'
            ]
        ]);
    }

    /**
     * Update the specified gift in storage.
     */
    public function update(Request $request, Gift $gift)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'category' => 'required|in:standard,regional,grade,mygift',
            'points' => 'required|integer|min:0',
            'icon' => 'nullable|string|max:255',
        ]);

        $gift->update($validated);

        return redirect()->route('admin.gifts.index')
            ->with('success', 'ギフトが正常に更新されました。');
    }

    /**
     * Remove the specified gift from storage.
     */
    public function destroy(Gift $gift)
    {
        $gift->delete();

        return redirect()->route('admin.gifts.index')
            ->with('success', 'ギフトが正常に削除されました。');
    }
}
