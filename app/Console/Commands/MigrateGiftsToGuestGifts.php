<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Message;
use App\Models\GuestGift;

class MigrateGiftsToGuestGifts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gifts:migrate-to-guest-gifts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate existing gift messages to guest_gifts table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting migration of gifts to guest_gifts table...');

        // Get all messages that have a gift_id (gifts sent by guests to casts)
        $giftMessages = Message::with(['chat', 'guest'])
            ->whereNotNull('gift_id')
            ->whereNotNull('sender_guest_id')
            ->get();

        $this->info("Found {$giftMessages->count()} gift messages to migrate");

        $migrated = 0;
        $skipped = 0;

        foreach ($giftMessages as $message) {
            // Check if this gift is already in guest_gifts table
            $existingGift = GuestGift::where([
                'sender_guest_id' => $message->sender_guest_id,
                'receiver_cast_id' => $message->chat->cast_id,
                'gift_id' => $message->gift_id,
                'created_at' => $message->created_at,
            ])->first();

            if ($existingGift) {
                $skipped++;
                continue;
            }

            // Create new guest_gift record
            GuestGift::create([
                'sender_guest_id' => $message->sender_guest_id,
                'receiver_cast_id' => $message->chat->cast_id,
                'gift_id' => $message->gift_id,
                'message' => $message->message,
                'created_at' => $message->created_at,
            ]);

            $migrated++;
        }

        $this->info("Migration completed!");
        $this->info("Migrated: {$migrated} gifts");
        $this->info("Skipped: {$skipped} gifts (already existed)");

        return 0;
    }
} 