<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CastController extends Controller
{
    /**
     * Display a listing of the casts.
     */
    public function index(Request $request): Response
    {
        $query = Cast::query();

        // Search functionality
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('nickname', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('line_id', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 10);
        $casts = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Add avatar_urls to each cast
        $casts->getCollection()->transform(function ($cast) {
            $cast->avatar_urls = $cast->avatar_urls;
            return $cast;
        });

        return Inertia::render('admin/casts', [
            'casts' => $casts,
            'filters' => $request->only(['search', 'per_page'])
        ]);
    }

    /**
     * Show the form for creating a new cast.
     */
    public function create(): Response
    {
        return Inertia::render('admin/casts/create');
    }

    /**
     * Store a newly created cast in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:20|unique:casts,phone',
            'line_id' => 'nullable|string|max:50|unique:casts,line_id',
            'nickname' => 'nullable|string|max:50',
            'avatar' => 'nullable|string|max:1000', // Increased for multiple paths
            'status' => 'nullable|string|in:active,inactive,suspended',
            'category' => 'nullable|in:プレミアム,VIP,ロイヤルVIP',
            'birth_year' => 'required|integer|min:1900|max:' . (date('Y') - 18),
            'height' => 'nullable|integer|min:100|max:250',
            'grade' => 'nullable|string|max:50',
            'grade_points' => 'nullable|integer|min:0',
            'residence' => 'nullable|string|max:100',
            'birthplace' => 'nullable|string|max:100',
            'profile_text' => 'nullable|string',
            'payjp_customer_id' => 'nullable|string|max:255',
            'payment_info' => 'nullable|string',
            'points' => 'nullable|integer|min:0',
        ]);

        // Convert avatars array to comma-separated string
        if (isset($validated['avatars']) && is_array($validated['avatars'])) {
            $validated['avatar'] = implode(',', $validated['avatars']);
            unset($validated['avatars']);
        }

        $cast = Cast::create($validated);

        return redirect()->route('admin.casts.index')
            ->with('success', 'キャストが正常に作成されました。');
    }

    /**
     * Display the specified cast.
     */
    public function show(Cast $cast): Response
    {
        // Load relationships (excluding badges for now)
        $cast->load(['likes', 'receivedGifts', 'favoritedBy']);

        // Add avatar_urls to the cast data
        $cast->avatar_urls = $cast->avatar_urls;

        return Inertia::render('admin/casts/show', [
            'cast' => $cast
        ]);
    }

    /**
     * Show the form for editing the specified cast.
     */
    public function edit(Cast $cast): Response
    {
        // Add avatar_urls to the cast data
        $cast->avatar_urls = $cast->avatar_urls;

        return Inertia::render('admin/casts/edit', [
            'cast' => $cast
        ]);
    }

    /**
     * Update the specified cast in storage.
     */
    public function update(Request $request, Cast $cast)
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:20|unique:casts,phone,' . $cast->id,
            'line_id' => 'nullable|string|max:50|unique:casts,line_id,' . $cast->id,
            'nickname' => 'nullable|string|max:50',
            'avatar' => 'nullable|string|max:1000', // Increased for multiple paths
            'status' => 'nullable|string|in:active,inactive,suspended',
            'category' => 'nullable|in:プレミアム,VIP,ロイヤルVIP',
            'birth_year' => 'nullable|integer|min:1900|max:' . (date('Y') - 18),
            'height' => 'nullable|integer|min:100|max:250',
            'grade' => 'nullable|string|max:50',
            'grade_points' => 'nullable|integer|min:0',
            'residence' => 'required|string|max:100',
            'birthplace' => 'nullable|string|max:100',
            'profile_text' => 'nullable|string',
            'payjp_customer_id' => 'nullable|string|max:255',
            'payment_info' => 'nullable|string',
            'points' => 'nullable|integer|min:0',
        ]);

        // Convert avatars array to comma-separated string
        if (isset($validated['avatars']) && is_array($validated['avatars'])) {
            $validated['avatar'] = implode(',', $validated['avatars']);
            unset($validated['avatars']);
        }

        $cast->update($validated);

        return redirect()->route('admin.casts.index')
            ->with('success', 'キャストが正常に更新されました。');
    }

    /**
     * Remove the specified cast from storage.
     */
    public function destroy(Cast $cast)
    {
        // Delete all avatar files if they exist
        if ($cast->avatar) {
            $avatars = explode(',', $cast->avatar);
            foreach ($avatars as $avatarPath) {
                $path = trim($avatarPath);
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
        }

        $cast->delete();

        return redirect()->route('admin.casts.index')
            ->with('success', 'キャストが正常に削除されました。');
    }

    /**
     * Upload avatar for cast
     */
    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatars.*' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:51200',
        ]);

        $uploadedPaths = [];

        if ($request->hasFile('avatars')) {
            foreach ($request->file('avatars') as $file) {
                $path = $file->store('avatars', 'public');
                $uploadedPaths[] = $path;
            }
        }

        return response()->json([
            'paths' => $uploadedPaths,
            'message' => count($uploadedPaths) . '個の画像がアップロードされました。'
        ]);
    }

    /**
     * Delete individual avatar from cast
     */
    public function deleteAvatar(Request $request, Cast $cast)
    {
        $request->validate([
            'avatar_index' => 'required|integer|min:0',
        ]);

        $avatarIndex = $request->input('avatar_index');

        if (!$cast->avatar) {
            return response()->json(['error' => 'No avatars found'], 404);
        }

        $avatars = explode(',', $cast->avatar);

        if ($avatarIndex >= count($avatars)) {
            return response()->json(['error' => 'Avatar index out of range'], 404);
        }

        // Get the avatar path to delete from storage
        $avatarPath = trim($avatars[$avatarIndex]);

        // Delete the file from storage
        if (Storage::disk('public')->exists($avatarPath)) {
            Storage::disk('public')->delete($avatarPath);
        }

        // Remove the avatar from the array
        unset($avatars[$avatarIndex]);
        $avatars = array_values($avatars); // Re-index array

        // Update the cast with the new avatar string
        $cast->update(['avatar' => implode(',', $avatars)]);

        return response()->json([
            'message' => 'Avatar deleted successfully',
            'remaining_avatars' => $avatars
        ]);
    }
}
