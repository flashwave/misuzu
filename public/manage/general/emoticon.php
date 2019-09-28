<?php
namespace Misuzu;

require_once '../../../misuzu.php';

if(!perms_check_user(MSZ_PERMS_GENERAL, user_session_current('user_id'), MSZ_PERM_GENERAL_MANAGE_EMOTICONS)) {
    echo render_error(403);
    return;
}

$emoteId = !empty($_GET['e']) && is_string($_GET['e']) ? (int)$_GET['e'] : 0;

if($emoteId > 0) {
    $emoteInfo = emotes_get_by_id($emoteId);
}

if(csrf_verify_request()
    && isset($_POST['emote_order']) && isset($_POST['emote_hierarchy'])
    && !empty($_POST['emote_string']) && !empty($_POST['emote_url'])) {
    if(empty($emoteInfo)) {
        $emoteId = emotes_add($_POST['emote_string'], $_POST['emote_url'], $_POST['emote_hierarchy'], $_POST['emote_order']);

        if($emoteId > 0) {
            // seems like an odd decision to redirect back to the emoticons index, but it'll probably be nicer in the long run
            url_redirect('manage-general-emoticons');
            return;
        } else {
            echo "SOMETHING HAPPENED {$emoteId}";
        }
    } else {
        emotes_update_url($emoteInfo['emote_url'], $_POST['emote_url'], $_POST['emote_hierarchy'], $_POST['emote_order']);
        emotes_update_string($emoteId, $_POST['emote_string']);
        $emoteInfo = emotes_get_by_id($emoteId);
    }
}

echo tpl_render('manage.general.emoticon', [
    'emote_info' => $emoteInfo ?? null,
]);
