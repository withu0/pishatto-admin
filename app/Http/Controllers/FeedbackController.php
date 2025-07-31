<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Feedback;
use App\Models\Reservation;
use App\Models\Cast;
use App\Models\Guest;
use App\Models\Badge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FeedbackController extends Controller
{
    /**
     * Submit feedback for a specific cast in a reservation.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reservation_id' => 'required|exists:reservations,id',
            'cast_id' => 'required|exists:casts,id',
            'guest_id' => 'required|exists:guests,id',
            'comment' => 'nullable|string|max:1000',
            'rating' => 'nullable|integer|min:1|max:5',
            'badge_id' => 'nullable|exists:badges,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if reservation is completed
        $reservation = Reservation::find($request->reservation_id);
        if (!$reservation || !$reservation->ended_at) {
            return response()->json(['message' => 'Reservation must be completed before submitting feedback'], 400);
        }

        // Check if feedback already exists for this guest-cast-reservation combination
        $existingFeedback = Feedback::where([
            'reservation_id' => $request->reservation_id,
            'cast_id' => $request->cast_id,
            'guest_id' => $request->guest_id,
        ])->first();

        if ($existingFeedback) {
            return response()->json(['message' => 'Feedback already submitted for this cast in this reservation'], 409);
        }

        try {
            DB::beginTransaction();

            // Create the feedback
            $feedback = Feedback::create([
                'reservation_id' => $request->reservation_id,
                'cast_id' => $request->cast_id,
                'guest_id' => $request->guest_id,
                'comment' => $request->comment,
                'rating' => $request->rating,
                'badge_id' => $request->badge_id,
            ]);

            // Assign badge to cast if badge_id is present
            if ($request->filled('badge_id')) {
                $cast = Cast::find($request->cast_id);
                $badge = Badge::find($request->badge_id);
                
                if ($cast && $badge) {
                    if (!$cast->badges()->where('badge_id', $request->badge_id)->exists()) {
                        $cast->badges()->attach($request->badge_id);
                    }
                }
            }

            DB::commit();

            $feedback->load(['cast', 'guest', 'badge']);

            return response()->json([
                'message' => 'Feedback submitted successfully',
                'feedback' => $feedback
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to submit feedback', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get feedback for a specific reservation.
     */
    public function getReservationFeedback($reservationId)
    {
        $feedback = Feedback::with(['cast', 'guest', 'badge'])
            ->where('reservation_id', $reservationId)
            ->get();

        return response()->json(['feedback' => $feedback]);
    }

    /**
     * Get feedback for a specific cast.
     */
    public function getCastFeedback($castId)
    {
        $feedback = Feedback::with(['reservation', 'guest', 'badge'])
            ->where('cast_id', $castId)
            ->orderBy('created_at', 'desc')
            ->get();

        $averageRating = $feedback->whereNotNull('rating')->avg('rating');

        return response()->json([
            'feedback' => $feedback,
            'average_rating' => round($averageRating, 2),
            'total_feedback_count' => $feedback->count(),
        ]);
    }

    /**
     * Get feedback written by a specific guest.
     */
    public function getGuestFeedback($guestId)
    {
        $feedback = Feedback::with(['reservation', 'cast', 'badge'])
            ->where('guest_id', $guestId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['feedback' => $feedback]);
    }

    /**
     * Update existing feedback.
     */
    public function update(Request $request, $feedbackId)
    {
        $validator = Validator::make($request->all(), [
            'comment' => 'nullable|string|max:1000',
            'rating' => 'nullable|integer|min:1|max:5',
            'badge_id' => 'nullable|exists:badges,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $feedback = Feedback::find($feedbackId);
        if (!$feedback) {
            return response()->json(['message' => 'Feedback not found'], 404);
        }

        try {
            DB::beginTransaction();

            // Update feedback
            $feedback->update($request->only(['comment', 'rating', 'badge_id']));

            // Handle badge assignment
            if ($request->filled('badge_id')) {
                $cast = Cast::find($feedback->cast_id);
                $badge = Badge::find($request->badge_id);
                
                if ($cast && $badge) {
                    if (!$cast->badges()->where('badge_id', $request->badge_id)->exists()) {
                        $cast->badges()->attach($request->badge_id);
                    }
                }
            }

            DB::commit();

            $feedback->load(['cast', 'guest', 'badge']);

            return response()->json([
                'message' => 'Feedback updated successfully',
                'feedback' => $feedback
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update feedback', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete feedback.
     */
    public function destroy($feedbackId)
    {
        $feedback = Feedback::find($feedbackId);
        if (!$feedback) {
            return response()->json(['message' => 'Feedback not found'], 404);
        }

        $feedback->delete();

        return response()->json(['message' => 'Feedback deleted successfully']);
    }

    /**
     * Get feedback statistics for a cast.
     */
    public function getCastFeedbackStats($castId)
    {
        $feedback = Feedback::where('cast_id', $castId)->get();

        $stats = [
            'total_feedback' => $feedback->count(),
            'average_rating' => round($feedback->whereNotNull('rating')->avg('rating'), 2),
            'rating_distribution' => [
                1 => $feedback->where('rating', 1)->count(),
                2 => $feedback->where('rating', 2)->count(),
                3 => $feedback->where('rating', 3)->count(),
                4 => $feedback->where('rating', 4)->count(),
                5 => $feedback->where('rating', 5)->count(),
            ],
            'feedback_with_comments' => $feedback->whereNotNull('comment')->count(),
            'feedback_with_badges' => $feedback->whereNotNull('badge_id')->count(),
        ];

        return response()->json(['stats' => $stats]);
    }

    /**
     * Get top 5 casts with highest average feedback ratings.
     */
    public function getTopSatisfactionCasts()
    {
        // First get the top 5 cast IDs with their average ratings
        $topCastIds = \App\Models\Cast::select('casts.id')
            ->selectRaw('AVG(feedback.rating) as average_rating')
            ->selectRaw('COUNT(feedback.id) as feedback_count')
            ->join('feedback', 'casts.id', '=', 'feedback.cast_id')
            ->whereNotNull('feedback.rating')
            ->groupBy('casts.id')
            ->having('feedback_count', '>=', 1) // At least 1 feedback
            ->orderBy('average_rating', 'desc')
            ->orderBy('feedback_count', 'desc') // Secondary sort by feedback count
            ->limit(5)
            ->pluck('id');

        // Then get the full cast data for these IDs
        $topCasts = \App\Models\Cast::whereIn('id', $topCastIds)
            ->get()
            ->map(function($cast) {
                // Get the average rating and feedback count for this cast
                $feedbackStats = \App\Models\Feedback::where('cast_id', $cast->id)
                    ->whereNotNull('rating')
                    ->selectRaw('AVG(rating) as average_rating, COUNT(*) as feedback_count')
                    ->first();
                
                $cast->average_rating = round($feedbackStats->average_rating, 2);
                $cast->feedback_count = $feedbackStats->feedback_count;
                
                return $cast;
            });

        return response()->json(['casts' => $topCasts]);
    }

    /**
     * Get all casts with feedback (not limited to top 5).
     */
    public function getAllSatisfactionCasts()
    {
        // Get all cast IDs that have feedback
        $castIds = \App\Models\Cast::select('casts.id')
            ->selectRaw('AVG(feedback.rating) as average_rating')
            ->selectRaw('COUNT(feedback.id) as feedback_count')
            ->join('feedback', 'casts.id', '=', 'feedback.cast_id')
            ->whereNotNull('feedback.rating')
            ->groupBy('casts.id')
            ->having('feedback_count', '>=', 1) // At least 1 feedback
            ->orderBy('average_rating', 'desc')
            ->orderBy('feedback_count', 'desc') // Secondary sort by feedback count
            ->pluck('id');

        // Then get the full cast data for these IDs
        $allCasts = \App\Models\Cast::whereIn('id', $castIds)
            ->get()
            ->map(function($cast) {
                // Get the average rating and feedback count for this cast
                $feedbackStats = \App\Models\Feedback::where('cast_id', $cast->id)
                    ->whereNotNull('rating')
                    ->selectRaw('AVG(rating) as average_rating, COUNT(*) as feedback_count')
                    ->first();
                
                $cast->average_rating = round($feedbackStats->average_rating, 2);
                $cast->feedback_count = $feedbackStats->feedback_count;
                
                return $cast;
            });

        return response()->json(['casts' => $allCasts]);
    }
}