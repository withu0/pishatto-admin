<?php

namespace App\Console\Commands;

use App\Services\CastPayoutService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CloseCastPayoutsCommand extends Command
{
    protected $signature = 'casts:close-month {--month= : Target month in YYYY-MM. Defaults to previous month}';

    protected $description = '末締めでキャスト報酬を集計し、翌月末振込予定を作成します。';

    public function __construct(private CastPayoutService $castPayoutService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $monthOption = $this->option('month');
        $periodEnd = null;

        if ($monthOption) {
            try {
                $periodEnd = Carbon::createFromFormat('Y-m', $monthOption)->endOfMonth();
            } catch (\Exception $e) {
                $this->error('月は YYYY-MM 形式で指定してください。');
                return self::FAILURE;
            }
        }

        $created = $this->castPayoutService->closeMonthlyPeriod($periodEnd);
        $this->info("{$created} 件の振込予定を作成しました。");

        return self::SUCCESS;
    }
}


