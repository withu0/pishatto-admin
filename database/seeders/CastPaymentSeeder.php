<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Payment;
use App\Models\Cast;

class CastPaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get some existing casts or create them if they don't exist
        $casts = Cast::take(5)->get();
        
        if ($casts->isEmpty()) {
            // Create some sample casts if none exist
            $casts = Cast::factory(5)->create();
        }

        $paymentMethods = ['card', 'bank_transfer', 'linepay', 'convenience_store', 'other'];
        $statuses = ['pending', 'paid', 'failed', 'refunded'];
        $descriptions = [
            '7月分の給与',
            '6月分の給与', 
            '5月分の給与',
            '4月分の給与',
            '3月分の給与',
            '2月分の給与',
            '1月分の給与',
            '12月分の給与',
            '11月分の給与',
            '10月分の給与'
        ];

        foreach ($casts as $cast) {
            // Create 2-4 payments per cast
            $numPayments = rand(2, 4);
            
            for ($i = 0; $i < $numPayments; $i++) {
                $amount = rand(2000, 8000) * 100; // Random amount between 200,000 and 800,000 yen
                $status = $statuses[array_rand($statuses)];
                $paymentMethod = $paymentMethods[array_rand($paymentMethods)];
                $description = $descriptions[array_rand($descriptions)];
                
                $payment = Payment::create([
                    'user_id' => $cast->id,
                    'user_type' => 'cast',
                    'amount' => $amount,
                    'status' => $status,
                    'payment_method' => $paymentMethod,
                    'description' => $description,
                    'paid_at' => $status === 'paid' ? now()->subDays(rand(1, 30)) : null,
                    'failed_at' => $status === 'failed' ? now()->subDays(rand(1, 30)) : null,
                    'refunded_at' => $status === 'refunded' ? now()->subDays(rand(1, 30)) : null,
                    'created_at' => now()->subDays(rand(30, 90)),
                    'updated_at' => now()->subDays(rand(1, 30)),
                ]);

                // Update cast points if payment is paid
                if ($status === 'paid') {
                    $cast->points = ($cast->points ?? 0) + $amount;
                    $cast->save();
                }
            }
        }

        $this->command->info('Cast payments seeded successfully!');
    }
} 