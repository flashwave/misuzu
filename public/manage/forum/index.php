<?php
namespace Misuzu;

require_once '../../../misuzu.php';

if(!perms_check_user(MSZ_PERMS_GENERAL, user_session_current('user_id'), MSZ_PERM_FORUM_MANAGE_FORUMS)) {
    echo render_error(403);
    return;
}

$forums = DB::query('SELECT * FROM `msz_forum_categories`')->fetchAll();
$rawPerms = perms_create(MSZ_FORUM_PERM_MODES);
$perms = manage_forum_perms_list($rawPerms);

if(!empty($_POST['perms']) && is_array($_POST['perms'])) {
    $finalPerms = manage_perms_apply($perms, $_POST['perms'], $rawPerms);
    $perms = manage_forum_perms_list($finalPerms);
    tpl_var('calculated_perms', $finalPerms);
}

echo tpl_render('manage.forum.listing', compact('forums', 'perms'));
