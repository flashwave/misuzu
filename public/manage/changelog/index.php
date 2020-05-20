<?php
namespace Misuzu;

use Misuzu\Changelog\ChangelogChange;

require_once '../../../misuzu.php';

if(!perms_check_user(MSZ_PERMS_CHANGELOG, user_session_current('user_id'), MSZ_PERM_CHANGELOG_MANAGE_CHANGES)) {
    echo render_error(403);
    return;
}

$changelogPagination = new Pagination(ChangelogChange::countAll(), 30);

if(!$changelogPagination->hasValidOffset()) {
    echo render_error(404);
    return;
}

$changes = ChangelogChange::all($changelogPagination);

Template::render('manage.changelog.changes', [
    'changelog_changes' => $changes,
    'changelog_pagination' => $changelogPagination,
]);
