<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Ranking;
use Carbon\Carbon;

class CalculateMonthlyEarnedRanking extends Command
{
    protected $signature = 'rankings:monthly-earned {--month=current : current|last}';

    protected $description = 'Calculate monthly earned rankings for casts based on point_transactions (gift + transfer)';

    public function handle()
    {
        $month = $this->option('month') === 'last' ? 'last' : 'current';
        $now = Carbon::now();
        if ($month === 'last') {
            $start = $now->copy()->subMonth()->startOfMonth();
            $end = $now->copy()->subMonth()->endOfMonth();
        } else {
            $start = $now->copy()->startOfMonth();
            $end = $now->copy()->endOfMonth();
        }

        $this->info("Calculating monthly earned rankings for {$month} ({$start->toDateString()} - {$end->toDateString()})");

        $totals = DB::table('point_transactions as pt')
            ->select('pt.cast_id', DB::raw('COALESCE(SUM(pt.amount), 0) as points'))
            ->whereNotNull('pt.cast_id')
            ->whereIn('pt.type', ['gift', 'transfer'])
            ->whereBetween('pt.created_at', [$start, $end])
            ->groupBy('pt.cast_id')
            ->get();

        $period = 'monthly';
        $region = '全国';
        $category = 'gift'; // semantic placeholder; we store monthly earned under gift category to reuse model

        $count = 0;
        foreach ($totals as $row) {
            Ranking::updateOrCreate(
                [
                    'type' => 'cast',
                    'category' => $category,
                    'user_id' => $row->cast_id,
                    'period' => $period,
                    'region' => $region,
                    'created_at' => $start,
                ],
                [
                    'points' => (int) $row->points,
                ]
            );
            $count++;
        }

        $this->info("Updated {$count} cast ranking rows.");
        return 0;
    }
}


