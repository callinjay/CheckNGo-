<?php
/**
 * services/ReminderService.php
 *
 * Two responsibilities:
 *  1. generateForSchedule() — given a departure schedule and a set of
 *     minute-offsets, create the four reminder rows (preparation,
 *     checklist, final_check, departure).
 *  2. processDueReminders() — the engine behind cron/process-reminders.php.
 *     Finds reminders whose time has arrived, looks at the linked
 *     checklist (session if one exists yet, otherwise the template) to
 *     find unchecked critical/important items, and writes a notification.
 *     Reminders are marked "sent" so they are never processed twice.
 *
 * IMPORTANT: PHP has no way to push a notification once the browser tab
 * is closed. This service only WRITES rows to the `notifications` table.
 * Something has to actually run processDueReminders() on a schedule —
 * either the XAMPP-friendly manual button on reminders.php, or a real
 * cron job / Windows Task Scheduler entry pointed at
 * cron/process-reminders.php. See README for exact setup steps.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Reminder.php';
require_once __DIR__ . '/../models/Notification.php';

class ReminderService
{
    private const MESSAGES = [
        'preparation' => 'preparation is starting soon.',
        'checklist'   => 'Have you checked your essentials?',
        'final_check' => 'Your departure is approaching. Review your unchecked items.',
        'departure'   => 'Your scheduled departure time has arrived.',
    ];

    /**
     * @param array<string,int> $offsetsMinutes e.g. ['preparation'=>30,'checklist'=>15,'final_check'=>5,'departure'=>0]
     *   Each value is minutes BEFORE departure_time. Only keys present are created.
     */
    public static function generateForSchedule(int $userId, int $scheduleId, string $departureDate, string $departureTime, array $offsetsMinutes): int
    {
        $departureAt = new DateTimeImmutable($departureDate . ' ' . $departureTime);
        $created = 0;

        foreach ($offsetsMinutes as $type => $minutesBefore) {
            if (!in_array($type, Reminder::TYPES, true)) {
                continue;
            }
            $when = $departureAt->sub(new DateInterval('PT' . max(0, (int) $minutesBefore) . 'M'));
            $ok = Reminder::createIfMissing($userId, $scheduleId, $type, $when->format('Y-m-d H:i:s'));
            if ($ok) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Processes every due, pending reminder system-wide. Safe to call
     * repeatedly (e.g. every minute from cron) — each reminder is only
     * ever sent once because it is marked "sent" inside the same
     * transaction that creates its notification.
     *
     * @return array{processed:int, failed:int}
     */
    public static function processDueReminders(): array
    {
        $db = get_db();
        $due = Reminder::findDue();
        $processed = 0;
        $failed = 0;

        foreach ($due as $reminder) {
            $db->beginTransaction();
            try {
                // Re-check status inside the transaction to avoid a double-send
                // if two processor runs overlap.
                $lock = $db->prepare('SELECT status FROM reminders WHERE id = :id FOR UPDATE');
                $lock->execute(['id' => $reminder['id']]);
                $current = $lock->fetch();
                if (!$current || $current['status'] !== 'pending') {
                    $db->rollBack();
                    continue;
                }

                $context = self::buildContext((int) $reminder['departure_schedule_id']);
                [$title, $message] = self::composeMessage($reminder['reminder_type'], $context);

                Notification::create(
                    (int) $reminder['user_id'],
                    (int) $reminder['id'],
                    $title,
                    $message,
                    $reminder['reminder_type']
                );

                Reminder::updateStatus((int) $reminder['id'], 'sent');
                $db->commit();
                $processed++;
            } catch (Throwable $e) {
                $db->rollBack();
                error_log('[CheckNGo] Reminder #' . $reminder['id'] . ' failed: ' . $e->getMessage());
                Reminder::updateStatus((int) $reminder['id'], 'failed');
                $failed++;
            }
        }

        return ['processed' => $processed, 'failed' => $failed];
    }

    /** Gathers activity name + unchecked required items for a schedule, from a live session if one exists, else the template. */
    private static function buildContext(int $scheduleId): array
    {
        $db = get_db();

        $schedStmt = $db->prepare(
            'SELECT ds.*, a.activity_name FROM departure_schedules ds
             JOIN activities a ON a.id = ds.activity_id WHERE ds.id = :id'
        );
        $schedStmt->execute(['id' => $scheduleId]);
        $schedule = $schedStmt->fetch();
        if (!$schedule) {
            return ['activity_name' => 'your activity', 'unchecked' => []];
        }

        $sessionStmt = $db->prepare(
            'SELECT id FROM checklist_sessions WHERE departure_schedule_id = :sid AND session_status = "in_progress"
             ORDER BY created_at DESC LIMIT 1'
        );
        $sessionStmt->execute(['sid' => $scheduleId]);
        $session = $sessionStmt->fetch();

        if ($session) {
            $itemsStmt = $db->prepare(
                'SELECT item_name_snapshot AS item_name FROM checklist_session_items
                 WHERE session_id = :sid AND is_checked = 0 AND is_required_snapshot = 1
                 ORDER BY FIELD(priority_snapshot,"critical","important","optional") LIMIT 5'
            );
            $itemsStmt->execute(['sid' => $session['id']]);
        } else {
            // No session started yet — fall back to the template's required items.
            $itemsStmt = $db->prepare(
                'SELECT b.item_name FROM checklist_template_items cti
                 JOIN belongings b ON b.id = cti.belonging_id
                 WHERE cti.template_id = :tid AND cti.is_required = 1 AND b.is_active = 1
                 ORDER BY FIELD(cti.priority,"critical","important","optional") LIMIT 5'
            );
            $itemsStmt->execute(['tid' => $schedule['template_id']]);
        }

        return [
            'activity_name' => $schedule['activity_name'],
            'unchecked' => array_column($itemsStmt->fetchAll(), 'item_name'),
        ];
    }

    /** @return array{0:string,1:string} [title, message] */
    private static function composeMessage(string $type, array $context): array
    {
        $activity = $context['activity_name'];
        $base = self::MESSAGES[$type] ?? 'Reminder for your upcoming activity.';

        $title = ucfirst($activity) . ' — ' . str_replace('_', ' ', ucwords($type, '_'));

        if ($type === 'preparation') {
            $message = ucfirst($activity) . ' ' . $base;
        } else {
            $message = $base;
        }

        if (!empty($context['unchecked']) && in_array($type, ['checklist', 'final_check'], true)) {
            $message .= ' Still unconfirmed: ' . implode(', ', $context['unchecked']) . '.';
        }

        return [$title, $message];
    }
}
