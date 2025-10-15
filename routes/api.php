<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GuestAuthController;
use App\Http\Controllers\Auth\CastAuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\TweetController;
use App\Http\Controllers\CastPaymentController;
use App\Http\Controllers\IdentityVerificationController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\Auth\SmsVerificationController;
use App\Http\Controllers\ReservationApplicationController;
use App\Http\Controllers\ConciergeController;
use App\Models\Feedback;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Api\GradeController;
use App\Http\Controllers\CastApplicationController;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// SMS Verification routes
Route::post('/sms/send-code', [SmsVerificationController::class, 'sendVerificationCode']);
Route::post('/sms/verify-code', [SmsVerificationController::class, 'verifyCode']);
Route::post('/sms/check-status', [SmsVerificationController::class, 'checkVerificationStatus']);
Route::get('/sms/test-formatting', [SmsVerificationController::class, 'testPhoneFormatting']);

Route::post('/guest/register', [GuestAuthController::class, 'register']);
Route::post('/guest/login', [GuestAuthController::class, 'login']);
Route::get('/guest/check-auth', [GuestAuthController::class, 'checkAuth']);
Route::get('/guest/profile/{phone}', [GuestAuthController::class, 'getProfile']);
Route::get('/guest/profile/line/{line_id}', [GuestAuthController::class, 'getProfileByLineId']);
Route::post('/guest/profile', [GuestAuthController::class, 'updateProfile']);
Route::post('/cast/login', [CastAuthController::class, 'login']);
Route::post('/cast/check-exists', [CastAuthController::class, 'checkCastExists']);
Route::get('/cast/check-auth', [CastAuthController::class, 'checkAuth']);
Route::post('/cast/profile', [CastAuthController::class, 'updateProfile']);
Route::post('/cast/register', [CastAuthController::class, 'register']);
Route::post('/guest/reservation', [GuestAuthController::class, 'createReservation']);
Route::post('/guest/free-call', [GuestAuthController::class, 'createFreeCall']);
Route::post('/guest/free-call-reservation', [GuestAuthController::class, 'createFreeCallReservation']);
Route::get('/casts/available', [CastAuthController::class, 'getAvailableCasts']);
Route::get('/cast/profile/{id}', [CastAuthController::class, 'getProfile']);
Route::get('/guest/reservations/{guest_id}', [GuestAuthController::class, 'listReservations']);
Route::get('/reservations/all', [CastAuthController::class, 'allReservations']);
Route::post('/reservation/match', [GuestAuthController::class, 'matchReservation']); // Deprecated
Route::post('/reservation-applications/apply', [ReservationApplicationController::class, 'apply']);
Route::post('/reservation-applications/{applicationId}/approve', [ReservationApplicationController::class, 'approve']);
Route::post('/reservation-applications/{applicationId}/reject', [ReservationApplicationController::class, 'reject']);
Route::get('/reservation-applications/pending', [ReservationApplicationController::class, 'getPendingApplications']);
Route::get('/reservation-applications/reservation/{reservationId}', [ReservationApplicationController::class, 'getReservationApplications']);
Route::get('/reservation-applications/cast/{castId}', [ReservationApplicationController::class, 'getCastApplications']);
Route::get('/reservation-applications/cast/{castId}/all', [ReservationApplicationController::class, 'getAllCastApplications']);
Route::get('/chats/{chatId}/messages', [ChatController::class, 'messages']);
Route::post('/chats/{chatId}/mark-read', [ChatController::class, 'markChatRead']);
Route::get('/chats/{userType}/{userId}', [GuestAuthController::class, 'getUserChats']);
Route::get('/chats-list/cast/{castId}', [GuestAuthController::class, 'getCastChats']);
Route::get('/chats/all', [GuestAuthController::class, 'allChats']);
Route::get('/chats', [ChatController::class, 'index']);
Route::post('/messages', [ChatController::class, 'store']);
Route::get('/reservations/{id}', [GuestAuthController::class, 'getReservationById']);
Route::put('/reservations/{id}', [GuestAuthController::class, 'updateReservation']);
Route::post('/reservations/{id}/complete', [GuestAuthController::class, 'completeReservation']);
Route::post('/reservations/{id}/cancel', [GuestAuthController::class, 'cancelReservation']);
Route::post('/reservations/{id}/refund', [GuestAuthController::class, 'refundUnusedPoints']);
Route::post('/sessions/complete', [GuestAuthController::class, 'completeSession']);
Route::get('/reservations/{id}/point-breakdown', [GuestAuthController::class, 'getPointBreakdown']);
Route::get('/guests/repeat', [GuestAuthController::class, 'repeatGuests']);
Route::get('/guest/profile/id/{id}', [GuestAuthController::class, 'getProfileById']);
Route::post('/guests/deduct-points', [GuestAuthController::class, 'deductPoints']);
Route::get('/casts', [CastAuthController::class, 'list']);
Route::get('/casts/counts-by-location', [CastAuthController::class, 'getCastCountsByLocation']);
Route::get('/casts/profile/{id}', [CastAuthController::class, 'getCastProfile']);
Route::get('/casts/points/{id}', [CastAuthController::class, 'getCastPointsData']);
Route::get('/casts/passport/{id}', [CastAuthController::class, 'getCastPassportData']);
Route::post('/casts/like', [CastAuthController::class, 'like']);
Route::get('/casts/liked/{guestId}', [CastAuthController::class, 'likedCasts']);
Route::post('/guests/visit', [CastAuthController::class, 'recordGuestVisit']);
Route::get('/casts/visit-history/{guestId}', [CastAuthController::class, 'visitHistory']);

