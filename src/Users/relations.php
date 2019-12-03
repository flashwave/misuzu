<?php
define('MSZ_USER_RELATION_NONE', 0);
define('MSZ_USER_RELATION_FOLLOW', 1);

define('MSZ_USER_RELATION_TYPES', [
    MSZ_USER_RELATION_NONE,
    MSZ_USER_RELATION_FOLLOW,
]);

define('MSZ_USER_RELATION_FOLLOW_PER_PAGE', 15);

function user_relation_is_valid_type(int $type): bool {
    return in_array($type, MSZ_USER_RELATION_TYPES, true);
}

function user_relation_set(int $userId, int $subjectId, int $type = MSZ_USER_RELATION_FOLLOW): bool {
    if($type === MSZ_USER_RELATION_NONE) {
        return user_relation_remove($userId, $subjectId);
    }

    if($userId < 1 || $subjectId < 1 || !user_relation_is_valid_type($type)) {
        return false;
    }

    $addRelation = \Misuzu\DB::prepare('
        REPLACE INTO `msz_user_relations`
            (`user_id`, `subject_id`, `relation_type`)
        VALUES
            (:user_id, :subject_id, :type)
    ');
    $addRelation->bind('user_id', $userId);
    $addRelation->bind('subject_id', $subjectId);
    $addRelation->bind('type', $type);
    $addRelation->execute();

    return $addRelation->execute();
}

function user_relation_remove(int $userId, int $subjectId): bool {
    if($userId < 1 || $subjectId < 1) {
        return false;
    }

    $removeRelation = \Misuzu\DB::prepare('
        DELETE FROM `msz_user_relations`
        WHERE `user_id` = :user_id
        AND `subject_id` = :subject_id
    ');
    $removeRelation->bind('user_id', $userId);
    $removeRelation->bind('subject_id', $subjectId);

    return $removeRelation->execute();
}

function user_relation_info(int $userId, int $subjectId): array {
    $getRelationInfo = \Misuzu\DB::prepare('
        SELECT
            :user_id as `user_id_arg`, :subject_id as `subject_id_arg`,
            (
                SELECT `relation_type`
                FROM `msz_user_relations`
                WHERE `user_id` = `user_id_arg`
                AND `subject_id` = `subject_id_arg`
            ) as `user_relation`,
            (
                SELECT `relation_type`
                FROM `msz_user_relations`
                WHERE `subject_id` = `user_id_arg`
                AND `user_id` = `subject_id_arg`
            ) as `subject_relation`,
            (
                SELECT MAX(`relation_created`)
                FROM `msz_user_relations`
                WHERE (`user_id` = `user_id_arg` AND `subject_id` = `subject_id_arg`)
                OR (`user_id` = `subject_id_arg` AND `subject_id` = `user_id_arg`)
            ) as `relation_created`
    ');
    $getRelationInfo->bind('user_id', $userId);
    $getRelationInfo->bind('subject_id', $subjectId);
    return $getRelationInfo->fetch();
}

function user_relation_count(int $userId, int $type, bool $from): int {
    if($userId < 1 || $type <= MSZ_USER_RELATION_NONE || !user_relation_is_valid_type($type)) {
        return 0;
    }

    static $getCount = [];
    $fetchCount = $getCount[$from] ?? null;

    if(empty($fetchCount)) {
        $getCount[$from] = $fetchCount = \Misuzu\DB::prepare(sprintf(
            '
                SELECT COUNT(`%1$s`)
                FROM `msz_user_relations`
                WHERE `%2$s` = :user_id
                AND `relation_type` = :type
            ',
            $from ? 'subject_id' : 'user_id',
            $from ? 'user_id' : 'subject_id'
        ));
    }

    $fetchCount->bind('user_id', $userId);
    $fetchCount->bind('type', $type);

    return (int)$fetchCount->fetchColumn();
}

function user_relation_count_to(int $userId, int $type): int {
    return user_relation_count($userId, $type, false);
}

function user_relation_count_from(int $userId, int $type): int {
    return user_relation_count($userId, $type, true);
}

function user_relation_users(
    int $userId,
    int $type,
    bool $from,
    int $take = 0,
    int $offset = 0,
    int $requestingUserId = 0
): array {
    if($userId < 1 || $type <= MSZ_USER_RELATION_NONE || !user_relation_is_valid_type($type)) {
        return [];
    }

    $fetchAll = $take < 1;
    $key = sprintf('%s,%s', $from ? 'from' : 'to', $fetchAll ? 'all' : 'page');

    static $prepared = [];
    $fetchUsers = $prepared[$key] ?? null;

    if(empty($fetchUsers)) {
        $prepared[$key] = $fetchUsers = \Misuzu\DB::prepare(sprintf(
            '
                SELECT
                    :current_user_id AS `current_user_id`,
                    ur.`%1$s` AS `user_id`, u.`username`, u.`user_country`,
                    u.`user_created`, u.`user_active`, r.`role_id`,
                    COALESCE(u.`user_title`, r.`role_title`) as `user_title`,
                    COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`,
                    (
                        SELECT COUNT(`topic_id`)
                        FROM `msz_forum_topics`
                        WHERE `user_id` = ur.`%1$s`
                        AND `topic_deleted` IS NULL
                    ) AS `user_count_topics`,
                    (
                        SELECT COUNT(`post_Id`)
                        FROM `msz_forum_posts`
                        WHERE `user_id` = ur.`%1$s`
                        AND `post_deleted` IS NULL
                    ) AS `user_count_posts`,
                    (
                        SELECT COUNT(`subject_id`)
                        FROM `msz_user_relations`
                        WHERE `user_id` = ur.`%1$s`
                        AND `relation_type` = ur.`relation_type`
                    ) AS `user_count_following`,
                    (
                        SELECT COUNT(`user_id`)
                        FROM `msz_user_relations`
                        WHERE `subject_id` = ur.`%1$s`
                        AND `relation_type` = ur.`relation_type`
                    ) AS `user_count_followers`,
                    (
                        SELECT `relation_type` = ur.`relation_type`
                        FROM `msz_user_relations`
                        WHERE `user_id` = `current_user_id`
                        AND `subject_id` = u.`user_id`
                    ) AS `user_is_following`,
                    (
                        SELECT `relation_type` = ur.`relation_type`
                        FROM `msz_user_relations`
                        WHERE `user_id` = u.`user_id`
                        AND `subject_id` = `current_user_id`
                    ) AS `user_is_follower`
                FROM `msz_user_relations` AS ur
                LEFT JOIN `msz_users` AS u
                ON u.`user_id` = ur.`%1$s`
                LEFT JOIN `msz_roles` as r
                ON r.`role_id` = u.`display_role`
                WHERE ur.`%2$s` = :user_id
                AND ur.`relation_type` = :type
                ORDER BY ur.`relation_created` DESC
                %3$s
            ',
            $from ? 'subject_id' : 'user_id',
            $from ? 'user_id' : 'subject_id',
            !$fetchAll ? 'LIMIT :offset, :take' : ''
        ));
    }

    $fetchUsers->bind('user_id', $userId);
    $fetchUsers->bind('current_user_id', $requestingUserId);
    $fetchUsers->bind('type', $type);

    if(!$fetchAll) {
        $fetchUsers->bind('take', $take);
        $fetchUsers->bind('offset', $offset);
    }

    return $fetchUsers->fetchAll();
}

function user_relation_users_to(
    int $userId,
    int $type,
    int $take = 0,
    int $offset = 0,
    int $requestingUserId = 0
): array {
    return user_relation_users($userId, $type, false, $take, $offset, $requestingUserId);
}

function user_relation_users_from(
    int $userId,
    int $type,
    int $take = 0,
    int $offset = 0,
    int $requestingUserId = 0
): array {
    return user_relation_users($userId, $type, true, $take, $offset, $requestingUserId);
}
