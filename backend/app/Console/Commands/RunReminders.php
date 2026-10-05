<?php

namespace App\Console\Commands;

use App\Services\AutomationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunReminders extends Command
{
    protected $signature = 'automation:run-reminders';

    protected $description = 'Send any due follow-up reminders (quotation / survey) and survey-day countdown emails';

    public function handle(AutomationService $automation): int
    {
        try {
            $sent    = $automation->advanceReminders();
            $daysSent = $automation->fireDueSurveyReminders();
            $quoteRemindersSent = $automation->fireDueQuotationReminders();

            $total = $sent + $daysSent + $quoteRemindersSent;
            $this->info("Reminder sweep complete — {$sent} follow-up(s) + {$daysSent} survey-day reminder(s) + {$quoteRemindersSent} quote reminder(s) = {$total} total sent.");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('Reminder sweep failed', ['error' => $e->getMessage()]);
            $this->error('Reminder sweep failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