Route::get('/notifications/{userType}/{userId}', [GuestAuthController::class, 'getNotifications']);
Route::get('/notifications/{userType}/{userId}/unread-count', [GuestAuthController::class, 'getUnreadNotificationCount']);
Route::post('/notifications/read/{id}', [GuestAuthController::class, 'markNotificationRead']);
Route::post('/notifications/read-all/{userType}/{userId}', [GuestAuthController::class, 'markAllNotificationsRead']);
Route::delete('/notifications/{id}', [GuestAuthController::class, 'deleteNotification']);

// Notification settings routes
Route::get('/notification-settings', [App\Http\Controllers\NotificationSettingsController::class, 'getSettings']);
Route::post('/notification-settings', [App\Http\Controllers\NotificationSettingsController::class, 'updateSettings']);
Route::get('/notification-settings/check', [App\Http\Controllers\NotificationSettingsController::class, 'isEnabled']);

// Avatar serving route
Route::get('/avatars/{filename}', [GuestAuthController::class, 'getAvatar']);
Route::post('/users/avatar', [GuestAuthController::class, 'uploadAvatar']);
Route::delete('/users/avatar', [GuestAuthController::class, 'deleteAvatar']);

// Payment routes
Route::post('/payments/token', [PaymentController::class, 'createToken']);
Route::post('/payments/create-payment-method', [PaymentController::class, 'createPaymentMethod']);
Route::post('/payments/complete-payment-intent', [PaymentController::class, 'completePaymentIntent']);

// Point transaction routes
Route::get('/point-transactions/{userType}/{userId}', [PaymentController::class, 'getPointTransactions']);
Route::post('/point-transactions', [PaymentController::class, 'createPointTransaction']);

// Badge routes
Route::get('/badges', function () {
    return response()->json(['badges' => \App\Models\Badge::all()]);
});
// Concierge routes
Route::get('/concierge/messages', [ConciergeController::class, 'getMessages']);
Route::post('/concierge/messages', [ConciergeController::class, 'sendMessage']);
Route::post('/concierge/system-message', [ConciergeController::class, 'sendSystemMessage']);
Route::post('/concierge/mark-read', [ConciergeController::class, 'markAsRead']);
Route::get('/concierge/info', [ConciergeController::class, 'getInfo']);

