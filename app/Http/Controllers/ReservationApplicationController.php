<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ReservationApplication;
use App\Models\Reservation;
use App\Models\Chat;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ReservationApplicationController extends Controller
{
    /**
     * Apply for a reservation (cast applies)
     */
    public function apply(Request $request)
    {
        $validated = $request->validate([
            'reservation_id' => 'required|exists:reservations,id',
            'cast_id' => 'required|exists:casts,id',
        ]);

        // Check if reservation is still active
        $reservation = Reservation::find($validated['reservation_id']);
        if (!$reservation->active) {
            return response()->json([
                'message' => 'Reservation is no longer active'
            ], 400);
        }

        // Check if cast already applied
        $existingApplication = ReservationApplication::where('reservation_id', $validated['reservation_id'])
            ->where('cast_id', $validated['cast_id'])
            ->first();

        if ($existingApplication) {
            return response()->json([
                'message' => 'You have already applied for this reservation'
            ], 400);
        }

        // Create application
        $application = ReservationApplication::create([
            'reservation_id' => $validated['reservation_id'],
            'cast_id' => $validated['cast_id'],
            'status' => 'pending',
            'applied_at' => now(),
        ]);

        // Notify guest that a cast has applied
        $notification = Notification::create([
            'user_id' => $reservation->guest_id,
            'user_type' => 'guest',
            'type' => 'cast_applied',
            'reservation_id' => $reservation->id,
            'cast_id' => $validated['cast_id'],
            'message' => 'キャストが予約に応募しました',
            'read' => false,
        ]);

        event(new \App\Events\NotificationSent($notification));

        return response()->json([
            'message' => 'Application submitted successfully',
            'application' => $application->load(['cast', 'reservation'])
        ]);
    }

    /**
     * Approve a reservation application (admin action)
     */
    public function approve(Request $request, $applicationId)
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
            $reservation->update([
                'active' => false,
                'cast_id' => $application->cast_id,
            ]);

            // Reject all other pending applications for this reservation
            ReservationApplication::where('reservation_id', $reservation->id)
                ->where('id', '!=', $application->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'rejected',
                    'rejected_at' => now(),
                    'rejected_by' => $validated['admin_id'],
                    'rejection_reason' => 'Another cast was approved for this reservation',
                ]);

            // Create chat group
            $chat = Chat::create([
                'guest_id' => $reservation->guest_id,
                'cast_id' => $application->cast_id,
                'reservation_id' => $reservation->id,
            ]);

            // Notify guest
            $guestNotification = Notification::create([
                'user_id' => $reservation->guest_id,
                'user_type' => 'guest',
                'type' => 'order_matched',
                'reservation_id' => $reservation->id,
                'cast_id' => $application->cast_id,
                'message' => '予約がキャストにマッチされました',
                'read' => false,
            ]);

            // Notify approved cast
            $castNotification = Notification::create([
                'user_id' => $application->cast_id,
                'user_type' => 'cast',
                'type' => 'application_approved',
                'reservation_id' => $reservation->id,
                'message' => '予約の応募が承認されました',
                'read' => false,
            ]);

            // Notify rejected casts
            $rejectedApplications = ReservationApplication::where('reservation_id', $reservation->id)
                ->where('status', 'rejected')
                ->get();

            foreach ($rejectedApplications as $rejectedApp) {
                Notification::create([
                    'user_id' => $rejectedApp->cast_id,
                    'user_type' => 'cast',
                    'type' => 'application_rejected',
                    'reservation_id' => $reservation->id,
                    'message' => '予約の応募が却下されました',
                    'read' => false,
                ]);
            }

            // Broadcast notifications
            event(new \App\Events\NotificationSent($guestNotification));
            event(new \App\Events\NotificationSent($castNotification));

            // Update rankings
            $rankingService = app(\App\Services\RankingService::class);
            $rankingService->updateRealTimeRankings($reservation->location ?? '全国');
        });

        return response()->json([
            'message' => 'Application approved successfully',
            'chat' => $chat ?? null,
            'reservation' => $application->reservation->fresh()
        ]);
    }

    /**
     * Reject a reservation application (admin action)
     */
    public function reject(Request $request, $applicationId)
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
        $notification = Notification::create([
            'user_id' => $application->cast_id,
            'user_type' => 'cast',
            'type' => 'application_rejected',
            'reservation_id' => $application->reservation_id,
            'message' => '予約の応募が却下されました',
            'read' => false,
        ]);

        event(new \App\Events\NotificationSent($notification));

        return response()->json([
            'message' => 'Application rejected successfully'
        ]);
    }

    /**
     * Get pending applications for admin review
     */
    public function getPendingApplications()
    {
        $applications = ReservationApplication::with(['reservation.guest', 'cast'])
            ->where('status', 'pending')
            ->orderBy('applied_at', 'asc')
            ->get();

        return response()->json([
            'applications' => $applications
        ]);
    }

    /**
     * Get applications for a specific reservation
     */
    public function getReservationApplications($reservationId)
    {
        $applications = ReservationApplication::with(['cast'])
            ->where('reservation_id', $reservationId)
            ->orderBy('applied_at', 'asc')
            ->get();

        return response()->json([
            'applications' => $applications
        ]);
    }

    /**
     * Get all applications for a specific cast
     */
    public function getCastApplications($castId)
    {
        $applications = ReservationApplication::with(['reservation'])
            ->where('cast_id', $castId)
            ->where('status', 'pending') // Only return pending applications
            ->orderBy('applied_at', 'desc')
            ->get();

        return response()->json([
            'applications' => $applications
        ]);
    }
}
