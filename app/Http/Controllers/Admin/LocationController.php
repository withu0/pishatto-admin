<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LocationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $locations = Location::orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/locations/index', [
            'locations' => $locations
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('admin/locations/create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'prefecture' => 'required|string|max:50',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0'
        ]);

        Location::create($validated);

        return redirect()->route('admin.locations.index')
            ->with('success', 'ロケーションが正常に作成されました。');
    }

    /**
     * Display the specified resource.
     */
    public function show(Location $location)
    {
        $location->load(['guests', 'castMembers']);

        return Inertia::render('admin/locations/show', [
            'location' => $location
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Location $location)
    {
        return Inertia::render('admin/locations/edit', [
            'location' => $location
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Location $location)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'prefecture' => 'required|string|max:50',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0'
        ]);

        $location->update($validated);

        return redirect()->route('admin.locations.index')
            ->with('success', 'ロケーションが正常に更新されました。');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Location $location)
    {
        // Check if location is being used
        if ($location->guests()->count() > 0 || $location->castMembers()->count() > 0) {
            return redirect()->route('admin.locations.index')
                ->with('error', 'このロケーションは使用中のため削除できません。');
        }

        $location->delete();

        return redirect()->route('admin.locations.index')
            ->with('success', 'ロケーションが正常に削除されました。');
    }

    /**
     * Get all active locations for API
     */
    public function getActiveLocations()
    {
        $locations = Location::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name');

        return response()->json($locations);
    }

    /**
     * Get prefectures grouped by location for API
     */
    public function getPrefecturesByLocation()
    {
        $locations = Location::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['name', 'prefecture']);

        $grouped = [];
        foreach ($locations as $location) {
            if (!isset($grouped[$location->name])) {
                $grouped[$location->name] = [];
            }
            if ($location->prefecture && !in_array($location->prefecture, $grouped[$location->name])) {
                $grouped[$location->name][] = $location->prefecture;
            }
        }

        return response()->json($grouped);
    }
} 