Route::get('/badges/{castId}', function ($castId) {
    // Get all badge IDs from feedback table for this cast
    $badgeIds = \App\Models\Feedback::where('cast_id', $castId)
        ->whereNotNull('badge_id')
        ->pluck('badge_id');

    if ($badgeIds->isEmpty()) {
        return response()->json(['badges' => []]);
    }

    // Get badge information and count occurrences
    $badgesWithCounts = \App\Models\Badge::select('badges.*', DB::raw('COUNT(feedback.badge_id) as count'))
        ->join('feedback', 'badges.id', '=', 'feedback.badge_id')
        ->where('feedback.cast_id', $castId)
        ->groupBy('badges.id', 'badges.name', 'badges.icon', 'badges.description', 'badges.created_at', 'badges.updated_at')
        ->get();

    return response()->json(['badges' => $badgesWithCounts]);
});

// Feedback routes
Route::post('/feedback', [FeedbackController::class, 'store']);
Route::get('/feedback/reservation/{reservationId}', [FeedbackController::class, 'getReservationFeedback']);
Route::get('/feedback/cast/{castId}', [FeedbackController::class, 'getCastFeedback']);
Route::get('/feedback/guest/{guestId}', [FeedbackController::class, 'getGuestFeedback']);
Route::put('/feedback/{feedbackId}', [FeedbackController::class, 'update']);
Route::delete('/feedback/{feedbackId}', [FeedbackController::class, 'destroy']);
Route::get('/feedback/cast/{castId}/stats', [FeedbackController::class, 'getCastFeedbackStats']);
Route::get('/feedback/top-satisfaction', [FeedbackController::class, 'getTopSatisfactionCasts']);
Route::get('/feedback/all-satisfaction', [FeedbackController::class, 'getAllSatisfactionCasts']);
Route::post('/payments/payment-intent-direct', [PaymentController::class, 'createPaymentIntentDirect']);
Route::post('/payments/debug-response', [PaymentController::class, 'debugStripeResponse']);
Route::post('/payments/purchase', [PaymentController::class, 'purchase']);
Route::post('/payments/register-card', [PaymentController::class, 'registerCard']);
Route::post('/payments/info', [PaymentController::class, 'storePaymentInfo']);
Route::get('/payments/info/{userType}/{userId}', [PaymentController::class, 'getPaymentInfo']);
Route::get('/payments/stats/{userType}/{userId}', [PaymentController::class, 'getCustomerStats']);
Route::delete('/payments/info/{userType}/{userId}/{cardId}', [PaymentController::class, 'deletePaymentInfo']);
Route::get('/payments/history/{userType}/{userId}', [PaymentController::class, 'history']);
Route::get('/payments/status/{paymentId}', [PaymentController::class, 'getPaymentStatus']);
Route::post('/payments/{paymentId}/refund', [PaymentController::class, 'refund']);
Route::post('/payments/payout', [PaymentController::class, 'requestPayout']);
Route::post('/payments/webhook', [PaymentController::class, 'webhook']);

// Automatic payment routes for insufficient points
Route::post('/payments/automatic', [PaymentController::class, 'processAutomaticPayment']);
Route::get('/payments/automatic/eligibility/{guestId}', [PaymentController::class, 'checkAutomaticPaymentEligibility']);
Route::get('/payments/automatic/history', [PaymentController::class, 'getAutomaticPaymentHistory']);
Route::get('/payments/automatic/audit-trail', [PaymentController::class, 'getAutomaticPaymentAuditTrail']);
Route::get('/payments/automatic/reservation/{reservationId}', [PaymentController::class, 'getReservationAutomaticPayments']);

// Admin cast payment management routes
Route::get('/admin/payments/cast', [PaymentController::class, 'getCastPayments']);
Route::post('/admin/payments/cast', [PaymentController::class, 'createCastPayment']);
Route::put('/admin/payments/cast/{paymentId}', [PaymentController::class, 'updateCastPayment']);
Route::delete('/admin/payments/cast/{paymentId}', [PaymentController::class, 'deleteCastPayment']);

