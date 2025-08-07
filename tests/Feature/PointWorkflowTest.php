<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Guest;
use App\Models\Cast;
use App\Models\Reservation;
use App\Models\PointTransaction;
use App\Services\PointTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class PointWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected $pointService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pointService = app(PointTransactionService::class);
    }

    /** @test */
    public function it_calculates_night_time_bonus_correctly()
    {
        // Test night time bonus (12 AM to 5 AM)
        $midnight = Carbon::create(2024, 1, 1, 0, 0, 0); // 12 AM
        $threeAM = Carbon::create(2024, 1, 1, 3, 0, 0); // 3 AM
        $sixAM = Carbon::create(2024, 1, 1, 6, 0, 0); // 6 AM

        $this->assertEquals(4000, $this->pointService->calculateNightTimeBonus($midnight));
        $this->assertEquals(4000, $this->pointService->calculateNightTimeBonus($threeAM));
        $this->assertEquals(0, $this->pointService->calculateNightTimeBonus($sixAM));
    }

    /** @test */
    public function it_calculates_extension_fee_correctly()
    {
        $guest = Guest::factory()->create(['points' => 10000]);
        $cast = Cast::factory()->create(['grade_points' => 15000]);
        
        $reservation = Reservation::factory()->create([
            'guest_id' => $guest->id,
            'cast_id' => $cast->id,
            'duration' => 1, // 1 hour
            'started_at' => Carbon::create(2024, 1, 1, 14, 0, 0), // 2 PM
            'ended_at' => Carbon::create(2024, 1, 1, 15, 30, 0), // 3:30 PM (1.5 hours)
        ]);

        $extensionFee = $this->pointService->calculateExtensionFee($reservation);
        
        // Base points per minute = 15000 / 30 = 500
        // Exceeded minutes = 30
        // Extension fee = 500 * 30 * 1.5 = 22500
        $this->assertEquals(22500, $extensionFee);
    }

    /** @test */
    public function it_processes_free_call_creation_correctly()
    {
        $guest = Guest::factory()->create(['points' => 10000]);
        
        $reservation = Reservation::factory()->create([
            'guest_id' => $guest->id,
            'duration' => 2, // 2 hours
            'type' => 'free'
        ]);

        $requiredPoints = 5000;
        $success = $this->pointService->processFreeCallCreation($reservation, $requiredPoints);

        $this->assertTrue($success);
        
        // Check that points were deducted from guest
        $guest->refresh();
        $this->assertEquals(5000, $guest->points);
        
        // Check that pending transaction was created
        $pendingTransaction = PointTransaction::where('reservation_id', $reservation->id)
            ->where('type', 'pending')
            ->first();
        
        $this->assertNotNull($pendingTransaction);
        $this->assertEquals(5000, $pendingTransaction->amount);
    }

    /** @test */
    public function it_processes_reservation_completion_with_night_time_bonus()
    {
        $guest = Guest::factory()->create(['points' => 10000]);
        $cast = Cast::factory()->create(['grade_points' => 15000, 'points' => 0]);
        
        $reservation = Reservation::factory()->create([
            'guest_id' => $guest->id,
            'cast_id' => $cast->id,
            'duration' => 1, // 1 hour
            'started_at' => Carbon::create(2024, 1, 1, 2, 0, 0), // 2 AM (night time)
            'ended_at' => Carbon::create(2024, 1, 1, 3, 0, 0), // 3 AM
        ]);

        // Create pending transaction
        PointTransaction::create([
            'guest_id' => $guest->id,
            'cast_id' => null,
            'type' => 'pending',
            'amount' => 10000,
            'reservation_id' => $reservation->id,
            'description' => 'Test pending transaction'
        ]);

        $success = $this->pointService->processReservationCompletion($reservation);

        $this->assertTrue($success);
        
        // Check that points were added to cast
        $cast->refresh();
        $expectedPoints = 30000 + 4000; // Base points (15000 * 2) + night time bonus
        $this->assertEquals($expectedPoints, $cast->points);
        
        // Check that reservation was updated
        $reservation->refresh();
        $this->assertEquals($expectedPoints, $reservation->points_earned);
    }

    /** @test */
    public function it_refunds_unused_points_correctly()
    {
        $guest = Guest::factory()->create(['points' => 5000]);
        
        $reservation = Reservation::factory()->create([
            'guest_id' => $guest->id,
            'duration' => 1,
        ]);

        // Create pending transaction
        PointTransaction::create([
            'guest_id' => $guest->id,
            'cast_id' => null,
            'type' => 'pending',
            'amount' => 10000,
            'reservation_id' => $reservation->id,
            'description' => 'Test pending transaction'
        ]);

        $success = $this->pointService->refundUnusedPoints($reservation);

        $this->assertTrue($success);
        
        // Check that points were refunded to guest
        $guest->refresh();
        $this->assertEquals(15000, $guest->points); // 5000 + 10000
        
        // Check that transaction was updated
        $refundTransaction = PointTransaction::where('reservation_id', $reservation->id)
            ->where('type', 'convert')
            ->first();
        
        $this->assertNotNull($refundTransaction);
        $this->assertEquals(10000, $refundTransaction->amount);
    }
}

