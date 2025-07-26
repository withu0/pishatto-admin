<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\RankingService;
use Illuminate\Support\Facades\Log;

class CalculateRankingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $period;
    protected $region;

    /**
     * Create a new job instance.
     */
    public function __construct(string $period = 'daily', string $region = '全国')
    {
        $this->period = $period;
        $this->region = $region;
    }

    /**
     * Execute the job.
     */
    public function handle(RankingService $rankingService): void
    {
        try {
            Log::info("Starting ranking calculation for {$this->period} period in {$this->region}");
            
            $rankingService->calculateRankings($this->period, $this->region);
            
            Log::info("Ranking calculation completed for {$this->period} period in {$this->region}");
        } catch (\Exception $e) {
            Log::error("Error in ranking calculation job: " . $e->getMessage(), [
                'period' => $this->period,
                'region' => $this->region,
                'exception' => $e
            ]);
            
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Ranking calculation job failed", [
            'period' => $this->period,
            'region' => $this->region,
            'exception' => $exception->getMessage()
        ]);
    }
} 