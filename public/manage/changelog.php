<?php
require_once '../../misuzu.php';

$changelogPerms = perms_get_user(MSZ_PERMS_CHANGELOG, user_session_current('user_id', 0));

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
                c.`change_id`, c.`change_log`, c.`change_created`,
                a.`action_name`, a.`action_colour`, a.`action_class`,
                u.`user_id`, u.`username`,
                COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`,
                DATE(`change_created`) as `change_date`,
                !ISNULL(c.`change_text`) as `change_has_text`
            FROM `msz_changelog_changes` as c
            LEFT JOIN `msz_changelog_actions` as a
            ON a.`action_id` = c.`action_id`
            LEFT JOIN `msz_users` as u
            ON u.`user_id` = c.`user_id`
            LEFT JOIN `msz_roles` as r
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
                            `action_id` = :action,
                            `user_id` = :user,
                            `change_created` = :created
                        WHERE `change_id` = :change_id
                    ');
                    $postChange->bindValue('change_id', $changeId);
                } else {
                    $postChange = db_prepare('
                        INSERT INTO `msz_changelog_changes`
                            (
                                `change_log`, `change_text`, `action_id`,
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

        $actions = db_query('
            SELECT `action_id`, `action_name`
            FROM `msz_changelog_actions`
        ')->fetchAll(PDO::FETCH_ASSOC);
        tpl_var('changelog_actions', $actions);

        if ($changeId > 0) {
            $getChange = db_prepare('
                SELECT
                    `change_id`, `change_log`, `change_text`, `user_id`,
                    `action_id`, `change_created`
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
        $canManageActions = perms_check($changelogPerms, MSZ_PERM_CHANGELOG_MANAGE_ACTIONS);

        if (!$canManageTags && !$canManageActions) {
            echo render_error(403);
            break;
        }

        if ($canManageActions) {
            $getActions = db_prepare('
                SELECT
                    a.`action_id`, a.`action_name`, a.`action_colour`,
                    (
                        SELECT COUNT(c.`action_id`)
                        FROM `msz_changelog_changes` as c
                        WHERE c.`action_id` = a.`action_id`
                    ) as `action_count`
                FROM `msz_changelog_actions` as a
                ORDER BY a.`action_id` ASC
            ');
            tpl_var('changelog_actions', db_fetch_all($getActions));
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

        echo tpl_render('manage.changelog.actions_tags');
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

    case 'action':
        if (!perms_check($changelogPerms, MSZ_PERM_CHANGELOG_MANAGE_ACTIONS)) {
            echo render_error(403);
            break;
        }

        $actionId = (int)($_GET['a'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify('changelog_action', $_POST['csrf'] ?? '')) {
            if (!empty($_POST['action']) && is_array($_POST['action'])) {
                if ($actionId > 0) {
                    $updateAction = db_prepare('
                        UPDATE `msz_changelog_actions`
                        SET `action_name` = :name,
                            `action_colour` = :colour,
                            `action_class` = :class
                        WHERE `action_id` = :id
                    ');
                    $updateAction->bindValue('id', $actionId);
                } else {
                    $updateAction = db_prepare('
                        INSERT INTO `msz_changelog_actions`
                            (`action_name`, `action_colour`, `action_class`)
                        VALUES
                            (:name, :colour, :class)
                    ');
                }

                $actionColour = colour_create();

                if (!empty($_POST['action']['colour']['inherit'])) {
                    colour_set_inherit($actionColour);
                } else {
                    colour_set_red($actionColour, $_POST['action']['colour']['red']);
                    colour_set_green($actionColour, $_POST['action']['colour']['green']);
                    colour_set_blue($actionColour, $_POST['action']['colour']['blue']);
                }

                $updateAction->bindValue('name', $_POST['action']['name']);
                $updateAction->bindValue('colour', $actionColour);
                $updateAction->bindValue('class', $_POST['action']['class']);
                $updateAction->execute();

                if ($actionId < 1) {
                    $actionId = db_last_insert_id();
                    audit_log(MSZ_AUDIT_CHANGELOG_ACTION_CREATE, user_session_current('user_id', 0), [$actionId]);
                    header('Location: ?v=action&a=' . $actionId);
                    return;
                } else {
                    audit_log(MSZ_AUDIT_CHANGELOG_ACTION_EDIT, user_session_current('user_id', 0), [$actionId]);
                }
            }
        }

        if ($actionId > 0) {
            $getAction = db_prepare('
                SELECT `action_id`, `action_name`, `action_colour`, `action_class`
                FROM `msz_changelog_actions`
                WHERE `action_id` = :action_id
            ');
            $getAction->bindValue('action_id', $actionId);
            $action = db_fetch($getAction);

            if ($action) {
                tpl_var('edit_action', $action);
            } else {
                header('Location: ?v=actions');
                return;
            }
        }

        echo tpl_render('manage.changelog.action_edit');
        break;
}
