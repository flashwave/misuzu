<?php
require_once '../../../misuzu.php';

if(!perms_check_user(MSZ_PERMS_CHANGELOG, user_session_current('user_id'), MSZ_PERM_CHANGELOG_MANAGE_TAGS)) {
    echo render_error(403);
    return;
}

$tagId = (int)($_GET['t'] ?? 0);

if(!empty($_POST['tag']) && is_array($_POST['tag']) && csrf_verify('changelog_tag', $_POST['csrf'] ?? '')) {
    if ($tagId > 0) {
        $updateTag = db_prepare('
            UPDATE `msz_changelog_tags`
            SET `tag_name` = :name,
                `tag_description` = :description,
                `tag_archived` = :archived
            WHERE `tag_id` = :id
        ');
        $updateTag->bindValue('id', $tagId);
    } else {
        $updateTag = db_prepare('
            INSERT INTO `msz_changelog_tags`
                (`tag_name`, `tag_description`, `tag_archived`)
            VALUES
                (:name, :description, :archived)
        ');
    }

    $updateTag->bindValue('name', $_POST['tag']['name']);
    $updateTag->bindValue('description', $_POST['tag']['description']);
    $updateTag->bindValue('archived', empty($_POST['tag']['archived']) ? null : date('Y-m-d H:i:s'));
    $updateTag->execute();

    if ($tagId < 1) {
        $tagId = db_last_insert_id();
        audit_log(MSZ_AUDIT_CHANGELOG_TAG_EDIT, user_session_current('user_id', 0), [$tagId]);
        url_redirect('manage-changelog-tag', ['tag' => $tagId]);
        return;
    } else {
        audit_log(MSZ_AUDIT_CHANGELOG_TAG_CREATE, user_session_current('user_id', 0), [$tagId]);
    }
}

if ($tagId > 0) {
    $getTag = db_prepare('
        SELECT `tag_id`, `tag_name`, `tag_description`, `tag_archived`, `tag_created`
        FROM `msz_changelog_tags`
        WHERE `tag_id` = :tag_id
    ');
    $getTag->bindValue('tag_id', $tagId);
    $tag = db_fetch($getTag);

    if ($tag) {
        tpl_var('edit_tag', $tag);
    } else {
        url_redirect('manage-changelog-tags');
        return;
    }
}

echo tpl_render('manage.changelog.tag');
