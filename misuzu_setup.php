<?php
/**
 * Setup script
 */

namespace Misuzu;

if (PHP_SAPI !== 'cli') {
    echo 'This can only be run from a CLI, if you can access this from a web browser your configuration is bad.';
    exit;
}

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
}

// Ensures all users are in the main role.
$db->query('
    INSERT INTO `msz_user_roles`
        (`user_id`, `role_id`)
    SELECT `user_id`, 1 FROM `msz_users` as u
    WHERE NOT EXISTS (
        SELECT 1
        FROM `msz_user_roles` as ur
        WHERE `role_id` = 1
        AND u.`user_id` = ur.`user_id`
    )
');

// Ensures all display_role values are correct with `msz_user_roles`
$db->query('
    UPDATE `msz_users` as u
    SET `display_role` = (
         SELECT ur.`role_id`
         FROM `msz_user_roles` as ur
         LEFT JOIN `msz_roles` as r
         ON r.`role_id` = ur.`role_id`
         WHERE ur.`user_id` = u.`user_id`
         ORDER BY `role_hierarchy` DESC
         LIMIT 1
    )
    WHERE NOT EXISTS (
        SELECT 1
        FROM `msz_user_roles` as ur
        WHERE ur.`role_id` = u.`display_role`
        AND `ur`.`user_id` = u.`user_id`
    )
');
