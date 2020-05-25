<?php
namespace Misuzu;

use Misuzu\Users\User;

require_once '../../misuzu.php';

$indexMode = !empty($_GET['m']) && is_string($_GET['m']) ? (string)$_GET['m'] : '';
$forumId = !empty($_GET['f']) && is_string($_GET['f']) ? (int)$_GET['f'] : 0;

$currentUser = User::getCurrent();
$currentUserId = $currentUser === null ? 0 : $currentUser->getId();

switch($indexMode) {
    case 'mark':
        url_redirect($forumId < 1 ? 'forum-mark-global' : 'forum-mark-single', ['forum' => $forumId]);
        break;

    default:
        $categories = forum_get_root_categories($currentUserId);
        $blankForum = count($categories) < 1;

        foreach($categories as $key => $category) {
            $categories[$key]['forum_subforums'] = forum_get_children($category['forum_id'], $currentUserId);

            foreach($categories[$key]['forum_subforums'] as $skey => $sub) {
                if(!forum_may_have_children($sub['forum_type'])) {
                    continue;
                }

                $categories[$key]['forum_subforums'][$skey]['forum_subforums']
                    = forum_get_children($sub['forum_id'], $currentUserId);
            }
        }

        Template::render('forum.index', [
            'forum_categories' => $categories,
            'forum_empty' => $blankForum,
        ]);
        break;
}
