<?php
namespace Misuzu;

require_once '../../../misuzu.php';

if(!perms_check_user(MSZ_PERMS_CHANGELOG, user_session_current('user_id'), MSZ_PERM_CHANGELOG_MANAGE_TAGS)) {
    echo render_error(403);
    return;
}

$getTags = DB::prepare('
    SELECT
        t.`tag_id`, t.`tag_name`, t.`tag_description`, t.`tag_created`,
        (
            SELECT COUNT(ct.`change_id`)
            FROM `msz_changelog_change_tags` as ct
            WHERE ct.`tag_id` = t.`tag_id`
        ) as `tag_count`
    FROM `msz_changelog_tags` as t
    ORDER BY t.`tag_id` ASC
');

echo tpl_render('manage.changelog.tags', [
    'changelog_tags' => $getTags->fetchAll(),
]);
