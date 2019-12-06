<?php
namespace Misuzu;

require_once '../misuzu.php';

$changelogChange = !empty($_GET['c']) && is_string($_GET['c']) ? (int)$_GET['c'] : 0;
$changelogDate = !empty($_GET['d']) && is_string($_GET['d']) ? (string)$_GET['d'] : '';
$changelogUser = !empty($_GET['u']) && is_string($_GET['u']) ? (int)$_GET['u'] : 0;
$changelogTags = !empty($_GET['t']) && is_string($_GET['t']) ? (string)$_GET['t'] : '';

Template::set('comments_perms', $commentPerms = comments_get_perms(user_session_current('user_id', 0)));

if($changelogChange > 0) {
    $change = changelog_change_get($changelogChange);

    if(!$change) {
        echo render_error(404);
        return;
    }

    Template::render('changelog.change', [
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

if(!empty($changelogDate)) {
    $dateParts = explode('-', $changelogDate, 3);

    if(count($dateParts) !== 3
        || !array_test($dateParts, 'ctype_digit')
        || !checkdate($dateParts[1], $dateParts[2], $dateParts[0])) {
        echo render_error(404);
        return;
    }
}

$changesCount = !empty($changelogDate) ? -1 : changelog_count_changes($changelogDate, $changelogUser);
$changesPagination = new Pagination($changesCount, 30);
$changes = $changesCount === -1 || $changesPagination->hasValidOffset()
    ? changelog_get_changes($changelogDate, $changelogUser, $changesPagination->getOffset(), $changesPagination->getRange())
    : [];

if(!$changes) {
    http_response_code(404);
}

if(!empty($changelogDate) && count($changes) > 0) {
    Template::set([
        'comments_category' => $commentsCategory = comments_category_info("changelog-date-{$changelogDate}", true),
        'comments' => comments_category_get($commentsCategory['category_id'], user_session_current('user_id', 0)),
    ]);
}

Template::render('changelog.index', [
    'changes' => $changes,
    'changelog_count' => $changesCount,
    'changelog_date' => $changelogDate,
    'changelog_user' => $changelogUser,
    'changelog_pagination' => $changesPagination,
]);
