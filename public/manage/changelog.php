<?php
require_once '../../misuzu.php';

$changelogPerms = perms_get_user(user_session_current('user_id', 0))[MSZ_PERMS_CHANGELOG];

switch ($_GET['v'] ?? null) {
    default:
    case 'changes':
        if (!perms_check($changelogPerms, MSZ_PERM_CHANGELOG_MANAGE_CHANGES)) {
            echo render_error(403);
            break;
        }

        $changesCount = (int)db_query('
            SELECT COUNT(`change_id`)
            FROM `msz_changelog_changes`
        ')->fetchColumn();

        $changelogPagination = pagination_create($changesCount, 30);
        $changelogOffset = pagination_offset($changelogPagination, pagination_param());

        if (!pagination_is_valid_offset($changelogOffset)) {
            echo render_error(404);
            break;
        }

        $getChanges = db_prepare('
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
        $getChanges->bindValue('take', $changelogPagination['range']);
        $getChanges->bindValue('offset', $changelogOffset);
        $changes = db_fetch_all($getChanges);

        $getTags = db_prepare('
            SELECT
                t.`tag_id`, t.`tag_name`, t.`tag_description`
            FROM `msz_changelog_change_tags` as ct
            LEFT JOIN `msz_changelog_tags` as t
            ON t.`tag_id` = ct.`tag_id`
            WHERE ct.`change_id` = :change_id
        ');

        // grab tags
        for ($i = 0; $i < count($changes); $i++) {
            $getTags->bindValue('change_id', $changes[$i]['change_id']);
            $changes[$i]['tags'] = db_fetch_all($getTags);
        }

        echo tpl_render('manage.changelog.changes', [
            'changelog_changes' => $changes,
            'changelog_changes_count' => $changesCount,
            'changelog_pagination' => $changelogPagination,
        ]);
        break;

    case 'change':
        if (!perms_check($changelogPerms, MSZ_PERM_CHANGELOG_MANAGE_CHANGES)) {
            echo render_error(403);
            break;
        }

        $changeId = (int)($_GET['c'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify('changelog_add', $_POST['csrf'] ?? '')) {
            if (!empty($_POST['change']) && is_array($_POST['change'])) {
                if ($changeId > 0) {
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

                if ($changeId < 1) {
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

        tpl_var('changelog_actions', $actions);

        if ($changeId > 0) {
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
                header('Location: ?v=changes');
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

        echo tpl_render('manage.changelog.change_edit', [
            'edit_change' => $change ?? null,
            'edit_change_tags' => $changeTags,
        ]);
        break;

    case 'tags':
        $canManageTags = perms_check($changelogPerms, MSZ_PERM_CHANGELOG_MANAGE_TAGS);

        if (!$canManageTags) {
            echo render_error(403);
            break;
        }

        if ($canManageTags) {
            $getTags = db_prepare('
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
            tpl_var('changelog_tags', db_fetch_all($getTags));
        }

        echo tpl_render('manage.changelog.tags');
        break;

    case 'tag':
        if (!perms_check($changelogPerms, MSZ_PERM_CHANGELOG_MANAGE_TAGS)) {
            echo render_error(403);
            break;
        }

        $tagId = (int)($_GET['t'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify('changelog_tag', $_POST['csrf'] ?? '')) {
            if (!empty($_POST['tag']) && is_array($_POST['tag'])) {
                if ($tagId > 0) {
                    $updateTag = db_prepare('
                        UPDATE `msz_changelog_tags`
                        SET `tag_name` = :name,
                            `tag_description` = :description,
                            `tag_archived` = :archived
                        WHERE `tag_id` = :id
                    ');
                    $updateTag->bindValue('id', $tagId);
                } else {
                    $updateTag = db_prepare('
                        INSERT INTO `msz_changelog_tags`
                            (`tag_name`, `tag_description`, `tag_archived`)
                        VALUES
                            (:name, :description, :archived)
                    ');
                }

                $updateTag->bindValue('name', $_POST['tag']['name']);
                $updateTag->bindValue('description', $_POST['tag']['description']);
                // this is fine, after being archived there shouldn't be any other changes being made
                $updateTag->bindValue('archived', empty($_POST['tag']['archived']) ? null : date('Y-m-d H:i:s'));
                $updateTag->execute();

                if ($tagId < 1) {
                    $tagId = db_last_insert_id();
                    audit_log(MSZ_AUDIT_CHANGELOG_TAG_EDIT, user_session_current('user_id', 0), [$tagId]);
                    header('Location: ?v=tag&t=' . $tagId);
                    return;
                } else {
                    audit_log(MSZ_AUDIT_CHANGELOG_TAG_CREATE, user_session_current('user_id', 0), [$tagId]);
                }
            }
        }

        if ($tagId > 0) {
            $getTag = db_prepare('
                SELECT `tag_id`, `tag_name`, `tag_description`, `tag_archived`, `tag_created`
                FROM `msz_changelog_tags`
                WHERE `tag_id` = :tag_id
            ');
            $getTag->bindValue('tag_id', $tagId);
            $tag = db_fetch($getTag);

            if ($tag) {
                tpl_var('edit_tag', $tag);
            } else {
                header('Location: ?v=tags');
                return;
            }
        }

        echo tpl_render('manage.changelog.tag_edit');
        break;
}
