<?php
use Misuzu\Database;

define('MSZ_USER_RELATION_FOLLOW', 1);

define('MSZ_USER_RELATION_TYPES', [
    MSZ_USER_RELATION_FOLLOW,
]);

define('MSZ_USER_RELATION_E_OK', 0);
define('MSZ_USER_RELATION_E_USER_ID', 1);
define('MSZ_USER_RELATION_E_DATABASE', 2);
define('MSZ_USER_RELATION_E_INVALID_TYPE', 2);

function user_relation_add(int $userId, int $subjectId, int $type = MSZ_USER_RELATION_FOLLOW): int
{
    if ($userId < 1 || $subjectId < 1) {
        return MSZ_USER_RELATION_E_USER_ID;
    }

    if (!in_array($type, MSZ_USER_RELATION_TYPES, true)) {
        return MSZ_USER_RELATION_E_INVALID_TYPE;
    }

    $addRelation = Database::prepare('
        REPLACE INTO `msz_user_relations`
            (`user_id`, `subject_id`, `relation_type`)
        VALUES
            (:user_id, :subject_id, :type)
    ');
    $addRelation->bindValue('user_id', $userId);
    $addRelation->bindValue('subject_id', $subjectId);
    $addRelation->bindValue('type', $type);
    $addRelation->execute();

    return $addRelation->execute()
        ? MSZ_USER_RELATION_E_OK
        : MSZ_USER_RELATION_E_DATABASE;
}

function user_relation_remove(int $userId, int $subjectId): bool
{
    if ($userId < 1 || $subjectId < 1) {
        return false;
    }

    $removeRelation = Database::prepare('
        DELETE FROM `msz_user_relations`
        WHERE `user_id` = :user_id
        AND `subject_id` = :subject_id
    ');
    $removeRelation->bindValue('user_id', $userId);
    $removeRelation->bindValue('subject_id', $subjectId);

    return $removeRelation->execute();
}
