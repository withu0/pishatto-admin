<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Conversion settings
    |--------------------------------------------------------------------------
    | Configure how many yen one point is worth for guest-side point purchases.
    | Note: Cast payouts now use grade-based FPT redemption rates defined in
    | GradeService::CAST_FPT_REDEMPTION_RATES instead of this conversion rate.
    */
    'yen_per_point' => env('CAST_PAYOUT_YEN_PER_POINT', 1.2),

    /*
    |--------------------------------------------------------------------------
    | Scheduled payout fee rates (末締め翌月末)
    |--------------------------------------------------------------------------
    | DEPRECATED: Fee rates are no longer used. Cast payouts now use
    | grade-based FPT redemption rates defined in GradeService.
    | Kept for backward compatibility with existing records.
    */
    // 'scheduled_fee_rates' => [
    //     'platinum' => 0.00,
    //     'gold' => 0.01,
    //     'silver' => 0.015,
    //     'bronze' => 0.02,
    //     'default' => 0.025,
    // ],

    /*
    |--------------------------------------------------------------------------
    | Instant payout fee rates (即時振込)
    |--------------------------------------------------------------------------
    | DEPRECATED: Fee rates are no longer used. Instant payouts use
    | grade-based FPT redemption rates with a 5% reduction.
    | Kept for backward compatibility with existing records.
    */
    // 'instant_fee_rates' => [
    //     'platinum' => 0.02,
    //     'gold' => 0.035,
    //     'silver' => 0.05,
    //     'bronze' => 0.07,
    //     'default' => 0.08,
    // ],

    /*
    |--------------------------------------------------------------------------
    | Instant payout limits
    |--------------------------------------------------------------------------
    */
    'instant_max_ratio' => 0.5, // 50% of unsettled points
    'instant_min_points' => 1000,
    'instant_min_amount_yen' => 5000,

    /*
    |--------------------------------------------------------------------------
    | Schedule configuration
    |--------------------------------------------------------------------------
    */
    'scheduled_payout_offset_months' => 1, // 翌月末
    'business_day_adjustment' => true, // Move to previous business day if weekend
    'timezone' => 'Asia/Tokyo',
];


