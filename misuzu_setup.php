<?php
/**
 * Setup script
 * @todo Move this into a CLI commands system.
 */

namespace Misuzu;

use Misuzu\Database;

require_once __DIR__ . '/misuzu.php';

$db = Database::connection();

$mainRoleId = (int)$db->query('
    SELECT `role_id`
    FROM `msz_roles`
    WHERE `role_id` = 1
')->fetchColumn();

if ($mainRoleId !== 1) {
    $db->query("
        REPLACE INTO `msz_roles`
            (`role_id`, `role_name`, `role_hierarchy`, `role_colour`, `role_description`, `created_at`)
        VALUES
            (1, 'Member', 1, 1073741824, NULL, NOW())
    ");

    $mainRoleId = 1;
}

$notInMainRole = $db->query('
    SELECT `user_id`
    FROM `msz_users` as u
    WHERE NOT EXISTS (
        SELECT 1
        FROM `msz_user_roles` as ur
        WHERE `role_id` = 1
        AND u.`user_id` = ur.`user_id`
    )
')->fetchAll();

if (count($notInMainRole) < 1) {
    exit;
}

$addToMainRole = $db->prepare('
    INSERT INTO `msz_user_roles`
        (`user_id`, `role_id`)
    VALUES
        (:user_id, 1)
');

foreach ($notInMainRole as $user) {
    $addToMainRole->execute($user);
}
