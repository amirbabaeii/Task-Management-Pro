<?php

namespace App\Console\Commands;

use App\Actions\Tasks\SendTaskDueRemindersAction;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class SendTaskDueRemindersCommand extends Command
{
    protected $signature = 'tasks:send-due-reminders {--date= : Run reminders for a specific date (YYYY-MM-DD)}';

    protected $description = 'Send daily reminders for overdue and due-today assigned tasks.';

    public function handle(SendTaskDueRemindersAction $sendTaskDueReminders): int
    {
        try {
            $date = $this->option('date')
                ? Carbon::parse((string) $this->option('date'))->startOfDay()
                : now()->startOfDay();
        } catch (Throwable) {
            $this->error('The --date option must be a valid date.');

            return self::FAILURE;
        }

        $sent = $sendTaskDueReminders->execute($date);
        $this->info(sprintf(
            'Sent %d task deadline reminder%s.',
            $sent,
            $sent === 1 ? '' : 's',
        ));

        return self::SUCCESS;
    }
}
