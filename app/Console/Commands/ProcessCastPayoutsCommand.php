<?php

namespace App\Console\Commands;

use App\Services\CastPayoutService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessCastPayoutsCommand extends Command
{
    protected $signature = 'casts:process-payouts {--date= : 指定日付（YYYY-MM-DD）。省略時は本日}';

    protected $description = '振込予定日を迎えたキャストへの振込処理を実行します。';

    public function __construct(private CastPayoutService $castPayoutService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $date = null;
        if ($this->option('date')) {
            try {
                $date = Carbon::createFromFormat('Y-m-d', $this->option('date'));
            } catch (\Exception $e) {
                $this->error('日付は YYYY-MM-DD 形式で指定してください。');
                return self::FAILURE;
            }
        }

        $processed = $this->castPayoutService->processDuePayouts($date);
        $this->info("{$processed} 件の振込処理を開始しました。");

        return self::SUCCESS;
    }
}


