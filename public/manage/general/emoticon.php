<?php
namespace Misuzu;

require_once '../../../misuzu.php';

if(!perms_check_user(MSZ_PERMS_GENERAL, user_session_current('user_id'), General::PERM_MANAGE_EMOTES)) {
    echo render_error(403);
    return;
}

$emoteId = !empty($_GET['e']) && is_string($_GET['e']) ? (int)$_GET['e'] : 0;
$isNew = $emoteId <= 0;
$emoteInfo = !$isNew ? Emoticon::byId($emoteId) : new Emoticon;

if(csrf_verify_request() && isset($_POST['emote_order']) && isset($_POST['emote_hierarchy']) && !empty($_POST['emote_url']) && !empty($_POST['emote_strings'])) {
    $emoteInfo->setUrl($_POST['emote_url'])
        ->setHierarchy($_POST['emote_hierarchy'])
        ->setOrder($_POST['emote_order'])
        ->save();

    if($isNew && !$emoteInfo->hasId())
        throw new \Exception("SOMETHING HAPPENED");

    $setStrings = array_column($emoteInfo->getStrings(), 'emote_string');
    $applyStrings = explode(' ', mb_strtolower($_POST['emote_strings']));
    $removeStrings = [];

    foreach($setStrings as $string) {
        if(!in_array($string, $applyStrings)) {
            $removeStrings[] = $string;
        }
    }

    $setStrings = array_diff($setStrings, $removeStrings);

    foreach($applyStrings as $string) {
        if(!in_array($string, $setStrings)) {
            $setStrings[] = $string;
        }
    }

    foreach($removeStrings as $string)
        $emoteInfo->removeString($string);
    foreach($setStrings as $string)
        $emoteInfo->addString($string);
}

Template::render('manage.general.emoticon', [
    'emote_info' => $emoteInfo,
]);
