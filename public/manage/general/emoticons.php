<?php
namespace Misuzu;

require_once '../../../misuzu.php';

if(!perms_check_user(MSZ_PERMS_GENERAL, user_session_current('user_id'), MSZ_PERM_GENERAL_MANAGE_EMOTICONS)) {
    echo render_error(403);
    return;
}

if(csrf_verify_request() && !empty($_GET['emote']) && is_string($_GET['emote'])) {
    $emoteId = (int)$_GET['emote'];

    if(!empty($_GET['order']) && is_string($_GET['order'])) {
        emotes_order_change($emoteId, $_GET['order'] === 'i');
    } elseif(!empty($_GET['alias']) && is_string($_GET['alias'])) {
        emotes_add_alias($emoteId, $_GET['alias']);
        return;
    } elseif(!empty($_GET['delete'])) {
        emotes_remove_id($emoteId);
    }

    url_redirect('manage-general-emoticons');
    return;
}

Template::render('manage.general.emoticons', [
    'emotes' => emotes_list(PHP_INT_MAX),
]);
