<?php
namespace Misuzu;

require_once '../../../misuzu.php';

if(!perms_check_user(MSZ_PERMS_CHANGELOG, user_session_current('user_id'), MSZ_PERM_CHANGELOG_MANAGE_TAGS)) {
    echo render_error(403);
    return;
}

$tagId = (int)($_GET['t'] ?? 0);

if(!empty($_POST['tag']) && is_array($_POST['tag']) && csrf_verify_request()) {
    if($tagId > 0) {
        $updateTag = DB::prepare('
            UPDATE `msz_changelog_tags`
            SET `tag_name` = :name,
                `tag_description` = :description,
                `tag_archived` = :archived
            WHERE `tag_id` = :id
        ');
        $updateTag->bind('id', $tagId);
    } else {
        $updateTag = DB::prepare('
            INSERT INTO `msz_changelog_tags`
                (`tag_name`, `tag_description`, `tag_archived`)
            VALUES
                (:name, :description, :archived)
        ');
    }

    $updateTag->bind('name', $_POST['tag']['name']);
    $updateTag->bind('description', $_POST['tag']['description']);
    $updateTag->bind('archived', empty($_POST['tag']['archived']) ? null : date('Y-m-d H:i:s'));
    $updateTag->execute();

    if($tagId < 1) {
        $tagId = DB::lastId();
        audit_log(MSZ_AUDIT_CHANGELOG_TAG_EDIT, user_session_current('user_id', 0), [$tagId]);
        url_redirect('manage-changelog-tag', ['tag' => $tagId]);
        return;
    } else {
        audit_log(MSZ_AUDIT_CHANGELOG_TAG_CREATE, user_session_current('user_id', 0), [$tagId]);
    }
}

if($tagId > 0) {
    $getTag = DB::prepare('
        SELECT `tag_id`, `tag_name`, `tag_description`, `tag_archived`, `tag_created`
        FROM `msz_changelog_tags`
        WHERE `tag_id` = :tag_id
    ');
    $getTag->bind('tag_id', $tagId);
    $tag = $getTag->fetch();

    if($tag) {
        Template::set('edit_tag', $tag);
    } else {
        url_redirect('manage-changelog-tags');
        return;
    }
}

Template::render('manage.changelog.tag');
