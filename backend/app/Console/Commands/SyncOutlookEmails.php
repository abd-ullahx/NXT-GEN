<?php

namespace App\Console\Commands;

use App\Services\OutlookService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncOutlookEmails extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'outlook:sync {--count=50 : Number of recent messages to fetch}';

    /**
     * The console command description.
     */
    protected $description = 'Fetch new Outlook emails and auto-create leads from provider notifications';

    public function handle(OutlookService $outlook): int
    {
        // Skip quietly if no account is connected yet.
        $status = $outlook->getConnectionStatus();
        if (empty($status['connected'])) {
            $this->info('Outlook not connected — skipping sync.');
            return self::SUCCESS;
        }

        try {
            $synced = $outlook->syncEmails((int) $this->option('count'));
            $this->info("Synced {$synced} new email(s).");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('Scheduled Outlook sync failed', ['error' => $e->getMessage()]);
            $this->error('Sync failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
