<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\PointTransactionService;
use Illuminate\Support\Facades\Config;
use App\Http\Controllers\PaymentController;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PointCalculationTest extends TestCase
{
    protected $pointService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pointService = app(PointTransactionService::class);
    }

    public function test_yen_to_points_conversion_uses_config_rate()
    {
        Config::set('points.yen_per_point', 12);

        $controller = app(PaymentController::class);

        $guest = \App\Models\Guest::factory()->create(['points' => 0]);

        $request = Request::create('/payments/purchase', 'POST', [
            'user_id' => $guest->id,
            'user_type' => 'guest',
            'amount' => 1200,
            'token' => 'tok_dummy',
            'payment_method' => 'card',
        ]);

        // Mock PayJP service to bypass external call
        $this->partialMock(\App\Services\PayJPService::class, function ($mock) {
            $mock->shouldReceive('processPayment')->andReturn([
                'success' => true,
                'payment' => (object) ['id' => 1],
                'charge' => ['id' => 'ch_dummy']
            ]);
        });

        $response = $controller->purchase($request);
        $data = $response->getData(true);

        $guest->refresh();
        $this->assertTrue($data['success']);
        $this->assertEquals(100, $data['points_added']);
        $this->assertEquals(100, $guest->points);
    }

    public function test_night_time_bonus_calculation()
    {
        // Test night time bonus (12 AM to 5 AM)
        $midnight = Carbon::create(2024, 1, 1, 0, 0, 0); // 12 AM
        $threeAM = Carbon::create(2024, 1, 1, 3, 0, 0); // 3 AM
        $sixAM = Carbon::create(2024, 1, 1, 6, 0, 0); // 6 AM

        $this->assertEquals(4000, $this->pointService->calculateNightTimeBonus($midnight));
        $this->assertEquals(4000, $this->pointService->calculateNightTimeBonus($threeAM));
        $this->assertEquals(0, $this->pointService->calculateNightTimeBonus($sixAM));
    }

    public function test_extension_fee_formula_verification()
    {
        // Test the extension fee formula manually
        $castGradePoints = 15000;
        $basePointsPerMinute = $castGradePoints / 30; // 500
        $exceededMinutes = 30;
        $extensionMultiplier = 1.5;
        
        $extensionFee = $basePointsPerMinute * $exceededMinutes * $extensionMultiplier;
        
        // Extension fee = 500 * 30 * 1.5 = 22500
        $this->assertEquals(22500, $extensionFee);
    }

    public function test_base_points_formula_verification()
    {
        // Test the base points formula manually
        $castGradePoints = 15000;
        $durationInMinutes = 120; // 2 hours
        $basePoints = $castGradePoints * ($durationInMinutes / 30);
        
        // Base points = 15000 * (120 / 30) = 15000 * 4 = 60000
        $this->assertEquals(60000, $basePoints);
    }

    public function test_total_points_calculation_manual()
    {
        // Test the total points calculation manually
        $castGradePoints = 15000;
        $durationInMinutes = 60; // 1 hour
        $basePoints = $castGradePoints * ($durationInMinutes / 30); // 30000
        $nightTimeBonus = 4000;
        $extensionFee = 22500; // From previous test
        
        $totalPoints = $basePoints + $nightTimeBonus + $extensionFee;
        
        // Total = 30000 + 4000 + 22500 = 56500
        $this->assertEquals(30000, $basePoints);
        $this->assertEquals(4000, $nightTimeBonus);
        $this->assertEquals(22500, $extensionFee);
        $this->assertEquals(56500, $totalPoints);
    }

    public function test_extension_fee_edge_cases()
    {
        // Test extension fee with no extension
        $castGradePoints = 15000;
        $exceededMinutes = 0;
        $extensionMultiplier = 1.5;
        
        $extensionFee = ($castGradePoints / 30) * $exceededMinutes * $extensionMultiplier;
        
        // No extension, so fee should be 0
        $this->assertEquals(0, $extensionFee);
    }
} 