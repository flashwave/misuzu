<?php
namespace Misuzu;

require_once '../../../misuzu.php';

if(!perms_check_user(MSZ_PERMS_CHANGELOG, user_session_current('user_id'), MSZ_PERM_CHANGELOG_MANAGE_CHANGES)) {
    echo render_error(403);
    return;
}

$changesCount = (int)DB::query('
    SELECT COUNT(`change_id`)
    FROM `msz_changelog_changes`
')->fetchColumn();

$changelogPagination = pagination_create($changesCount, 30);
$changelogOffset = pagination_offset($changelogPagination, pagination_param());

if(!pagination_is_valid_offset($changelogOffset)) {
    echo render_error(404);
    return;
}

$getChanges = DB::prepare('
    SELECT
        c.`change_id`, c.`change_log`, c.`change_created`, c.`change_action`,
        u.`user_id`, u.`username`,
        COALESCE(u.`user_colour`, r.`role_colour`) AS `user_colour`,
        DATE(`change_created`) AS `change_date`,
        !ISNULL(c.`change_text`) AS `change_has_text`
    FROM `msz_changelog_changes` AS c
    LEFT JOIN `msz_users` AS u
    ON u.`user_id` = c.`user_id`
    LEFT JOIN `msz_roles` AS r
    ON r.`role_id` = u.`display_role`
    ORDER BY c.`change_id` DESC
    LIMIT :offset, :take
');
$getChanges->bind('take', $changelogPagination['range']);
$getChanges->bind('offset', $changelogOffset);
$changes = $getChanges->fetchAll();

$getTags = DB::prepare('
    SELECT
        t.`tag_id`, t.`tag_name`, t.`tag_description`
    FROM `msz_changelog_change_tags` as ct
    LEFT JOIN `msz_changelog_tags` as t
    ON t.`tag_id` = ct.`tag_id`
    WHERE ct.`change_id` = :change_id
');

// grab tags
for($i = 0; $i < count($changes); $i++) {
    $getTags->bind('change_id', $changes[$i]['change_id']);
    $changes[$i]['tags'] = $getTags->fetchAll();
}

echo tpl_render('manage.changelog.changes', [
    'changelog_changes' => $changes,
    'changelog_changes_count' => $changesCount,
    'changelog_pagination' => $changelogPagination,
]);
