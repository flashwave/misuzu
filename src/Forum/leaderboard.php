<?php
define('MSZ_FORUM_LEADERBOARD_START_YEAR', 2018);
define('MSZ_FORUM_LEADERBOARD_START_MONTH', 12);
define('MSZ_FORUM_LEADERBOARD_CATEGORY_ALL', 0);

function forum_leaderboard_year_valid(?int $year): bool
{
    return !is_null($year) && $year >= MSZ_FORUM_LEADERBOARD_START_YEAR && $year <= date('Y');
}

function forum_leaderboard_month_valid(?int $year, ?int $month): bool
{
    if (is_null($month) || !forum_leaderboard_year_valid($year) || $month < 1 || $month > 12) {
        return false;
    }

    $combo = sprintf('%04d%02d', $year, $month);
    $start = sprintf('%04d%02d', MSZ_FORUM_LEADERBOARD_START_YEAR, MSZ_FORUM_LEADERBOARD_START_MONTH);
    $current = date('Ym');

    return $combo >= $start && $combo <= $current;
}

function forum_leaderboard_categories(): array
{
    $categories = [
        MSZ_FORUM_LEADERBOARD_CATEGORY_ALL => 'All Time',
    ];

    $currentYear = date('Y');
    $currentMonth = date('m');

    for ($i = MSZ_FORUM_LEADERBOARD_START_YEAR; $i <= $currentYear; $i++) {
        $categories[$i] = sprintf('Leaderboard %d', $i);
    }

    for ($i = MSZ_FORUM_LEADERBOARD_START_YEAR, $j = MSZ_FORUM_LEADERBOARD_START_MONTH;;) {
        $categories[sprintf('%d%02d', $i, $j)] = sprintf('Leaderboard %d-%02d', $i, $j);

        if ($j >= 12) {
            $i++; $j = 1;
        } else $j++;

        if ($i >= $currentYear && $j > $currentMonth)
            break;
    }

    return $categories;
}

function forum_leaderboard_listing(?int $year = null, ?int $month = null, array $unrankedForums = [], array $unrankedTopics = []): array
{
    $hasYear = forum_leaderboard_year_valid($year);
    $hasMonth = $hasYear && forum_leaderboard_month_valid($year, $month);
    $unrankedForums = implode(',', $unrankedForums);
    $unrankedTopics = implode(',', $unrankedTopics);

    $rawLeaderboard = db_fetch_all(db_query(sprintf(
        '
            SELECT
                u.`user_id`, u.`username`,
                COUNT(fp.`post_id`) as `posts`
            FROM `msz_users` AS u
            INNER JOIN `msz_forum_posts` AS fp
            ON fp.`user_id` = u.`user_id`
            WHERE fp.`post_deleted` IS NULL
            %s %s %s
            GROUP BY u.`user_id`
            HAVING `posts` > 0
            ORDER BY `posts` DESC
        ',
        $unrankedForums ? sprintf('AND fp.`forum_id` NOT IN (%s)', $unrankedForums) : '',
        $unrankedTopics ? sprintf('AND fp.`topic_id` NOT IN (%s)', $unrankedTopics) : '',
        !$hasYear ? '' : sprintf(
            'AND DATE(fp.`post_created`) BETWEEN \'%1$04d-%2$02d-01\' AND \'%1$04d-%3$02d-31\'',
            $year,
            $hasMonth ? $month : 1,
            $hasMonth ? $month : 12
        )
    )));

    $leaderboard = [];
    $ranking = 0;
    $lastPosts = null;

    foreach ($rawLeaderboard as $entry) {
        if (is_null($lastPosts) || $lastPosts > $entry['posts']) {
            $ranking++;
            $lastPosts = $entry['posts'];
        }

        $entry['rank'] = $ranking;
        $leaderboard[] = $entry;
    }

    return $leaderboard;
}
