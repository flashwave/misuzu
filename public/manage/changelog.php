<?php
use Misuzu\Database;

require_once __DIR__ . '/../../misuzu.php';

$db = Database::connection();
$tpl = $app->getTemplating();

$changelogPerms = perms_get_user(MSZ_PERMS_CHANGELOG, $app->getUserId());

$queryOffset = (int)($_GET['o'] ?? 0);

switch ($_GET['v'] ?? null) {
    case 'changes':
        if (!perms_check($changelogPerms, MSZ_CHANGELOG_MANAGE_CHANGES)) {
            echo render_error(403);
            break;
        }

        $changesTake = 20;
        $changesCount = (int)$db->query('
            SELECT COUNT(`change_id`)
            FROM `msz_changelog_changes`
        ')->fetchColumn();

        $getChanges = $db->prepare('
            SELECT
                c.`change_id`, c.`change_log`, c.`change_created`,
                a.`action_name`, a.`action_colour`, a.`action_class`,
                u.`user_id`, u.`username`,
                COALESCE(r.`role_colour`, CAST(0x40000000 AS UNSIGNED)) as `user_colour`
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
        $getChanges->bindValue('take', $changesTake);
        $getChanges->bindValue('offset', $queryOffset);
        $changes = $getChanges->execute() ? $getChanges->fetchAll(PDO::FETCH_ASSOC) : [];

        $getTags = $db->prepare('
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
            $changes[$i]['tags'] = $getTags->execute() ? $getTags->fetchAll(PDO::FETCH_ASSOC) : [];
        }

        echo $tpl->render('@manage.changelog.changes', [
            'changelog_changes' => $changes,
            'changelog_changes_count' => $changesCount,
            'changelog_offset' => $queryOffset,
            'changelog_take' => $changesTake,
        ]);
        break;

    case 'change':
        if (!perms_check($changelogPerms, MSZ_CHANGELOG_MANAGE_CHANGES)) {
            echo render_error(403);
            break;
        }

        $changeId = (int)($_GET['c'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && tmp_csrf_verify($_POST['csrf'] ?? '')) {
            if (!empty($_POST['change']) && is_array($_POST['change'])) {
                if ($changeId > 0) {
                    $postChange = $db->prepare('
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
                    $postChange = $db->prepare('
                        INSERT INTO `msz_changelog_changes`
                            (`change_log`, `change_text`, `action_id`, `user_id`, `change_created`)
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
                    header('Location: ?v=change&c=' . $db->lastInsertId());
                    return;
                }
            }

            if (!empty($_POST['add_tag']) && is_numeric($_POST['add_tag'])) {
                $addTag = $db->prepare('REPLACE INTO `msz_changelog_change_tags` VALUES (:change_id, :tag_id)');
                $addTag->bindValue('change_id', $changeId);
                $addTag->bindValue('tag_id', $_POST['add_tag']);
                $addTag->execute();
            }

            if (!empty($_POST['remove_tag']) && is_numeric($_POST['remove_tag'])) {
                $removeTag = $db->prepare('
                    DELETE FROM `msz_changelog_change_tags`
                    WHERE `change_id` = :change_id
                    AND `tag_id` = :tag_id
                ');
                $removeTag->bindValue('change_id', $changeId);
                $removeTag->bindValue('tag_id', $_POST['remove_tag']);
                $removeTag->execute();
            }
        }

        $actions = $db->query('
            SELECT `action_id`, `action_name`
            FROM `msz_changelog_actions`
        ')->fetchAll(PDO::FETCH_ASSOC);
        $tpl->var('changelog_actions', $actions);

        if ($changeId > 0) {
            $getChange = $db->prepare('
                SELECT `change_id`, `change_log`, `change_text`, `user_id`, `action_id`, `change_created`
                FROM `msz_changelog_changes`
                WHERE `change_id` = :change_id
            ');
            $getChange->bindValue('change_id', $changeId);
            $change = $getChange->execute() ? $getChange->fetch(PDO::FETCH_ASSOC) : [];

            if ($change) {
                $tpl->var('edit_change', $change);

                $assignedTags = $db->prepare('
                    SELECT `tag_id`, `tag_name`
                    FROM `msz_changelog_tags`
                    WHERE `tag_id` IN (
                        SELECT `tag_id`
                        FROM `msz_changelog_change_tags`
                        WHERE `change_id` = :change_id
                    )
                ');
                $assignedTags->bindValue('change_id', $change['change_id']);
                $assignedTags = $assignedTags->execute() ? $assignedTags->fetchAll(PDO::FETCH_ASSOC) : [];

                $availableTags = $db->prepare('
                    SELECT `tag_id`, `tag_name`
                    FROM `msz_changelog_tags`
                    WHERE `tag_archived` IS NULL
                    AND `tag_id` NOT IN (
                        SELECT `tag_id`
                        FROM `msz_changelog_change_tags`
                        WHERE `change_id` = :change_id
                    )
                ');
                $availableTags->bindValue('change_id', $change['change_id']);
                $availableTags = $availableTags->execute() ? $availableTags->fetchAll(PDO::FETCH_ASSOC) : [];

                $tpl->vars([
                    'edit_change_assigned_tags' => $assignedTags,
                    'edit_change_available_tags' => $availableTags,
                ]);
            } else {
                header('Location: ?v=changes');
                return;
            }
        }

        echo $tpl->render('@manage.changelog.change_edit');
        break;

    case 'tags':
        if (!perms_check($changelogPerms, MSZ_CHANGELOG_MANAGE_TAGS)) {
            echo render_error(403);
            break;
        }

        $tagsTake = 32;

        $tagsCount = (int)$db->query('
            SELECT COUNT(`tag_id`)
            FROM `msz_changelog_tags`
        ')->fetchColumn();

        $getTags = $db->prepare('
            SELECT
                t.`tag_id`, t.`tag_name`, t.`tag_description`, t.`tag_created`,
                (
                    SELECT COUNT(ct.`change_id`)
                    FROM `msz_changelog_change_tags` as ct
                    WHERE ct.`tag_id` = t.`tag_id`
                ) as `tag_count`
            FROM `msz_changelog_tags` as t
            ORDER BY t.`tag_id` ASC
            LIMIT :offset, :take
        ');
        $getTags->bindValue('take', $tagsTake);
        $getTags->bindValue('offset', $queryOffset);
        $tags = $getTags->execute() ? $getTags->fetchAll(PDO::FETCH_ASSOC) : [];

        echo $tpl->render('@manage.changelog.tags', [
            'changelog_tags' => $tags,
            'changelog_tags_count' => $tagsCount,
            'changelog_take' => $tagsTake,
            'changelog_offset' => $queryOffset,
        ]);
        break;

    case 'tag':
        if (!perms_check($changelogPerms, MSZ_CHANGELOG_MANAGE_TAGS)) {
            echo render_error(403);
            break;
        }

        $tagId = (int)($_GET['t'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && tmp_csrf_verify($_POST['csrf'] ?? '')) {
            if (!empty($_POST['tag']) && is_array($_POST['tag'])) {
                if ($tagId > 0) {
                    $updateTag = $db->prepare('
                        UPDATE `msz_changelog_tags`
                        SET `tag_name` = :name,
                            `tag_description` = :description,
                            `tag_archived` = :archived
                        WHERE `tag_id` = :id
                    ');
                    $updateTag->bindValue('id', $tagId);
                } else {
                    $updateTag = $db->prepare('
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
                    header('Location: ?v=tag&t=' . $db->lastInsertId());
                    return;
                }
            }
        }

        if ($tagId > 0) {
            $getTag = $db->prepare('
                SELECT `tag_id`, `tag_name`, `tag_description`, `tag_archived`, `tag_created`
                FROM `msz_changelog_tags`
                WHERE `tag_id` = :tag_id
            ');
            $getTag->bindValue('tag_id', $tagId);
            $tag = $getTag->execute() ? $getTag->fetch(PDO::FETCH_ASSOC) : [];

            if ($tag) {
                $tpl->var('edit_tag', $tag);
            } else {
                header('Location: ?v=tags');
                return;
            }
        }

        echo $tpl->render('@manage.changelog.tag_edit');
        break;

    case 'actions':
        if (!perms_check($changelogPerms, MSZ_CHANGELOG_MANAGE_ACTIONS)) {
            echo render_error(403);
            break;
        }

        $actionTake = 32;

        $actionCount = (int)$db->query('
            SELECT COUNT(`action_id`)
            FROM `msz_changelog_actions`
        ')->fetchColumn();

        $getActions = $db->prepare('
            SELECT
                a.`action_id`, a.`action_name`, a.`action_colour`,
                (
                    SELECT COUNT(c.`action_id`)
                    FROM `msz_changelog_changes` as c
                    WHERE c.`action_id` = a.`action_id`
                ) as `action_count`
            FROM `msz_changelog_actions` as a
            ORDER BY a.`action_id` ASC
            LIMIT :offset, :take
        ');
        $getActions->bindValue('take', $actionTake);
        $getActions->bindValue('offset', $queryOffset);
        $actions = $getActions->execute() ? $getActions->fetchAll(PDO::FETCH_ASSOC) : [];

        echo $tpl->render('@manage.changelog.actions', [
            'changelog_actions' => $actions,
            'changelog_actions_count' => $actionTake,
            'changelog_take' => $actionTake,
            'changelog_offset' => $queryOffset,
        ]);
        break;

    case 'action':
        if (!perms_check($changelogPerms, MSZ_CHANGELOG_MANAGE_ACTIONS)) {
            echo render_error(403);
            break;
        }

        $actionId = (int)($_GET['a'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && tmp_csrf_verify($_POST['csrf'] ?? '')) {
            if (!empty($_POST['action']) && is_array($_POST['action'])) {
                if ($actionId > 0) {
                    $updateAction = $db->prepare('
                        UPDATE `msz_changelog_actions`
                        SET `action_name` = :name,
                            `action_colour` = :colour,
                            `action_class` = :class
                        WHERE `action_id` = :id
                    ');
                    $updateAction->bindValue('id', $actionId);
                } else {
                    $updateAction = $db->prepare('
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
                    header('Location: ?v=action&a=' . $db->lastInsertId());
                    return;
                }
            }
        }

        if ($actionId > 0) {
            $getAction = $db->prepare('
                SELECT `action_id`, `action_name`, `action_colour`, `action_class`
                FROM `msz_changelog_actions`
                WHERE `action_id` = :action_id
            ');
            $getAction->bindValue('action_id', $actionId);
            $action = $getAction->execute() ? $getAction->fetch(PDO::FETCH_ASSOC) : [];

            if ($action) {
                $tpl->var('edit_action', $action);
            } else {
                header('Location: ?v=actions');
                return;
            }
        }

        echo $tpl->render('@manage.changelog.action_edit');
        break;
}
