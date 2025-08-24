<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UpdateMessagesRecipientTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Update all existing messages to have recipient_type = 'both' as default
        DB::table('messages')
            ->whereNull('recipient_type')
            ->orWhere('recipient_type', '')
            ->update(['recipient_type' => 'both']);
        
        $this->command->info('Updated existing messages with default recipient_type = "both"');
    }
}
