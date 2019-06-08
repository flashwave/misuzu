<?php
require_once '../../../misuzu.php';

if(!perms_check_user(MSZ_PERMS_CHANGELOG, user_session_current('user_id'), MSZ_PERM_CHANGELOG_MANAGE_CHANGES)) {
    echo render_error(403);
    return;
}

$changeId = (int)($_GET['c'] ?? 0);

if($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify('changelog_add', $_POST['csrf'] ?? '')) {
    if(!empty($_POST['change']) && is_array($_POST['change'])) {
        if($changeId > 0) {
            $postChange = db_prepare('
                UPDATE `msz_changelog_changes`
                SET `change_log` = :log,
                    `change_text` = :text,
                    `change_action` = :action,
                    `user_id` = :user,
                    `change_created` = :created
                WHERE `change_id` = :change_id
            ');
            $postChange->bindValue('change_id', $changeId);
        } else {
            $postChange = db_prepare('
                INSERT INTO `msz_changelog_changes`
                    (
                        `change_log`, `change_text`, `change_action`,
                        `user_id`, `change_created`
                    )
                VALUES
                    (:log, :text, :action, :user, :created)
            ');
        }

        $postChange->bindValue('log', $_POST['change']['log']);
        $postChange->bindValue('action', $_POST['change']['action']);
        $postChange->bindValue('text', strlen($_POST['change']['text'])
            ? $_POST['change']['text']
            : null);
        $postChange->bindValue('user', is_numeric($_POST['change']['user'])
            ? $_POST['change']['user']
            : null);
        $postChange->bindValue('created', strlen($_POST['change']['created'])
            ? $_POST['change']['created']
            : null);
        $postChange->execute();

        if($changeId < 1) {
            $changeId = db_last_insert_id();
            audit_log(MSZ_AUDIT_CHANGELOG_ENTRY_CREATE, user_session_current('user_id', 0), [$changeId]);
        } else {
            audit_log(MSZ_AUDIT_CHANGELOG_ENTRY_EDIT, user_session_current('user_id', 0), [$changeId]);
        }
    }

    if(!empty($_POST['tags']) && is_array($_POST['tags']) && array_test($_POST['tags'], 'ctype_digit')) {
        $setTags = array_apply($_POST['tags'], 'intval');

        $removeTags = db_prepare(sprintf('
            DELETE FROM `msz_changelog_change_tags`
            WHERE `change_id` = :change_id
            AND `tag_id` NOT IN (%s)
        ', implode(',', $setTags)));
        $removeTags->bindValue('change_id', $changeId);
        $removeTags->execute();

        $addTag = db_prepare('
            INSERT IGNORE INTO `msz_changelog_change_tags`
                (`change_id`, `tag_id`)
            VALUES
                (:change_id, :tag_id)
        ');
        $addTag->bindValue('change_id', $changeId);

        foreach ($setTags as $role) {
            $addTag->bindValue('tag_id', $role);
            $addTag->execute();
        }
    }
}

$actions = [
    ['action_id' => MSZ_CHANGELOG_ACTION_ADD, 'action_name' => 'Added'],
    ['action_id' => MSZ_CHANGELOG_ACTION_REMOVE, 'action_name' => 'Removed'],
    ['action_id' => MSZ_CHANGELOG_ACTION_UPDATE, 'action_name' => 'Updated'],
    ['action_id' => MSZ_CHANGELOG_ACTION_FIX, 'action_name' => 'Fixed'],
    ['action_id' => MSZ_CHANGELOG_ACTION_IMPORT, 'action_name' => 'Imported'],
    ['action_id' => MSZ_CHANGELOG_ACTION_REVERT, 'action_name' => 'Reverted'],
];

if($changeId > 0) {
    $getChange = db_prepare('
        SELECT
            `change_id`, `change_log`, `change_text`, `user_id`,
            `change_action`, `change_created`
        FROM `msz_changelog_changes`
        WHERE `change_id` = :change_id
    ');
    $getChange->bindValue('change_id', $changeId);
    $change = db_fetch($getChange);

    if(!$change) {
        url_redirect('manage-changelog-changes');
        return;
    }
}

$getChangeTags = db_prepare('
    SELECT
        ct.`tag_id`, ct.`tag_name`,
        (
            SELECT COUNT(`change_id`) > 0
            FROM `msz_changelog_change_tags`
            WHERE `tag_id` = ct.`tag_id`
            AND `change_id` = :change_id
        ) AS `has_tag`
    FROM `msz_changelog_tags` AS ct
');
$getChangeTags->bindValue('change_id', $change['change_id'] ?? 0);
$changeTags = db_fetch_all($getChangeTags);

echo tpl_render('manage.changelog.change', [
    'change' => $change ?? null,
    'change_tags' => $changeTags,
    'change_actions' => $actions,
]);
