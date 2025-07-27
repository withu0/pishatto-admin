<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\RankingController;
use Illuminate\Http\Request;

class TestRanking extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ranking:test {--userType=cast} {--timePeriod=current} {--category=gift} {--area=全国}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the ranking functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userType = $this->option('userType');
        $timePeriod = $this->option('timePeriod');
        $category = $this->option('category');
        $area = $this->option('area');

        $this->info("Testing ranking with parameters:");
        $this->line("User Type: {$userType}");
        $this->line("Time Period: {$timePeriod}");
        $this->line("Category: {$category}");
        $this->line("Area: {$area}");

        // Create a mock request
        $request = new Request();
        $request->query->set('userType', $userType);
        $request->query->set('timePeriod', $timePeriod);
        $request->query->set('category', $category);
        $request->query->set('area', $area);

        try {
            $controller = new RankingController();
            $response = $controller->getRanking($request);
            
            $data = json_decode($response->getContent(), true);
            
            if (isset($data['data'])) {
                $this->info("✅ Ranking calculation successful!");
                $this->line("Found " . count($data['data']) . " results");
                
                if (count($data['data']) > 0) {
                    $this->line("\nTop 5 results:");
                    foreach (array_slice($data['data'], 0, 5) as $index => $item) {
                        $this->line(($index + 1) . ". {$item['name']} - {$item['points']} points");
                    }
                }
            } else {
                $this->error("❌ No data returned");
                $this->line("Response: " . $response->getContent());
            }
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            $this->line("Stack trace: " . $e->getTraceAsString());
        }
    }
} 