<?php
use Misuzu\Database;

require_once __DIR__ . '/../misuzu.php';

$db = Database::connection();
$tpl = $app->getTemplating();

$changelogOffset = max((int)($_GET['o'] ?? 0), 0);
$changelogRange = 30;

$changelogChange = (int)($_GET['c'] ?? 0);
$changelogDate = $_GET['d'] ?? '';
$changelogUser = (int)($_GET['u'] ?? 0);

$tpl->vars([
    'changelog_offset' => $changelogOffset,
    'changelog_take' => $changelogRange,
]);

if ($changelogChange > 0) {
    $getChange = $db->prepare('
        SELECT
            c.`change_id`, c.`change_created`, c.`change_log`, c.`change_text`,
            a.`action_name`, a.`action_colour`, a.`action_class`,
            u.`user_id`, u.`username`,
            DATE(`change_created`) as `change_date`,
            COALESCE(u.`user_title`, r.`role_title`) as `user_title`,
            COALESCE(r.`role_colour`, CAST(0x40000000 AS UNSIGNED)) as `user_colour`
        FROM `msz_changelog_changes` as c
        LEFT JOIN `msz_users` as u
        ON u.`user_id` = c.`user_id`
        LEFT JOIN `msz_roles` as r
        ON r.`role_id` = u.`display_role`
        LEFT JOIN `msz_changelog_actions` as a
        ON a.`action_id` = c.`action_id`
        WHERE `change_id` = :change_id
    ');
    $getChange->bindValue('change_id', $changelogChange);
    $change = $getChange->execute() ? $getChange->fetch(PDO::FETCH_ASSOC) : [];

    if (!$change) {
        http_response_code(404);
    } else {
        $getTags = $db->prepare('
            SELECT
                t.`tag_id`, t.`tag_name`, t.`tag_description`
            FROM `msz_changelog_tags` as t
            LEFT JOIN `msz_changelog_change_tags` as ct
            ON ct.`tag_id` = t.`tag_id`
            WHERE ct.`change_id` = :change_id
        ');
        $getTags->bindValue('change_id', $change['change_id']);
        $tags = $getTags->execute() ? $getTags->fetchAll(PDO::FETCH_ASSOC) : [];
        $tpl->var('tags', $tags);
    }

    echo $tpl->render('changelog.change', compact('change'));
    return;
}

if (!empty($changelogDate)) {
    $dateParts = explode('-', $changelogDate, 3);

    if (count($dateParts) !== 3 || !checkdate($dateParts[1], $dateParts[2], $dateParts[0])) {
        echo render_error(404);
        return;
    }

    $getChanges = $db->prepare('
        SELECT
            c.`change_id`, c.`change_log`,
            a.`action_name`, a.`action_colour`, a.`action_class`,
            u.`user_id`, u.`username`,
            DATE(`change_created`) as `change_date`,
            !ISNULL(c.`change_text`) as `change_has_text`,
            COALESCE(r.`role_colour`, CAST(0x40000000 AS UNSIGNED)) as `user_colour`
        FROM `msz_changelog_changes` as c
        LEFT JOIN `msz_users` as u
        ON u.`user_id` = c.`user_id`
        LEFT JOIN `msz_roles` as r
        ON r.`role_id` = u.`display_role`
        LEFT JOIN `msz_changelog_actions` as a
        ON a.`action_id` = c.`action_id`
        WHERE DATE(c.`change_created`) = :date
        GROUP BY `change_created`, `change_id`
        ORDER BY `change_created` DESC, `change_id` DESC
    ');
    $getChanges->bindValue('date', $changelogDate);
    $changes = $getChanges->execute() ? $getChanges->fetchAll() : [];

    // settings the response code to 404, but rendering our own page anyway.
    if (count($changes) < 1) {
        http_response_code(404);
    }

    echo $tpl->render('changelog.date', [
        'changes' => $changes,
    ]);
    return;
}

$changesCount = (int)$db->query('
    SELECT COUNT(`change_id`)
    FROM `msz_changelog_changes`
')->fetchColumn();

$getChanges = $db->prepare('
    SELECT
        c.`change_id`, c.`change_log`,
        a.`action_name`, a.`action_colour`, a.`action_class`,
        u.`user_id`, u.`username`,
        DATE(`change_created`) as `change_date`,
        !ISNULL(c.`change_text`) as `change_has_text`,
        COALESCE(r.`role_colour`, CAST(0x40000000 AS UNSIGNED)) as `user_colour`
    FROM `msz_changelog_changes` as c
    LEFT JOIN `msz_users` as u
    ON u.`user_id` = c.`user_id`
    LEFT JOIN `msz_roles` as r
    ON r.`role_id` = u.`display_role`
    LEFT JOIN `msz_changelog_actions` as a
    ON a.`action_id` = c.`action_id`
    GROUP BY `change_created`, `change_id`
    ORDER BY `change_created` DESC, `change_id` DESC
    LIMIT :offset, :take
');
$getChanges->bindValue('offset', $changelogOffset);
$getChanges->bindValue('take', $changelogRange);
$changes = $getChanges->execute() ? $getChanges->fetchAll() : [];

echo $tpl->render('changelog.index', [
    'changes' => $changes,
    'changelog_count' => $changesCount,
]);
