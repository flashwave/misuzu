<?php
define('MSZ_WARN_SILENCE', 2);
define('MSZ_WARN_BAN', 3);

define('MSZ_WARN_TYPES_HAS_DURATION', [MSZ_WARN_SILENCE, MSZ_WARN_BAN]);

function user_warning_check_expiration(int $userId, int $type): int {
    if($userId < 1 || !in_array($type, MSZ_WARN_TYPES_HAS_DURATION, true))
        return 0;

    static $memo = [];
    $memoId = "{$userId}-{$type}";

    if(array_key_exists($memoId, $memo))
        return $memo[$memoId];

    $getExpiration = \Misuzu\DB::prepare('
        SELECT `warning_duration`
        FROM `msz_user_warnings`
        WHERE `warning_type` = :type
        AND `user_id` = :user
        AND `warning_duration` IS NOT NULL
        AND `warning_duration` >= NOW()
        ORDER BY `warning_duration` DESC
        LIMIT 1
    ');
    $getExpiration->bind('type', $type);
    $getExpiration->bind('user', $userId);
    $expiration = $getExpiration->fetchColumn(0, '');

    return $memo[$memoId] = (empty($expiration) ? 0 : strtotime($expiration));
}