// Payment Details API routes
Route::get('/admin/payment-details', [App\Http\Controllers\Admin\PaymentDetailController::class, 'getPaymentDetails']);
Route::post('/admin/payment-details', [App\Http\Controllers\Admin\PaymentDetailController::class, 'createPaymentDetail']);
Route::put('/admin/payment-details/{paymentDetailId}', [App\Http\Controllers\Admin\PaymentDetailController::class, 'updatePaymentDetail']);

// Receipt routes
Route::get('/receipts/by-number/{receiptNumber}', [PaymentController::class, 'getReceiptByNumber']);
Route::get('/receipts/{userType}/{userId}', [PaymentController::class, 'receipts']);
Route::post('/receipts', [PaymentController::class, 'createReceipt']);
Route::get('/receipts/{receiptId}', [PaymentController::class, 'getReceipt']);

// Admin Receipt routes
Route::get('/admin/receipts', [App\Http\Controllers\Admin\ReceiptsController::class, 'getReceiptsData']);
Route::get('/admin/receipts/{id}', [App\Http\Controllers\Admin\ReceiptsController::class, 'show']);
Route::post('/admin/receipts', [App\Http\Controllers\Admin\ReceiptsController::class, 'store']);
Route::put('/admin/receipts/{id}', [App\Http\Controllers\Admin\ReceiptsController::class, 'update']);
Route::delete('/admin/receipts/{id}', [App\Http\Controllers\Admin\ReceiptsController::class, 'destroy']);

// Payout routes
Route::post('/payouts/request', [PaymentController::class, 'requestPayout']);

// Tweet routes
Route::get('/tweets/{tweetId}/like-count', [TweetController::class, 'likeCount']);
Route::get('/tweets/{tweetId}/like-status', [TweetController::class, 'likeStatus']);
Route::get('/tweets', [TweetController::class, 'index']);
Route::get('/tweets/{userType}/{userId}', [TweetController::class, 'userTweets']);
Route::post('/tweets', [TweetController::class, 'store']);
Route::delete('/tweets/{id}', [TweetController::class, 'destroy']);
// Tweet like endpoints
Route::post('/tweets/like', [TweetController::class, 'like']);

// Grade management APIs
Route::get('/grades/guest/{guest_id}', [GradeController::class, 'getGuestGrade']);
Route::post('/grades/guest/update', [GradeController::class, 'updateGuestGrade']);
Route::get('/grades/cast/{cast_id}', [GradeController::class, 'getCastGrade']);
Route::post('/grades/cast/update', [GradeController::class, 'updateCastGrade']);
Route::get('/grades/info', [GradeController::class, 'getAllGradesInfo']);
Route::post('/grades/benefits', [GradeController::class, 'getGradeBenefits']);
// Candidates for management approval and auto-downgrade
Route::get('/admin/grades/candidates', [GradeController::class, 'candidates']);
Route::post('/admin/grades/approve-guest', [GradeController::class, 'approveGuestUpgrade']);
Route::post('/admin/grades/approve-cast', [GradeController::class, 'approveCastUpgrade']);
Route::post('/admin/grades/auto-downgrade', [GradeController::class, 'runAutoDowngrades']);
Route::get('/admin/grades/downgrade-candidates', [GradeController::class, 'downgradeCandidates']);
Route::get('/admin/grades/evaluation-info', [GradeController::class, 'getEvaluationInfo']);
Route::get('/admin/grades/quarterly-points-info', [GradeController::class, 'getQuarterlyPointsInfo']);

