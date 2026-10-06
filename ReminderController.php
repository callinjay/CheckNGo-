<?php
/**
 * controllers/ReminderController.php
 * Handles POST actions from reminders.php: create a departure schedule
 * (which also generates its reminder rows), or cancel one.
 */

declare(strict_types=1);

require_once __DIR__ . '/../models/Schedule.php';
require_once __DIR__ . '/../models/Activity.php';
require_once __DIR__ . '/../models/Checklist.php';
require_once __DIR__ . '/../services/ReminderService.php';

class ReminderController
{
    /** @return array{success:bool, error:?string} */
    public static function handle(array $post, int $userId): array
    {
        $action = $post['action'] ?? '';

        try {
            switch ($action) {
                case 'create_schedule':
                    return self::createSchedule($post, $userId);
                case 'cancel_schedule':
                    $id = (int) ($post['schedule_id'] ?? 0);
                    Schedule::cancel($id, $userId);
                    return ['success' => true, 'error' => null];
                case 'process_now':
                    $result = ReminderService::processDueReminders();
                    return ['success' => true, 'error' => null, 'processed' => $result['processed'], 'failed' => $result['failed']];
                default:
                    return ['success' => false, 'error' => 'Unknown action.'];
            }
        } catch (Throwable $e) {
            error_log('[CheckNGo] ReminderController error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Something went wrong. Please try again.'];
        }
    }

    private static function createSchedule(array $post, int $userId): array
    {
        $activityType = (string) ($post['activity_type'] ?? '');
        $date = (string) ($post['departure_date'] ?? '');
        $time = (string) ($post['departure_time'] ?? '');

        if (!isset(Activity::CATEGORIES[$activityType])) {
            return ['success' => false, 'error' => 'Please choose a valid activity.'];
        }

        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj) {
            return ['success' => false, 'error' => 'Please choose a valid departure date.'];
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            return ['success' => false, 'error' => 'Please choose a valid departure time.'];
        }

        $departureAt = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $time);
        if ($departureAt < new DateTime('-5 minutes')) {
            return ['success' => false, 'error' => 'Departure time must be in the future.'];
        }

        $activity = Activity::getOrCreateByType($userId, $activityType);
        $template = Checklist::getOrCreateDefaultTemplate($userId, (int) $activity['id']);
        if (Checklist::itemCount((int) $template['id']) === 0) {
            return ['success' => false, 'error' => 'Set up this activity\'s checklist template before scheduling a departure.'];
        }

        $scheduleId = Schedule::create($userId, (int) $activity['id'], (int) $template['id'], $date, $time);

        $offsets = [];
        foreach (['preparation' => 30, 'checklist' => 15, 'final_check' => 5, 'departure' => 0] as $type => $default) {
            if (isset($post['reminder_' . $type])) {
                $minutes = (int) ($post['offset_' . $type] ?? $default);
                $offsets[$type] = max(0, $minutes);
            }
        }
        if (empty($offsets)) {
            $offsets = ['preparation' => 30, 'checklist' => 15, 'final_check' => 5, 'departure' => 0];
        }

        ReminderService::generateForSchedule($userId, $scheduleId, $date, $time, $offsets);

        return ['success' => true, 'error' => null];
    }
}
