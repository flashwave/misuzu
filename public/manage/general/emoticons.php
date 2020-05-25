<?php
namespace Misuzu;

use Misuzu\Users\User;

require_once '../../../misuzu.php';

if(!User::hasCurrent() || !perms_check_user(MSZ_PERMS_GENERAL, User::getCurrent()->getId(), MSZ_PERM_GENERAL_MANAGE_EMOTES)) {
    echo render_error(403);
    return;
}

if(CSRF::validateRequest() && !empty($_GET['emote']) && is_string($_GET['emote'])) {
    $emoteId = (int)$_GET['emote'];
    $emoteInfo = Emoticon::byId($emoteId);

    if(empty($emoteInfo)) {
        echo render_error(404);
        return;
    }

    if(!empty($_GET['order']) && is_string($_GET['order'])) {
        $emoteInfo->changeOrder($_GET['order'] === 'i' ? 1 : -1);
    } elseif(!empty($_GET['alias']) && is_string($_GET['alias']) && ctype_alnum($_GET['alias'])) {
        $emoteInfo->addString(mb_strtolower($_GET['alias']));
        return;
    } elseif(!empty($_GET['delete'])) {
        $emoteInfo->delete();
    }

    url_redirect('manage-general-emoticons');
    return;
}

Template::render('manage.general.emoticons', [
    'emotes' => Emoticon::all(PHP_INT_MAX),
]);