Route::get('/guests/phones', [GuestAuthController::class, 'allPhones']);
Route::get('/gifts', [ChatController::class, 'gifts']);
// Gift box: received gifts for cast
Route::get('/cast/{castId}/received-gifts', [ChatController::class, 'receivedGifts']);
Route::post('/cast/avatar-upload', [CastAuthController::class, 'uploadAvatar']);
Route::delete('/cast/avatar-delete', [CastAuthController::class, 'deleteAvatar']);
Route::post('/guests/like', [GuestAuthController::class, 'likeGuest']);
Route::post('/chats/create', [ChatController::class, 'createChat']);
Route::post('/chats/create-group', [ChatController::class, 'createChatGroup']);
Route::post('/chats/group-message', [ChatController::class, 'sendGroupMessage']);
Route::get('/chats/group/{groupId}/messages', [ChatController::class, 'getGroupMessages']);
Route::get('/chats/group/{groupId}/participants', [ChatController::class, 'getGroupParticipants']);
Route::get('/guests/like-status/{cast_id}/{guest_id}', [GuestAuthController::class, 'likeStatus']);
Route::get('/ranking', [RankingController::class, 'getRanking']);
// New monthly earned ranking (point_transactions-based)
Route::get('/ranking/monthly-earned', [RankingController::class, 'getMonthlyEarnedRanking']);
Route::post('/ranking/clear-cache', [RankingController::class, 'clearRankingCache']);
Route::post('/ranking/recalculate', [RankingController::class, 'recalculateRankings']);
Route::post('/ranking/recalculate-all', [RankingController::class, 'recalculateAllRankings']);
Route::get('/chats/{chatId}', [ChatController::class, 'show']);
Route::put('/chats/{chatId}', [ChatController::class, 'update']);
Route::post('/reservation/start', [CastAuthController::class, 'startReservation']);
Route::post('/reservation/stop', [CastAuthController::class, 'stopReservation']);
Route::get('/reservation/cast-session-status', [CastAuthController::class, 'getCastSessionStatus']);
Route::get('/reservation/cast-sessions', [CastAuthController::class, 'getReservationCastSessions']);
Route::post('/casts/favorite', [CastAuthController::class, 'favorite']);
Route::post('/casts/unfavorite', [CastAuthController::class, 'unfavorite']);
Route::get('/casts/favorites/{guestId}', [CastAuthController::class, 'favoriteCasts']);

// Debug endpoint for realtime configuration
Route::get('/debug/realtime-config', function () {
    return response()->json([
        'broadcast_driver' => config('broadcasting.default'),
        'reverb_config' => config('broadcasting.connections.reverb'),
        'app_url' => config('app.url'),
        'app_env' => config('app.env'),
        'log_level' => config('logging.default'),
        'timestamp' => now()->toISOString()
    ]);
});
// Route::get('/badges', [GuestAuthController::class, 'getAllBadges']); // Commented out to avoid duplicate routes

// Chat favorites routes
Route::post('/chats/favorite', [GuestAuthController::class, 'favoriteChat']);
Route::post('/chats/unfavorite', [GuestAuthController::class, 'unfavoriteChat']);
Route::get('/chats-guest/favorites/{guestId}', [GuestAuthController::class, 'favoriteChats']);
Route::get('/chats/{chatId}/favorited/{guestId}', [GuestAuthController::class, 'isChatFavorited']);

// Cast payment routes
Route::get('/casts/{castId}/immediate-payment', [CastPaymentController::class, 'getImmediatePaymentData']);
Route::post('/casts/{castId}/immediate-payment', [CastPaymentController::class, 'processImmediatePayment']);

Route::post('/identity/upload', [IdentityVerificationController::class, 'upload']);
// Admin endpoints for identity verification approval/rejection
Route::post('/admin/identity-verification/{guestId}/approve', [IdentityVerificationController::class, 'approve']);
Route::post('/admin/identity-verification/{guestId}/reject', [IdentityVerificationController::class, 'reject']);

// Admin News API routes
Route::get('/admin-news/{userType}/{userId}', [GuestAuthController::class, 'getAdminNews']);
Route::get('/admin-news/{userType}', [GuestAuthController::class, 'getAdminNews']);

