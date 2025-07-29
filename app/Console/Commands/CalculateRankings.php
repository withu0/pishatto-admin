<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RankingService;

class CalculateRankings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rankings:calculate 
                            {period? : The period to calculate (daily, weekly, monthly, period, current, yesterday, lastWeek, lastMonth, allTime)}
                            {region? : The region to calculate for (default: 全国)}
                            {--all : Calculate for all periods and regions}
                            {--with-night-bonus : Recalculate with night time bonus logic}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate and update user rankings';

    protected $rankingService;

    /**
     * Create a new command instance.
     */
    public function __construct(RankingService $rankingService)
    {
        parent::__construct();
        $this->rankingService = $rankingService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $period = $this->argument('period');
        $region = $this->argument('region') ?? '全国';
        $all = $this->option('all');
        $withNightBonus = $this->option('with-night-bonus');

        if ($withNightBonus) {
            $this->info('Night time bonus logic is now enabled by default in the ranking calculations.');
        }

        if ($all) {
            $this->info('Calculating rankings for all periods and regions...');
            $this->rankingService->recalculateAllRankings();
            $this->info('All rankings calculated successfully!');
            return 0;
        }

        if (!$period) {
            $period = $this->choice(
                'Which period would you like to calculate?',
                ['daily', 'weekly', 'monthly', 'period', 'current', 'yesterday', 'lastWeek', 'lastMonth', 'allTime'],
                'daily'
            );
        }

        // Map frontend period names to backend period names
        $periodMapping = [
            'current' => 'monthly',
            'yesterday' => 'daily',
            'lastWeek' => 'weekly',
            'lastMonth' => 'monthly',
            'allTime' => 'period'
        ];

        $backendPeriod = $periodMapping[$period] ?? $period;

        $this->info("Calculating rankings for {$period} period (mapped to {$backendPeriod}) in {$region}...");

        try {
            $this->rankingService->calculateRankings($backendPeriod, $region);
            $this->info("Rankings calculated successfully for {$period} period in {$region}!");
            return 0;
        } catch (\Exception $e) {
            $this->error("Error calculating rankings: " . $e->getMessage());
            return 1;
        }
    }
} 