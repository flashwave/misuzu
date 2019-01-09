<?php
define('MSZ_USER_RELATION_NONE', 0);
define('MSZ_USER_RELATION_FOLLOW', 1);

define('MSZ_USER_RELATION_TYPES', [
    MSZ_USER_RELATION_NONE,
    MSZ_USER_RELATION_FOLLOW,
]);

function user_relation_is_valid_type(int $type): bool
{
    return in_array($type, MSZ_USER_RELATION_TYPES, true);
}

function user_relation_set(int $userId, int $subjectId, int $type = MSZ_USER_RELATION_FOLLOW): bool
{
    if ($type === MSZ_USER_RELATION_NONE) {
        return user_relation_remove($userId, $subjectId);
    }

    if ($userId < 1 || $subjectId < 1 || !user_relation_is_valid_type($type)) {
        return false;
    }

    $addRelation = db_prepare('
        REPLACE INTO `msz_user_relations`
            (`user_id`, `subject_id`, `relation_type`)
        VALUES
            (:user_id, :subject_id, :type)
    ');
    $addRelation->bindValue('user_id', $userId);
    $addRelation->bindValue('subject_id', $subjectId);
    $addRelation->bindValue('type', $type);
    $addRelation->execute();

    return $addRelation->execute();
}

function user_relation_remove(int $userId, int $subjectId): bool
{
    if ($userId < 1 || $subjectId < 1) {
        return false;
    }

    $removeRelation = db_prepare('
        DELETE FROM `msz_user_relations`
        WHERE `user_id` = :user_id
        AND `subject_id` = :subject_id
    ');
    $removeRelation->bindValue('user_id', $userId);
    $removeRelation->bindValue('subject_id', $subjectId);

    return $removeRelation->execute();
}

function user_relation_info(int $userId, int $subjectId): array
{
    $getRelationInfo = db_prepare('
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
    $getRelationInfo->bindValue('user_id', $userId);
    $getRelationInfo->bindValue('subject_id', $subjectId);
    return db_fetch($getRelationInfo);
}
