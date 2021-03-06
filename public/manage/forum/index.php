<?php
namespace Misuzu;

use Misuzu\Users\User;

require_once '../../../misuzu.php';

if(!User::hasCurrent() || !perms_check_user(MSZ_PERMS_GENERAL, User::getCurrent()->getId(), MSZ_PERM_FORUM_MANAGE_FORUMS)) {
    echo render_error(403);
    return;
}

$forums = DB::query('SELECT * FROM `msz_forum_categories`')->fetchAll();
$rawPerms = perms_create(MSZ_FORUM_PERM_MODES);
$perms = manage_forum_perms_list($rawPerms);

if(!empty($_POST['perms']) && is_array($_POST['perms'])) {
    $finalPerms = manage_perms_apply($perms, $_POST['perms'], $rawPerms);
    $perms = manage_forum_perms_list($finalPerms);
    Template::set('calculated_perms', $finalPerms);
}

Template::render('manage.forum.listing', compact('forums', 'perms'));
