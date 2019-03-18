<?php
use Misuzu\Request\RequestVar;

require_once '../misuzu.php';

$changelogChange = RequestVar::get()->select('c')->int(0);
$changelogDate = RequestVar::get()->select('d')->string('');
$changelogUser = RequestVar::get()->select('u')->int(0);
$changelogTags = RequestVar::get()->select('t')->string('');

tpl_var('comments_perms', $commentPerms = comments_get_perms(user_session_current('user_id', 0)));

if ($changelogChange > 0) {
    $change = changelog_change_get($changelogChange);

    if (!$change) {
        echo render_error(404);
        return;
    }

    echo tpl_render('changelog.change', [
        'change' => $change,
        'tags' => changelog_change_tags_get($change['change_id']),
        'comments_category' => $commentsCategory = comments_category_info(
            "changelog-date-{$change['change_date']}",
            true
        ),
        'comments' => comments_category_get($commentsCategory['category_id'], user_session_current('user_id', 0)),
    ]);
    return;
}

if (!empty($changelogDate)) {
    $dateParts = explode('-', $changelogDate, 3);

    if (count($dateParts) !== 3
        || !array_test($dateParts, 'is_user_int')
        || !checkdate($dateParts[1], $dateParts[2], $dateParts[0])) {
        echo render_error(404);
        return;
    }
}

$changesCount = !empty($changelogDate) ? -1 : changelog_count_changes($changelogDate, $changelogUser);
$changesPagination = pagination_create($changesCount, 30);
$changesOffset = $changesCount === -1 ? 0 : pagination_offset($changesPagination, pagination_param());
$changes = $changesCount === -1 || pagination_is_valid_offset($changesOffset)
    ? changelog_get_changes($changelogDate, $changelogUser, $changesOffset, $changesPagination['range'])
    : [];

if (!$changes) {
    http_response_code(404);
}

if (!empty($changelogDate) && count($changes) > 0) {
    tpl_vars([
        'comments_category' => $commentsCategory = comments_category_info("changelog-date-{$changelogDate}", true),
        'comments' => comments_category_get($commentsCategory['category_id'], user_session_current('user_id', 0)),
    ]);
}

echo tpl_render('changelog.index', [
    'changes' => $changes,
    'changelog_count' => $changesCount,
    'changelog_date' => $changelogDate,
    'changelog_user' => $changelogUser,
    'changelog_pagination' => $changesPagination,
]);
