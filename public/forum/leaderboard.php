<?php
require_once '../../misuzu.php';

if (!perms_check_user(MSZ_PERMS_FORUM, user_session_current('user_id'), MSZ_PERM_FORUM_VIEW_LEADERBOARD)) {
    echo render_error(403);
    return;
}

$leaderboardMode = !empty($_GET['mode']) && is_string($_GET['mode']) && ctype_lower($_GET['mode']) ? $_GET['mode'] : '';
$leaderboardId = !empty($_GET['id']) && is_string($_GET['id'])
    && ctype_digit($_GET['id'])
    ? $_GET['id']
    : MSZ_FORUM_LEADERBOARD_CATEGORY_ALL;
$leaderboardIdLength = strlen($leaderboardId);

$leaderboardYear = $leaderboardIdLength === 4 || $leaderboardIdLength === 6 ? substr($leaderboardId, 0, 4) : null;
$leaderboardMonth = $leaderboardIdLength === 6 ? substr($leaderboardId, 4, 2) : null;

$leaderboards = forum_leaderboard_categories();
$leaderboard = forum_leaderboard_listing($leaderboardYear, $leaderboardMonth);

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

    tpl_var('leaderboard_markdown', $markdown);
}

echo tpl_render('forum.leaderboard', [
    'leaderboard_id' => $leaderboardId,
    'leaderboard_name' => $leaderboardName,
    'leaderboard_categories' => $leaderboards,
    'leaderboard_data' => $leaderboard,
    'leaderboard_mode' => $leaderboardMode,
]);
