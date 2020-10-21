<?php
namespace Misuzu;

use Misuzu\Forum\ForumLeaderboard;
use Misuzu\Users\User;

require_once '../../misuzu.php';

if(!User::hasCurrent() || !perms_check_user(MSZ_PERMS_FORUM, User::getCurrent()->getId(), MSZ_PERM_FORUM_VIEW_LEADERBOARD)) {
    echo render_error(403);
    return;
}

$leaderboardMode = !empty($_GET['mode']) && is_string($_GET['mode']) && ctype_lower($_GET['mode']) ? $_GET['mode'] : '';
$leaderboardId = !empty($_GET['id']) && is_string($_GET['id'])
    && ctype_digit($_GET['id'])
    ? $_GET['id']
    : ForumLeaderboard::CATEGORY_ALL;
$leaderboardIdLength = strlen($leaderboardId);

$leaderboardYear  = $leaderboardIdLength === 4 || $leaderboardIdLength === 6 ? substr($leaderboardId, 0, 4) : null;
$leaderboardMonth = $leaderboardIdLength === 6 ? substr($leaderboardId, 4, 2) : null;

$unrankedForums = !empty($_GET['allow_unranked']) ? [] : Config::get('forum_leader.unranked.forum', Config::TYPE_ARR);
$unrankedTopics = !empty($_GET['allow_unranked']) ? [] : Config::get('forum_leader.unranked.topic', Config::TYPE_ARR);
$leaderboards = ForumLeaderboard::categories();
$leaderboard = ForumLeaderboard::listing($leaderboardYear, $leaderboardMonth, $unrankedForums, $unrankedTopics);

$leaderboardName = 'All Time';

if($leaderboardYear) {
    $leaderboardName = "Leaderboard {$leaderboardYear}";

    if($leaderboardMonth) {
        $leaderboardName .= "-{$leaderboardMonth}";
    }
}

if($leaderboardMode === 'markdown') {
    $markdown = <<<MD
# {$leaderboardName}

| Rank | Usename | Post count |
| ----:|:------- | ----------:|

MD;

    foreach($leaderboard as $user) {
        $markdown .= sprintf("| %s | [%s](%s%s) | %s |\r\n", $user['rank'], $user['username'], url_prefix(false), url('user-profile', ['user' => $user['user_id']]), $user['posts']);
    }

    Template::set('leaderboard_markdown', $markdown);
}

Template::render('forum.leaderboard', [
    'leaderboard_id' => $leaderboardId,
    'leaderboard_name' => $leaderboardName,
    'leaderboard_categories' => $leaderboards,
    'leaderboard_data' => $leaderboard,
    'leaderboard_mode' => $leaderboardMode,
]);
