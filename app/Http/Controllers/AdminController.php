<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ReservationApplication;
use App\Models\Reservation;
use App\Models\Cast;
use App\Models\Guest;
use App\Services\MatchingMessageService;
use Illuminate\Support\Facades\DB; // Added for DB facade
use Illuminate\Support\Facades\Log; 

class AdminController extends Controller
{
    public function dashboard()
    {
        $pendingApplications = ReservationApplication::with(['reservation.guest', 'cast'])
            ->where('status', 'pending')
            ->count();

        $totalReservations = Reservation::count();
        $activeReservations = Reservation::where('active', true)->count();
        $totalCasts = Cast::count();
        $totalGuests = Guest::count();

        return \Inertia\Inertia::render('dashboard', compact(
            'pendingApplications',
            'totalReservations',
            'activeReservations',
            'totalCasts',
            'totalGuests'
        ));
    }

    public function reservationApplications()
    {
        $applications = ReservationApplication::with(['reservation.guest', 'cast'])
            ->where('status', 'pending')
            ->orderBy('applied_at', 'asc')
            ->get()
            ->map(function ($application) {
                return [
                    'id' => $application->id,
                    'reservation' => [
                        'id' => $application->reservation->id,
                        'guest' => [
                            'id' => $application->reservation->guest->id,
                            'nickname' => $application->reservation->guest->nickname,
                            'avatar' => $application->reservation->guest->avatar_url,
                            'phone' => $application->reservation->guest->phone,
                            'age' => $application->reservation->guest->age,
                            'location' => $application->reservation->guest->location,
                            'residence' => $application->reservation->guest->residence,
                            'birthplace' => $application->reservation->guest->birthplace,
                            'occupation' => $application->reservation->guest->occupation,
                            'education' => $application->reservation->guest->education,
                            'annual_income' => $application->reservation->guest->annual_income,
                            'interests' => $application->reservation->guest->interests,
                            'points' => $application->reservation->guest->points,
                            'created_at' => $application->reservation->guest->created_at,
                            'total_reservations' => $application->reservation->guest->reservations()->count(),
                        ],
                        'scheduled_at' => $application->reservation->scheduled_at,
                        'location' => $application->reservation->location,
                        'duration' => $application->reservation->duration,
                        'details' => $application->reservation->details,
                        'type' => $application->reservation->type,
                    ],
                    'cast' => [
                        'id' => $application->cast->id,
                        'nickname' => $application->cast->nickname,
                        'avatar' => $application->cast->first_avatar_url,
                        'phone' => $application->cast->phone,
                        'name' => $application->cast->name,
                        'birth_year' => $application->cast->birth_year,
                        'height' => $application->cast->height,
                        'grade' => $application->cast->grade,
                        'grade_points' => $application->cast->grade_points,
                        'residence' => $application->cast->residence,
                        'birthplace' => $application->cast->birthplace,
                        'location' => $application->cast->location,
                        'profile_text' => $application->cast->profile_text,
                        'points' => $application->cast->points,
                        'status' => $application->cast->status,
                        'created_at' => $application->cast->created_at,
                    ],
                    'status' => $application->status,
                    'applied_at' => $application->applied_at,
                    'approved_at' => $application->approved_at,
                    'rejected_at' => $application->rejected_at,
                    'rejection_reason' => $application->rejection_reason,
                ];
            });

        return \Inertia\Inertia::render('admin/reservation-applications', compact('applications'));
    }

