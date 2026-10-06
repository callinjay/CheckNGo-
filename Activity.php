<?php
/**
 * models/Activity.php
 * Manages the activities table. CheckNGo ships six built-in categories;
 * each user gets their own row per category (lazily created) so their
 * destination label and template stay personal.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Activity
{
    /** type => [label, icon (FontAwesome), default destination, description] */
    public const CATEGORIES = [
        'school'      => ['School',      'fa-graduation-cap', 'Campus',        'Classes, requirements, and daily essentials.'],
        'work'        => ['Work',        'fa-briefcase',      'Office',        'Everything you need for a productive work day.'],
        'travel'      => ['Travel',      'fa-plane',          'Trip',          'Documents and essentials for departures.'],
        'personal'    => ['Personal',    'fa-user',           'Errands',       'Everyday carry for personal activities.'],
        'examination' => ['Examination', 'fa-file-pen',       'Exam Room',     'Permits, materials, and exam-day must-haves.'],
        'laboratory'  => ['Laboratory',  'fa-flask',          'Laboratory',    'Equipment and materials for lab sessions.'],
    ];

    public static function listForUser(int $userId): array
    {
        $stmt = get_db()->prepare('SELECT * FROM activities WHERE user_id = :uid');
        $stmt->execute(['uid' => $userId]);
        $rows = $stmt->fetchAll();

        $byType = [];
        foreach ($rows as $row) {
            $byType[$row['activity_type']] = $row;
        }
        return $byType;
    }

    public static function findById(int $activityId, int $userId): ?array
    {
        $stmt = get_db()->prepare('SELECT * FROM activities WHERE id = :id AND user_id = :uid LIMIT 1');
        $stmt->execute(['id' => $activityId, 'uid' => $userId]);
        return $stmt->fetch() ?: null;
    }

    /** Finds the user's activity row for a built-in type, creating it on first use. */
    public static function getOrCreateByType(int $userId, string $type): array
    {
        if (!isset(self::CATEGORIES[$type])) {
            throw new InvalidArgumentException('Unknown activity type: ' . $type);
        }

        $stmt = get_db()->prepare(
            'SELECT * FROM activities WHERE user_id = :uid AND activity_type = :type LIMIT 1'
        );
        $stmt->execute(['uid' => $userId, 'type' => $type]);
        $existing = $stmt->fetch();
        if ($existing) {
            return $existing;
        }

        [$label, $icon, $destination] = self::CATEGORIES[$type];

        $insert = get_db()->prepare(
            'INSERT INTO activities (user_id, activity_name, activity_type, destination_label, icon)
             VALUES (:uid, :name, :type, :dest, :icon)'
        );
        $insert->execute([
            'uid'  => $userId,
            'name' => $label,
            'type' => $type,
            'dest' => $destination,
            'icon' => $icon,
        ]);

        return self::findById((int) get_db()->lastInsertId(), $userId);
    }

    public static function updateDestination(int $activityId, int $userId, string $destinationLabel): bool
    {
        $stmt = get_db()->prepare(
            'UPDATE activities SET destination_label = :dest WHERE id = :id AND user_id = :uid'
        );
        return $stmt->execute(['dest' => $destinationLabel, 'id' => $activityId, 'uid' => $userId]);
    }
}
