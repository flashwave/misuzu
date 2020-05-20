<?php
namespace Misuzu;

use Misuzu\Changelog\ChangelogTag;

require_once '../../../misuzu.php';

if(!perms_check_user(MSZ_PERMS_CHANGELOG, user_session_current('user_id'), MSZ_PERM_CHANGELOG_MANAGE_TAGS)) {
    echo render_error(403);
    return;
}

Template::render('manage.changelog.tags', [
    'changelog_tags' => ChangelogTag::all(),
]);