// Point transactions admin routes (all except pending type)
Route::get('/admin/exceeded-pending', [App\Http\Controllers\Admin\ExceededPendingController::class, 'index']);
Route::get('/admin/exceeded-pending/grouped', [App\Http\Controllers\Admin\ExceededPendingController::class, 'groupedByReservation']);
Route::get('/admin/exceeded-pending/count', [App\Http\Controllers\Admin\ExceededPendingController::class, 'count']);
Route::post('/admin/exceeded-pending/process-all', [App\Http\Controllers\Admin\ExceededPendingController::class, 'processAll']);
Route::post('/admin/exceeded-pending/cancel-payment', [App\Http\Controllers\Admin\ExceededPendingController::class, 'cancelPayment']);

// All point transactions (including pending)
Route::get('/admin/point-transactions', [App\Http\Controllers\Admin\ExceededPendingController::class, 'getAllPointTransactions']);

// Test endpoint for exceeded time processing (for development/testing)
Route::post('/test/exceeded-time/{reservationId}', function ($reservationId) {
    $reservation = \App\Models\Reservation::find($reservationId);
    if (!$reservation) {
        return response()->json(['message' => 'Reservation not found'], 404);
    }

    $pointService = app(\App\Services\PointTransactionService::class);
    $exceededAmount = $pointService->calculateExceededTimeAmount($reservation);
    $success = $pointService->processExceededTime($reservation);

    // Check if exceeded_pending transaction was created
    $exceededPendingTransaction = \App\Models\PointTransaction::where('reservation_id', $reservationId)
        ->where('type', 'exceeded_pending')
        ->first();

    return response()->json([
        'reservation_id' => $reservationId,
        'reservation_type' => $reservation->type,
        'started_at' => $reservation->started_at,
        'ended_at' => $reservation->ended_at,
        'duration' => $reservation->duration,
        'duration_type' => gettype($reservation->duration),
        'duration_value' => (float) $reservation->duration,
        'cast_id' => $reservation->cast_id,
        'exceeded_amount' => $exceededAmount,
        'success' => $success,
        'exceeded_pending_created' => $exceededPendingTransaction ? true : false,
        'exceeded_pending_transaction' => $exceededPendingTransaction
    ]);
});

// Cast Application routes
Route::post('/cast-applications/submit', [CastApplicationController::class, 'submit']);
Route::get('/cast-applications', [CastApplicationController::class, 'index']);
Route::get('/cast-applications/{id}', [CastApplicationController::class, 'show']);
Route::post('/cast-applications/{id}/approve', [CastApplicationController::class, 'approve']);
Route::post('/cast-applications/{id}/reject', [CastApplicationController::class, 'reject']);

// Two-stage screening routes
Route::post('/cast-applications/{id}/approve-preliminary', [CastApplicationController::class, 'approvePreliminary']);
Route::post('/cast-applications/{id}/reject-preliminary', [CastApplicationController::class, 'rejectPreliminary']);
Route::post('/cast-applications/{id}/approve-final', [CastApplicationController::class, 'approveFinal']);
Route::post('/cast-applications/{id}/reject-final', [CastApplicationController::class, 'rejectFinal']);

// Public API routes for locations
Route::get('/locations/active', [LocationController::class, 'getActiveLocations']);
Route::get('/locations/prefectures', [LocationController::class, 'getPrefecturesByLocation']);

// Grade API routes
Route::get('/grades/guest/{guest_id}', [GradeController::class, 'getGuestGrade']);
Route::post('/grades/guest/update', [GradeController::class, 'updateGuestGrade']);
Route::get('/grades/cast/{cast_id}', [GradeController::class, 'getCastGrade']);
Route::post('/grades/cast/update', [GradeController::class, 'updateCastGrade']);
Route::get('/grades/info', [GradeController::class, 'getAllGradesInfo']);
Route::get('/grades/{grade}/benefits', [GradeController::class, 'getGradeBenefits']);
Route::post('/grades/update-all', [GradeController::class, 'updateAllGrades']);

// Line Authentication routes moved to web.php for session support
