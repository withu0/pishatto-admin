<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReservationApplication;
use App\Models\Reservation;
use App\Models\Cast;
use App\Models\Guest;
use App\Models\Chat;
use App\Models\ChatGroup;
use App\Models\Notification;
use App\Services\MatchingMessageService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class ReservationApplicationController extends Controller
{
    public function index()
    {
        $applications = ReservationApplication::with(['reservation.guest', 'cast'])
            ->orderBy('applied_at', 'desc')
            ->get()
            ->map(function ($application) {
                return [
                    'id' => $application->id,
                    'reservation' => [
                        'id' => $application->reservation->id,
                        'guest' => [
                            'id' => $application->reservation->guest->id,
                            'nickname' => $application->reservation->guest->nickname,
                            'avatar' => $application->reservation->guest->avatar,
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
                        'avatar' => $application->cast->avatar,
                    ],
                    'status' => $application->status,
                    'applied_at' => $application->applied_at,
                    'approved_at' => $application->approved_at,
                    'rejected_at' => $application->rejected_at,
                    'rejection_reason' => $application->rejection_reason,
                ];
            });

        return Inertia::render('admin/reservation-applications', [
            'applications' => $applications
        ]);
    }

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

            // Create chat group first
            $chatGroup = \App\Models\ChatGroup::create([
                'reservation_id' => $reservation->id,
                'cast_ids' => [$application->cast_id],
                'name' => '予約 - ' . $reservation->location,
                'created_at' => now(),
            ]);

            // Create individual chat for backward compatibility
            $chat = Chat::create([
                'guest_id' => $reservation->guest_id,
                'cast_id' => $application->cast_id,
                'reservation_id' => $reservation->id,
                'group_id' => $chatGroup->id,
            ]);

            // Send automatic matching information message
            $matchingMessageService = app(MatchingMessageService::class);
            $matchingMessageService->sendMatchingMessage($reservation, $application->cast_id, $chat->id, $chatGroup->id);

            // Notify guest
            $guestNotification = Notification::create([
                'user_id' => $reservation->guest_id,
                'user_type' => 'guest',
                'type' => 'order_matched',
                'reservation_id' => $reservation->id,
                'cast_id' => $application->cast_id,
                'message' => 'キャストと合流しました。合流後は自動延長となります。解散する際はキャストに解散とお伝えし、ボタン押下して終了となります。それでは、キャストとの時間をごゆっくりとお楽しみください。',
                'read' => false,
            ]);
            // Broadcast to guest
            event(new \App\Events\NotificationSent($guestNotification));

            // Notify approved cast
            $castNotification = Notification::create([
                'user_id' => $application->cast_id,
                'user_type' => 'cast',
                'type' => 'application_approved',
                'reservation_id' => $reservation->id,
                'message' => '予約の応募が承認されました',
                'read' => false,
            ]);
            // Broadcast to approved cast
            event(new \App\Events\NotificationSent($castNotification));

            // Update chat group with cast_ids (this is now handled by the creation above)
            // $updateChatGroup = $chatGroup; // Already created with correct cast_ids

            // Notify rejected casts
            $rejectedApplications = ReservationApplication::where('reservation_id', $reservation->id)
                ->where('status', 'rejected')
                ->get();

            foreach ($rejectedApplications as $rejectedApp) {
                $rejectedNotification = Notification::create([
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
        });

        return response()->json([
            'message' => 'Application approved successfully'
        ]);
    }

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

        return response()->json([
            'message' => 'Application rejected successfully'
        ]);
    }
} 