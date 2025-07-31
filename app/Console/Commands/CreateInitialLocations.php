<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateInitialLocations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'locations:create-initial';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create initial locations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $locations = [
            ['name' => '東京都', 'prefecture' => '東京都', 'is_active' => true, 'sort_order' => 1],
            ['name' => '大阪府', 'prefecture' => '大阪府', 'is_active' => true, 'sort_order' => 2],
            ['name' => '愛知県', 'prefecture' => '愛知県', 'is_active' => true, 'sort_order' => 3],
            ['name' => '福岡県', 'prefecture' => '福岡県', 'is_active' => true, 'sort_order' => 4],
            ['name' => '北海道', 'prefecture' => '北海道', 'is_active' => true, 'sort_order' => 5],
        ];

        foreach ($locations as $location) {
            DB::table('locations')->insert($location);
            $this->info("Created location: {$location['name']}");
        }

        $this->info('All initial locations created successfully!');
    }
} 