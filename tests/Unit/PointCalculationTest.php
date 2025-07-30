<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Cast;
use App\Models\Guest;
use App\Models\Reservation;
use App\Services\PointTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PointCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected $pointTransactionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pointTransactionService = new PointTransactionService();
    }

    public function test_point_calculation_based_on_grade_points_and_duration()
    {
        // Create a cast with grade_points
        $cast = Cast::create([
            'phone' => '+81901234567',
            'nickname' => 'Test Cast',
            'grade_points' => 5000, // 5000 points per 30 minutes
        ]);

        // Create a guest
        $guest = Guest::create([
            'phone' => '+81901234568',
            'nickname' => 'Test Guest',
        ]);

        // Create a reservation with 2 hours duration
        $reservation = Reservation::create([
            'guest_id' => $guest->id,
            'cast_id' => $cast->id,
            'duration' => 2, // 2 hours
            'scheduled_at' => now(),
            'created_at' => now(),
        ]);

        // Calculate expected points
        // Formula: grade_points * (duration_in_minutes / 30)
        // 5000 * (120 minutes / 30) = 5000 * 4 = 20000 points
        $expectedPoints = 5000 * (120 / 30);

        $calculatedPoints = $this->pointTransactionService->calculateReservationPoints($reservation);

        $this->assertEquals($expectedPoints, $calculatedPoints);
    }

    public function test_point_calculation_with_different_durations()
    {
        $cast = Cast::create([
            'phone' => '+81901234567',
            'nickname' => 'Test Cast',
            'grade_points' => 3000, // 3000 points per 30 minutes
        ]);

        $guest = Guest::create([
            'phone' => '+81901234568',
            'nickname' => 'Test Guest',
        ]);

        // Test 1 hour (60 minutes)
        $reservation1 = Reservation::create([
            'guest_id' => $guest->id,
            'cast_id' => $cast->id,
            'duration' => 1,
            'scheduled_at' => now(),
            'created_at' => now(),
        ]);

        $expectedPoints1 = 3000 * (60 / 30); // 3000 * 2 = 6000
        $calculatedPoints1 = $this->pointTransactionService->calculateReservationPoints($reservation1);
        $this->assertEquals($expectedPoints1, $calculatedPoints1);

        // Test 1.5 hours (90 minutes)
        $reservation2 = Reservation::create([
            'guest_id' => $guest->id,
            'cast_id' => $cast->id,
            'duration' => 1.5,
            'scheduled_at' => now(),
            'created_at' => now(),
        ]);

        $expectedPoints2 = 3000 * (90 / 30); // 3000 * 3 = 9000
        $calculatedPoints2 = $this->pointTransactionService->calculateReservationPoints($reservation2);
        $this->assertEquals($expectedPoints2, $calculatedPoints2);
    }

    public function test_point_calculation_with_zero_grade_points()
    {
        $cast = Cast::create([
            'phone' => '+81901234567',
            'nickname' => 'Test Cast',
            'grade_points' => 0,
        ]);

        $guest = Guest::create([
            'phone' => '+81901234568',
            'nickname' => 'Test Guest',
        ]);

        $reservation = Reservation::create([
            'guest_id' => $guest->id,
            'cast_id' => $cast->id,
            'duration' => 2,
            'scheduled_at' => now(),
            'created_at' => now(),
        ]);

        $calculatedPoints = $this->pointTransactionService->calculateReservationPoints($reservation);
        $this->assertEquals(0, $calculatedPoints);
    }

    public function test_point_calculation_with_null_grade_points()
    {
        $cast = Cast::create([
            'phone' => '+81901234567',
            'nickname' => 'Test Cast',
            'grade_points' => null,
        ]);

        $guest = Guest::create([
            'phone' => '+81901234568',
            'nickname' => 'Test Guest',
        ]);

        $reservation = Reservation::create([
            'guest_id' => $guest->id,
            'cast_id' => $cast->id,
            'duration' => 2,
            'scheduled_at' => now(),
            'created_at' => now(),
        ]);

        $calculatedPoints = $this->pointTransactionService->calculateReservationPoints($reservation);
        $this->assertEquals(0, $calculatedPoints);
    }
} 