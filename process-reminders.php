<?php
/**
 * cron/process-reminders.php
 *
 * Run this on a schedule to turn due reminders into in-app notifications:
 *
 *   Linux / macOS (crontab -e), every minute:
 *     * * * * * /usr/bin/php /path/to/checkngo/cron/process-reminders.php >> /path/to/checkngo/cron/reminder.log 2>&1
 *
 *   Windows (XAMPP), Task Scheduler, every minute:
 *     Program: C:\xampp\php\php.exe
 *     Arguments: C:\xampp\htdocs\checkngo\cron\process-reminders.php
 *
 * This script does NOT send browser push notifications — PHP cannot do
 * that on its own without a configured push service (which this project
 * intentionally avoids, per the "no paid APIs" requirement). It writes
 * rows to the `notifications` table, which notifications.php then displays
 * the next time the user opens the app.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('This script is intended to run from the command line (cron / Task Scheduler), not a browser.');
}

require_once __DIR__ . '/../services/ReminderService.php';

$result = ReminderService::processDueReminders();

$timestamp = date('Y-m-d H:i:s');
echo "[{$timestamp}] Processed: {$result['processed']}, Failed: {$result['failed']}" . PHP_EOL;