    public function approveApplication(Request $request, $applicationId)
    {
        $validated = $request->validate([
            'admin_id' => 'required|exists:users,id',
        ]);

        $application = ReservationApplication::with(['reservation', 'cast'])->findOrFail($applicationId);

        if ($application->status !== 'pending') {
            return response()->json([
                'message' => 'Application is not pending'
            ], 400);
        }

        DB::transaction(function () use ($application, $validated) {
            // Update application status
            $application->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $validated['admin_id'],
            ]);

            // Update reservation
            $reservation = $application->reservation;
            $existingCastIds = $reservation->cast_ids ?? [];
            // Only add cast_id if not already in the array
            if (!in_array($application->cast_id, $existingCastIds)) {
                $reservation->update([
                    'active' => false,
                    'cast_ids' => array_merge($existingCastIds, [$application->cast_id]), // Store as array for consistency
                    'cast_id' => $application->cast_id, // Store the selected cast ID in cast_id field
                ]);
            } else {
                $reservation->update([
                    'active' => false,
                    'cast_id' => $application->cast_id, // Store the selected cast ID in cast_id field
                ]);
            }

            if ($reservation->type !== 'free') {
                ReservationApplication::where('reservation_id', $reservation->id)
                    ->where('id', '!=', $application->id)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'rejected',
                        'rejected_at' => now(),
                        'rejected_by' => $validated['admin_id'],
                        'rejection_reason' => 'Another cast was approved for this reservation',
                    ]);
            }

            // Find existing chat group for this reservation and update cast_ids
            $chatGroup = \App\Models\ChatGroup::where('reservation_id', $reservation->id)->first();
            if ($chatGroup) {
                $existingCastIds = $chatGroup->cast_ids ?? [];
                // Only add cast_id if not already in the array
                if (!in_array($application->cast_id, $existingCastIds)) {
                    $chatGroup->update([
                        'cast_ids' => array_merge($existingCastIds, [$application->cast_id]),
                    ]);
                }
            } else {
                // Fallback: create new chat group if none exists
                $chatGroup = \App\Models\ChatGroup::create([
                    'reservation_id' => $reservation->id,
                    'cast_ids' => [$application->cast_id],
                    'name' => '予約 - ' . $reservation->location,
                    'created_at' => now(),
                ]);
            }

            // Create individual chat for backward compatibility
            $chat = \App\Models\Chat::create([
                'guest_id' => $reservation->guest_id,
                'cast_id' => $application->cast_id,
                'reservation_id' => $reservation->id,
                'group_id' => $chatGroup->id,
            ]);

            // Send automatic matching information message
            $matchingMessageService = app(MatchingMessageService::class);
            $matchingMessageService->sendMatchingMessage($reservation, $application->cast_id, $chat->id, $chatGroup->id);

            // Notify guest
            $guestNotification = \App\Models\Notification::create([
                'user_id' => $reservation->guest_id,
                'user_type' => 'guest',
                'type' => 'order_matched',
                'reservation_id' => $reservation->id,
                'cast_id' => $application->cast_id,
                'message' => '予約がキャストにマッチされました',
                'read' => false,
            ]);
            // Broadcast to guest
            event(new \App\Events\NotificationSent($guestNotification));

            // Notify approved cast
            $castNotification = \App\Models\Notification::create([
                'user_id' => $application->cast_id,
                'user_type' => 'cast',
                'type' => 'application_approved',
                'reservation_id' => $reservation->id,
                'message' => '予約の応募が承認されました',
                'read' => false,
            ]);
            // Broadcast to approved cast
            event(new \App\Events\NotificationSent($castNotification));

            // Notify rejected casts
            $rejectedApplications = ReservationApplication::where('reservation_id', $reservation->id)
                ->where('status', 'rejected')
                ->get();

            foreach ($rejectedApplications as $rejectedApp) {
                $rejectedNotification = \App\Models\Notification::create([
                    'user_id' => $rejectedApp->cast_id,
                    'user_type' => 'cast',
                    'type' => 'application_rejected',
                    'reservation_id' => $reservation->id,
                    'message' => '予約の応募が却下されました',
                    'read' => false,
                ]);
                // Broadcast to rejected cast
                event(new \App\Events\NotificationSent($rejectedNotification));
            }

            // Update rankings
            $rankingService = app(\App\Services\RankingService::class);
            $rankingService->updateRealTimeRankings($reservation->location ?? '全国');
        });

        return response()->json([
            'message' => 'Application approved successfully',
            'chat' => $chat ?? null,
            'chat_group' => $chatGroup ?? null,
            'reservation' => $application->reservation->fresh()
        ]);
    }

    public function rejectApplication(Request $request, $applicationId)
    {
        $validated = $request->validate([
            'admin_id' => 'required|exists:users,id',
            'rejection_reason' => 'nullable|string',
        ]);

        $application = ReservationApplication::findOrFail($applicationId);

        if ($application->status !== 'pending') {
            return response()->json([
                'message' => 'Application is not pending'
            ], 400);
        }

        $application->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by' => $validated['admin_id'],
            'rejection_reason' => $validated['rejection_reason'] ?? 'Application rejected by admin',
        ]);

        // Notify cast
        $notification = \App\Models\Notification::create([
            'user_id' => $application->cast_id,
            'user_type' => 'cast',
            'type' => 'application_rejected',
            'reservation_id' => $application->reservation_id,
            'message' => '予約の応募が却下されました',
            'read' => false,
        ]);

        return response()->json([
            'message' => 'Application rejected successfully'
        ]);
    }

    public function approveMultipleApplications(Request $request)
    {
        $validated = $request->validate([
            'admin_id' => 'required|exists:users,id',
            'reservation_id' => 'required|exists:reservations,id',
            'cast_ids' => 'required|array|min:1',
            'cast_ids.*' => 'exists:casts,id',
        ]);

        $reservation = \App\Models\Reservation::findOrFail($validated['reservation_id']);
        
        if ($reservation->type !== 'pishatto') {
            return response()->json([
                'message' => 'Multiple cast selection is only allowed for pishatto reservations'
            ], 400);
        }

        DB::transaction(function () use ($reservation, $validated) {
            // Update reservation with multiple cast IDs and store the first selected cast in cast_id field
            $reservation->update([
                'active' => false,
                'cast_ids' => $validated['cast_ids'],
                'cast_id' => $validated['cast_ids'][0], // Store the first selected cast ID in cast_id field
            ]);

            // Approve selected applications
            ReservationApplication::where('reservation_id', $reservation->id)
                ->whereIn('cast_id', $validated['cast_ids'])
                ->where('status', 'pending')
                ->update([
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approved_by' => $validated['admin_id'],
                ]);

            // Reject all other pending applications for this reservation
            ReservationApplication::where('reservation_id', $reservation->id)
                ->whereNotIn('cast_id', $validated['cast_ids'])
                ->where('status', 'pending')
                ->update([
                    'status' => 'rejected',
                    'rejected_at' => now(),
                    'rejected_by' => $validated['admin_id'],
                    'rejection_reason' => 'Other casts were selected for this reservation',
                ]);

            // Create chat group with multiple casts
            $chatGroup = \App\Models\ChatGroup::create([
                'reservation_id' => $reservation->id,
                'cast_ids' => $validated['cast_ids'],
                'name' => 'プレミアム予約 - ' . $reservation->location,
                'created_at' => now(),
            ]);

            // Create per-cast pending point transactions for this reservation if not already created
            $hasPending = \App\Models\PointTransaction::where('reservation_id', $reservation->id)
                ->where('type', 'pending')
                ->exists();
            if (!$hasPending) {
                /** @var \App\Services\PointTransactionService $pointService */
                $pointService = app(\App\Services\PointTransactionService::class);
                $requiredPoints = $pointService->calculateReservationPoints($reservation);

                $castIds = $validated['cast_ids'];
                $numCasts = count($castIds);
                if ($numCasts > 0 && $requiredPoints > 0) {
                    $baseShare = intdiv($requiredPoints, $numCasts);
                    $remainder = $requiredPoints % $numCasts;
                    foreach (array_values($castIds) as $index => $castId) {
                        $amount = $baseShare + ($index < $remainder ? 1 : 0);
                        \App\Models\PointTransaction::create([
                            'guest_id' => $reservation->guest_id,
                            'cast_id' => $castId,
                            'type' => 'pending',
                            'amount' => $amount,
                            'reservation_id' => $reservation->id,
                            'description' => "ピシャット予約 - {$reservation->duration} hours (pending)"
                        ]);
                    }
                }
            }

            // Create individual chats for each selected cast
            foreach ($validated['cast_ids'] as $castId) {
                \App\Models\Chat::create([
                    'reservation_id' => $reservation->id,
                    'guest_id' => $reservation->guest_id,
                    'cast_id' => $castId,
                    'group_id' => $chatGroup->id,
                    'created_at' => now(),
                ]);
            }

            // Send automatic matching information message for multiple casts
            $matchingMessageService = app(MatchingMessageService::class);
            $matchingMessageService->sendMultipleMatchingMessage($reservation, $validated['cast_ids'], $chatGroup->id);

            // Notify guest
            $guestNotification = \App\Models\Notification::create([
                'user_id' => $reservation->guest_id,
                'user_type' => 'guest',
                'type' => 'order_matched',
                'reservation_id' => $reservation->id,
                'message' => '予約が複数のキャストにマッチされました',
                'read' => false,
            ]);
            // Broadcast to guest
            event(new \App\Events\NotificationSent($guestNotification));

            // Notify approved casts
            foreach ($validated['cast_ids'] as $castId) {
                $approvedNotification = \App\Models\Notification::create([
                    'user_id' => $castId,
                    'user_type' => 'cast',
                    'type' => 'application_approved',
                    'reservation_id' => $reservation->id,
                    'message' => 'プレミアム予約の応募が承認されました',
                    'read' => false,
                ]);
                // Broadcast to approved cast
                event(new \App\Events\NotificationSent($approvedNotification));
            }

            // Notify rejected casts
            $rejectedApplications = ReservationApplication::where('reservation_id', $reservation->id)
                ->where('status', 'rejected')
                ->get();

            foreach ($rejectedApplications as $rejectedApp) {
                $rejectedNotification = \App\Models\Notification::create([
                    'user_id' => $rejectedApp->cast_id,
                    'user_type' => 'cast',
                    'type' => 'application_rejected',
                    'reservation_id' => $reservation->id,
                    'message' => '予約の応募が却下されました',
                    'read' => false,
                ]);
                // Broadcast to rejected cast
                event(new \App\Events\NotificationSent($rejectedNotification));
            }

            // Update rankings
            $rankingService = app(\App\Services\RankingService::class);
            $rankingService->updateRealTimeRankings($reservation->location ?? '全国');
        });

        return response()->json([
            'message' => 'Multiple applications approved successfully',
            'chat_group' => $chatGroup ?? null,
            'reservation' => $reservation->fresh()
        ]);
    }

    /**
     * Show payments management page with real data
     */
    public function payments(Request $request)
    {
        $query = \App\Models\Payment::with(['cast'])
            ->where('user_type', 'cast')
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('search') && $request->search) {
            $query->whereHas('cast', function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('nickname', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('payment_method') && $request->payment_method !== 'all') {
            $query->where('payment_method', $request->payment_method);
        }

        // Get paginated results
        $payments = $query->paginate(15);

        // Transform data for frontend
        $transformedPayments = $payments->getCollection()->map(function($payment) {
            return [
                'id' => $payment->id,
                'cast_id' => $payment->user_id,
                'cast_name' => $payment->cast ? $payment->cast->name : 'Unknown Cast',
                'amount' => $payment->amount,
                'status' => $payment->status,
                'payment_method' => $payment->payment_method,
                'description' => $payment->description,
                'paid_at' => $payment->paid_at?->toISOString(),
                'created_at' => $payment->created_at->toISOString(),
                'updated_at' => $payment->updated_at->toISOString(),
                'payjp_charge_id' => $payment->payjp_charge_id,
                'metadata' => $payment->metadata,
            ];
        });

        // Calculate summary statistics
        $summary = [
            'total_amount' => \App\Models\Payment::where('user_type', 'cast')->sum('amount'),
            'paid_count' => \App\Models\Payment::where('user_type', 'cast')->where('status', 'paid')->count(),
            'pending_count' => \App\Models\Payment::where('user_type', 'cast')->where('status', 'pending')->count(),
            'failed_count' => \App\Models\Payment::where('user_type', 'cast')->where('status', 'failed')->count(),
            'refunded_count' => \App\Models\Payment::where('user_type', 'cast')->where('status', 'refunded')->count(),
            'unique_casts' => \App\Models\Payment::where('user_type', 'cast')->distinct('user_id')->count(),
        ];

        $paymentsData = [
            'payments' => $transformedPayments,
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'from' => $payments->firstItem(),
                'to' => $payments->lastItem(),
            ],
            'summary' => $summary,
        ];

        return \Inertia\Inertia::render('admin/payments', [
            'payments' => $paymentsData,
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
                'payment_method' => $request->payment_method,
            ],
        ]);
    }
}
