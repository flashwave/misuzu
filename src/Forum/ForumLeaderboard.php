<?php
namespace Misuzu\Forum;

use Misuzu\DB;

final class ForumLeaderboard {
    public const START_YEAR = 2018;
    public const START_MONTH = 12;
    public const CATEGORY_ALL = 0;

    public static function isValidYear(?int $year): bool {
        return !is_null($year) && $year >= self::START_YEAR && $year <= date('Y');
    }

    public static function isValidMonth(?int $year, ?int $month): bool {
        if(is_null($month) || !self::isValidYear($year) || $month < 1 || $month > 12)
            return false;

        $combo = sprintf('%04d%02d', $year, $month);
        $start = sprintf('%04d%02d', self::START_YEAR, self::START_MONTH);
        $current = date('Ym');

        return $combo >= $start && $combo <= $current;
    }

    public static function categories(): array {
        $categories = [
            self::CATEGORY_ALL => 'All Time',
        ];

        $currentYear = date('Y');
        $currentMonth = date('m');

        for($i = $currentYear; $i >= self::START_YEAR; $i--) {
            $categories[$i] = sprintf('Leaderboard %d', $i);
        }

        for($i = $currentYear, $j = $currentMonth;;) {
            $categories[sprintf('%d%02d', $i, $j)] = sprintf('Leaderboard %d-%02d', $i, $j);

            if($j <= 1) {
                $i--; $j = 12;
            } else $j--;

            if($i <= self::START_YEAR && $j < self::START_MONTH)
                break;
        }

        return $categories;
    }

    public static function listing(
        ?int $year = null,
        ?int $month = null,
        array $unrankedForums = [],
        array $unrankedTopics = []
    ): array {
        $hasYear = self::isValidYear($year);
        $hasMonth = $hasYear && self::isValidMonth($year, $month);
        $unrankedForums = implode(',', $unrankedForums);
        $unrankedTopics = implode(',', $unrankedTopics);

        $rawLeaderboard = DB::query(sprintf(
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
        ))->fetchAll();

        $leaderboard = [];
        $ranking = 0;
        $lastPosts = null;

        foreach($rawLeaderboard as $entry) {
            if(is_null($lastPosts) || $lastPosts > $entry['posts']) {
                $ranking++;
                $lastPosts = $entry['posts'];
            }

            $entry['rank'] = $ranking;
            $leaderboard[] = $entry;
        }

        return $leaderboard;
    }
}
