<?php
namespace Misuzu;

use Misuzu\Changelog\ChangelogTag;
use Misuzu\Users\User;

require_once '../../../misuzu.php';

if(!User::hasCurrent() || !perms_check_user(MSZ_PERMS_CHANGELOG, User::getCurrent()->getId(), MSZ_PERM_CHANGELOG_MANAGE_TAGS)) {
    echo render_error(403);
    return;
}

Template::render('manage.changelog.tags', [
    'changelog_tags' => ChangelogTag::all(),
]);
