<?php
namespace Misuzu;

use Misuzu\AuditLog;
use Misuzu\Changelog\ChangelogTag;
use Misuzu\Changelog\ChangelogTagNotFoundException;
use Misuzu\Users\User;

require_once '../../../misuzu.php';

if(!perms_check_user(MSZ_PERMS_CHANGELOG, user_session_current('user_id'), MSZ_PERM_CHANGELOG_MANAGE_TAGS)) {
    echo render_error(403);
    return;
}

$tagId = (int)filter_input(INPUT_GET, 't', FILTER_SANITIZE_NUMBER_INT);

if($tagId > 0)
    try {
        $tagInfo = ChangelogTag::byId($tagId);
    } catch(ChangelogTagNotFoundException $ex) {
        url_redirect('manage-changelog-tags');
        return;
    }

if(!empty($_POST['tag']) && is_array($_POST['tag']) && CSRF::validateRequest()) {
    if(!isset($tagInfo)) {
        $tagInfo = new ChangelogTag;
        $isNew = true;
    }

    $tagInfo->setName($_POST['tag']['name'])
        ->setDescription($_POST['tag']['description'])
        ->setArchived(!empty($_POST['tag']['archived']))
        ->save();

    AuditLog::create(
        empty($isNew)
            ? AuditLog::CHANGELOG_TAG_EDIT
            : AuditLog::CHANGELOG_TAG_CREATE,
        [$tagInfo->getId()]
    );

    if(!empty($isNew)) {
        url_redirect('manage-changelog-tag', ['tag' => $tagInfo->getId()]);
        return;
    }
}

Template::render('manage.changelog.tag', [
    'edit_tag' => $tagInfo ?? null,
]);
