<?php
namespace Misuzu;

use Misuzu\AuditLog;
use Misuzu\Changelog\ChangelogChange;
use Misuzu\Changelog\ChangelogChangeNotFoundException;
use Misuzu\Changelog\ChangelogTag;
use Misuzu\Changelog\ChangelogTagNotFoundException;
use Misuzu\Users\User;
use Misuzu\Users\UserNotFoundException;

require_once '../../../misuzu.php';

if(!User::hasCurrent() || !perms_check_user(MSZ_PERMS_CHANGELOG, User::getCurrent()->getId(), MSZ_PERM_CHANGELOG_MANAGE_CHANGES)) {
    echo render_error(403);
    return;
}

define('MANAGE_ACTIONS', [
    ['action_id' => ChangelogChange::ACTION_ADD,    'action_name' => 'Added'],
    ['action_id' => ChangelogChange::ACTION_REMOVE, 'action_name' => 'Removed'],
    ['action_id' => ChangelogChange::ACTION_UPDATE, 'action_name' => 'Updated'],
    ['action_id' => ChangelogChange::ACTION_FIX,    'action_name' => 'Fixed'],
    ['action_id' => ChangelogChange::ACTION_IMPORT, 'action_name' => 'Imported'],
    ['action_id' => ChangelogChange::ACTION_REVERT, 'action_name' => 'Reverted'],
]);

$changeId = (int)filter_input(INPUT_GET, 'c', FILTER_SANITIZE_NUMBER_INT);
$tags = ChangelogTag::all();

if($changeId > 0)
    try {
        $change = ChangelogChange::byId($changeId);
    } catch(ChangelogChangeNotFoundException $ex) {
        url_redirect('manage-changelog-changes');
        return;
    }

if($_SERVER['REQUEST_METHOD'] === 'POST' && CSRF::validateRequest()) {
    if(!empty($_POST['change']) && is_array($_POST['change'])) {
        if(!isset($change)) {
            $change = new ChangelogChange;
            $isNew = true;
        }

        $changeUserId = filter_var($_POST['change']['user'], FILTER_SANITIZE_NUMBER_INT);
        if($changeUserId === 0)
            $changeUser = null;
        else
            try {
                $changeUser = User::byId($changeUserId);
            } catch(UserNotFoundException $ex) {
                $changeUser = User::getCurrent();
            }

        $change->setHeader($_POST['change']['log'])
            ->setBody($_POST['change']['text'])
            ->setAction($_POST['change']['action'])
            ->setUser($changeUser)
            ->save();

        AuditLog::create(
            empty($isNew)
                ? AuditLog::CHANGELOG_ENTRY_EDIT
                : AuditLog::CHANGELOG_ENTRY_CREATE,
            [$change->getId()]
        );
    }

    if(isset($change) && !empty($_POST['tags']) && is_array($_POST['tags']) && array_test($_POST['tags'], 'ctype_digit')) {
        $applyTags = [];
        foreach($_POST['tags'] as $tagId)
            try {
                $applyTags[] = ChangelogTag::byId((int)filter_var($tagId, FILTER_SANITIZE_NUMBER_INT));
            } catch(ChangelogTagNotFoundException $ex) {}
        $change->setTags($applyTags);
    }

    if(!empty($isNew)) {
        url_redirect('manage-changelog-change', ['change' => $change->getId()]);
        return;
    }
}

Template::render('manage.changelog.change', [
    'change' => $change ?? null,
    'change_tags' => $tags,
    'change_actions' => MANAGE_ACTIONS,
]);
