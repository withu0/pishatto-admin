<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class GuestController extends Controller
{
    /**
     * Display a listing of the guests.
     */
    public function index(Request $request): Response
    {
        $query = Guest::query();

        // Search functionality
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('nickname', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('line_id', 'like', "%{$search}%");
            });
        }

        $guests = $query->orderBy('created_at', 'desc')->paginate(20);

        return Inertia::render('admin/guests', [
            'guests' => $guests,
            'filters' => $request->only(['search'])
        ]);
    }

    /**
     * Show the form for creating a new guest.
     */
    public function create(): Response
    {
        return Inertia::render('admin/guests/create');
    }

    /**
     * Store a newly created guest in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:20|unique:guests,phone',
            'line_id' => 'nullable|string|max:50|unique:guests,line_id',
            'nickname' => 'nullable|string|max:50',
            'age' => 'nullable|string|max:50',
            'shiatsu' => 'nullable|string|max:50',
            'location' => 'nullable|string|max:50',
            'avatar' => 'nullable|string|max:255',
            'birth_year' => 'nullable|integer|min:1900|max:' . (date('Y') - 18),
            'height' => 'nullable|integer|min:100|max:250',
            'residence' => 'nullable|string|max:100',
            'birthplace' => 'nullable|string|max:100',
            'annual_income' => 'nullable|string|max:100',
            'education' => 'nullable|string|max:100',
            'occupation' => 'nullable|string|max:100',
            'alcohol' => 'nullable|string|max:20',
            'tobacco' => 'nullable|string|max:30',
            'siblings' => 'nullable|string|max:100',
            'cohabitant' => 'nullable|string|max:100',
            'pressure' => 'nullable|in:weak,medium,strong',
            'favorite_area' => 'nullable|string|max:100',
            'interests' => 'nullable|array',
            'payjp_customer_id' => 'nullable|string|max:255',
            'payment_info' => 'nullable|string',
            'points' => 'nullable|integer|min:0',
            'identity_verification_completed' => 'nullable|in:pending,success,failed',
            'identity_verification' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive,suspended',
        ]);

        $guest = Guest::create($validated);

        return redirect()->route('admin.guests.index')
            ->with('success', 'ゲストが正常に作成されました。');
    }

    /**
     * Display the specified guest.
     */
    public function show(Guest $guest): Response
    {
        // Load relationships - only load relationships that actually exist
        $guest->load(['reservations', 'sentGifts.gift', 'favorites', 'pointTransactions', 'feedback']);

        return Inertia::render('admin/guests/show', [
            'guest' => $guest
        ]);
    }

    /**
     * Show the form for editing the specified guest.
     */
    public function edit(Guest $guest): Response
    {
        return Inertia::render('admin/guests/edit', [
            'guest' => $guest
        ]);
    }

    /**
     * Update the specified guest in storage.
     */
    public function update(Request $request, Guest $guest)
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:20|unique:guests,phone,' . $guest->id,
            'line_id' => 'nullable|string|max:50|unique:guests,line_id,' . $guest->id,
            'nickname' => 'nullable|string|max:50',
            'age' => 'nullable|string|max:50',
            'shiatsu' => 'nullable|string|max:50',
            'location' => 'nullable|string|max:50',
            'avatar' => 'nullable|string|max:255',
            'birth_year' => 'nullable|integer|min:1900|max:' . (date('Y') - 18),
            'height' => 'nullable|integer|min:100|max:250',
            'residence' => 'nullable|string|max:100',
            'birthplace' => 'nullable|string|max:100',
            'annual_income' => 'nullable|string|max:100',
            'education' => 'nullable|string|max:100',
            'occupation' => 'nullable|string|max:100',
            'alcohol' => 'nullable|string|max:20',
            'tobacco' => 'nullable|string|max:30',
            'siblings' => 'nullable|string|max:100',
            'cohabitant' => 'nullable|string|max:100',
            'pressure' => 'nullable|in:weak,medium,strong',
            'favorite_area' => 'nullable|string|max:100',
            'interests' => 'nullable|array',
            'payjp_customer_id' => 'nullable|string|max:255',
            'payment_info' => 'nullable|string',
            'points' => 'nullable|integer|min:0',
            'identity_verification_completed' => 'nullable|in:pending,success,failed',
            'identity_verification' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive,suspended',
        ]);

        $guest->update($validated);

        return redirect()->route('admin.guests.index')
            ->with('success', 'ゲストが正常に更新されました。');
    }

    /**
     * Remove the specified guest from storage.
     */
    public function destroy(Guest $guest)
    {
        // Delete avatar if exists
        if ($guest->avatar && Storage::disk('public')->exists($guest->avatar)) {
            Storage::disk('public')->delete($guest->avatar);
        }

        $guest->delete();

        return redirect()->route('admin.guests.index')
            ->with('success', 'ゲストが正常に削除されました。');
    }
}
