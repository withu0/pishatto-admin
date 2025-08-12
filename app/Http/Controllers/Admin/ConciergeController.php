<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConciergeMessage;
use App\Models\Guest;
use App\Models\Cast;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class ConciergeController extends Controller
{
    /**
     * Display a listing of concierge messages
     */
    public function index(Request $request): Response
    {
        $query = ConciergeMessage::with(['user', 'assignedAdmin'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        if ($request->filled('message_type')) {
            $query->byType($request->message_type);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                  ->orWhere('admin_notes', 'like', "%{$search}%");
            });
        }

        $effectiveUserType = 'guest';
        if ($request->filled('user_type')) {
            $effectiveUserType = $request->user_type;
        }
        $query->where('user_type', $effectiveUserType);

        $messages = $query->paginate(20);

        // Get statistics
        $stats = [
            'total' => ConciergeMessage::count(),
            'pending' => ConciergeMessage::pending()->count(),
            'urgent' => ConciergeMessage::urgent()->count(),
            'resolved' => ConciergeMessage::byStatus('resolved')->count(),
        ];

        // Counts of new (pending) messages by user type for tab badges
        $newCounts = [
            'guest' => ConciergeMessage::where('user_type', 'guest')->where('is_concierge', false)->where('status', 'pending')->count(),
            'cast' => ConciergeMessage::where('user_type', 'cast')->where('is_concierge', false)->where('status', 'pending')->count(),
        ];

        return Inertia::render('admin/concierge/index', [
            'messages' => $messages,
            'stats' => $stats,
            'newCounts' => $newCounts,
            'filters' => array_merge(
                $request->only(['status', 'message_type', 'category', 'search']),
                ['user_type' => $effectiveUserType]
            ),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new concierge message
     */
    public function create(): Response
    {
        $guests = Guest::select('id', 'nickname', 'phone')->get();
        $casts = Cast::select('id', 'nickname', 'phone')->get();

        return Inertia::render('admin/concierge/create', [
            'guests' => $guests,
            'casts' => $casts,
        ]);
    }

    /**
     * Store a newly created concierge message
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'user_type' => 'required|in:guest,cast',
            'message' => 'required|string|max:1000',
            'message_type' => 'required|in:inquiry,support,reservation,payment,technical,general',
            'category' => 'required|in:urgent,normal,low',
            'status' => 'required|in:pending,in_progress,resolved,closed',
            'admin_notes' => 'nullable|string',
            'is_concierge' => 'boolean',
        ]);

        $message = ConciergeMessage::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'コンシェルジュメッセージが作成されました。',
            'data' => $message,
        ]);
    }

    /**
     * Display the specified concierge message
     */
    public function show(ConciergeMessage $concierge): Response
    {
        $concierge->load(['user', 'assignedAdmin']);

        return Inertia::render('admin/concierge/show', [
            'message' => $concierge,
        ]);
    }

    /**
     * Show the form for editing the specified concierge message
     */
    public function edit(ConciergeMessage $concierge): Response
    {
        $concierge->load(['user', 'assignedAdmin']);
        $guests = Guest::select('id', 'nickname', 'phone')->get();
        $casts = Cast::select('id', 'nickname', 'phone')->get();

        return Inertia::render('admin/concierge/edit', [
            'message' => $concierge,
            'guests' => $guests,
            'casts' => $casts,
        ]);
    }

    /**
     * Update the specified concierge message
     */
    public function update(Request $request, ConciergeMessage $concierge)
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|integer',
                'user_type' => 'required|in:guest,cast',
                'message' => 'required|string|max:1000',
                'message_type' => 'required|in:inquiry,support,reservation,payment,technical,general',
                'category' => 'required|in:urgent,normal,low',
                'status' => 'required|in:pending,in_progress,resolved,closed',
                'admin_notes' => 'nullable|string',
                'assigned_admin_id' => 'nullable|integer|exists:users,id',
                'is_concierge' => 'boolean',
            ]);

            // Set resolved_at if status is resolved
            if ($validated['status'] === 'resolved' && $concierge->status !== 'resolved') {
                $validated['resolved_at'] = now();
            }

            $concierge->update($validated);

            return redirect()->route('admin.concierge.show', $concierge)
                ->with('success', 'コンシェルジュメッセージが更新されました。');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => '更新に失敗しました: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified concierge message
     */
    public function destroy(ConciergeMessage $concierge)
    {
        try {
            $concierge->delete();

            return redirect()->route('admin.concierge.index')
                ->with('success', 'コンシェルジュメッセージが削除されました。');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => '削除に失敗しました: ' . $e->getMessage()]);
        }
    }

    /**
     * Update message status
     */
    public function updateStatus(Request $request, ConciergeMessage $concierge): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,in_progress,resolved,closed',
            'admin_notes' => 'nullable|string',
        ]);

        $data = $validated;

        // Set resolved_at if status is resolved
        if ($validated['status'] === 'resolved' && $concierge->status !== 'resolved') {
            $data['resolved_at'] = now();
        }

        $concierge->update($data);

        return response()->json([
            'success' => true,
            'message' => 'ステータスが更新されました。',
            'data' => $concierge->fresh(),
        ]);
    }

    /**
     * Assign admin to message
     */
    public function assignAdmin(Request $request, ConciergeMessage $concierge): JsonResponse
    {
        $validated = $request->validate([
            'assigned_admin_id' => 'required|integer|exists:users,id',
        ]);

        $concierge->update($validated);

        return response()->json([
            'success' => true,
            'message' => '担当者が割り当てられました。',
            'data' => $concierge->fresh(),
        ]);
    }

    /**
     * Get concierge statistics
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total' => ConciergeMessage::count(),
            'pending' => ConciergeMessage::pending()->count(),
            'urgent' => ConciergeMessage::urgent()->count(),
            'resolved' => ConciergeMessage::byStatus('resolved')->count(),
            'by_type' => ConciergeMessage::selectRaw('message_type, count(*) as count')
                ->groupBy('message_type')
                ->pluck('count', 'message_type'),
            'by_status' => ConciergeMessage::selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
