<?php
namespace Misuzu;

use Misuzu\Changelog\ChangelogChange;
use Misuzu\Users\User;

require_once '../../../misuzu.php';

if(!User::hasCurrent() || !perms_check_user(MSZ_PERMS_CHANGELOG, User::getCurrent()->getId(), MSZ_PERM_CHANGELOG_MANAGE_CHANGES)) {
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
