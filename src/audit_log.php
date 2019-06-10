<?php
audit_log_define('PERSONAL_EMAIL_CHANGE');
audit_log_define('PERSONAL_PASSWORD_CHANGE');
audit_log_define('PERSONAL_SESSION_DESTROY');
audit_log_define('PERSONAL_SESSION_DESTROY_ALL');
audit_log_define('PASSWORD_RESET');
audit_log_define('CHANGELOG_ENTRY_CREATE');
audit_log_define('CHANGELOG_ENTRY_EDIT');
audit_log_define('CHANGELOG_TAG_ADD');
audit_log_define('CHANGELOG_TAG_REMOVE');
audit_log_define('CHANGELOG_TAG_CREATE');
audit_log_define('CHANGELOG_TAG_EDIT');
audit_log_define('CHANGELOG_ACTION_CREATE');
audit_log_define('CHANGELOG_ACTION_EDIT');
audit_log_define('COMMENT_ENTRY_DELETE');
audit_log_define('COMMENT_ENTRY_DELETE_MOD');
audit_log_define('COMMENT_ENTRY_RESTORE');
audit_log_define('NEWS_POST_CREATE');
audit_log_define('NEWS_POST_EDIT');
audit_log_define('NEWS_CATEGORY_CREATE');
audit_log_define('NEWS_CATEGORY_EDIT');
audit_log_define('FORUM_POST_EDIT');
audit_log_define('FORUM_POST_DELETE');
audit_log_define('FORUM_POST_RESTORE');
audit_log_define('FORUM_POST_NUKE');
audit_log_define('FORUM_TOPIC_DELETE');
audit_log_define('FORUM_TOPIC_RESTORE');
audit_log_define('FORUM_TOPIC_NUKE');
audit_log_define('FORUM_TOPIC_BUMP');
audit_log_define('FORUM_TOPIC_LOCK');
audit_log_define('FORUM_TOPIC_UNLOCK');

// replace this with a localisation system
define('MSZ_AUDIT_LOG_STRINGS', [
    MSZ_AUDIT_PERSONAL_EMAIL_CHANGE         => 'Changed e-mail address to %s.',
    MSZ_AUDIT_PERSONAL_PASSWORD_CHANGE      => 'Changed account password.',
    MSZ_AUDIT_PERSONAL_SESSION_DESTROY      => 'Ended session #%d.',
    MSZ_AUDIT_PERSONAL_SESSION_DESTROY_ALL  => 'Ended all personal sessions.',
    MSZ_AUDIT_PASSWORD_RESET                => 'Successfully used the password reset form to change password.',
    MSZ_AUDIT_CHANGELOG_ENTRY_CREATE        => 'Created a new changelog entry #%d.',
    MSZ_AUDIT_CHANGELOG_ENTRY_EDIT          => 'Edited changelog entry #%d.',
    MSZ_AUDIT_CHANGELOG_TAG_ADD             => 'Added tag #%2$d to changelog entry #%1$d.',
    MSZ_AUDIT_CHANGELOG_TAG_REMOVE          => 'Removed tag #%2$d from changelog entry #%1$d.',
    MSZ_AUDIT_CHANGELOG_TAG_CREATE          => 'Created new changelog tag #%d.',
    MSZ_AUDIT_CHANGELOG_TAG_EDIT            => 'Edited changelog tag #%d.',
    MSZ_AUDIT_CHANGELOG_ACTION_CREATE       => 'Created new changelog action #%d.',
    MSZ_AUDIT_CHANGELOG_ACTION_EDIT         => 'Edited changelog action #%d.',
    MSZ_AUDIT_COMMENT_ENTRY_DELETE          => 'Deleted comment #%d.',
    MSZ_AUDIT_COMMENT_ENTRY_DELETE_MOD      => 'Deleted comment #%d by user #%d %s.',
    MSZ_AUDIT_COMMENT_ENTRY_RESTORE         => 'Restored comment #%d by user #%d %s.',
    MSZ_AUDIT_NEWS_POST_CREATE              => 'Created news post #%d.',
    MSZ_AUDIT_NEWS_POST_EDIT                => 'Edited news post #%d.',
    MSZ_AUDIT_NEWS_CATEGORY_CREATE          => 'Created news category #%d.',
    MSZ_AUDIT_NEWS_CATEGORY_EDIT            => 'Edited news category #%d.',
    MSZ_AUDIT_FORUM_POST_EDIT               => 'Edited forum post #%d.',
    MSZ_AUDIT_FORUM_POST_DELETE             => 'Deleted forum post #%d.',
    MSZ_AUDIT_FORUM_POST_RESTORE            => 'Restored forum post #%d.',
    MSZ_AUDIT_FORUM_POST_NUKE               => 'Nuked forum post #%d.',
    MSZ_AUDIT_FORUM_TOPIC_DELETE            => 'Deleted forum topic #%d.',
    MSZ_AUDIT_FORUM_TOPIC_RESTORE           => 'Restored forum topic #%d.',
    MSZ_AUDIT_FORUM_TOPIC_NUKE              => 'Nuked forum topic #%d.',
    MSZ_AUDIT_FORUM_TOPIC_BUMP              => 'Manually bumped forum topic #%d.',
    MSZ_AUDIT_FORUM_TOPIC_LOCK              => 'Locked forum topic #%d.',
    MSZ_AUDIT_FORUM_TOPIC_UNLOCK            => 'Unlocked forum topic #%d.',
]);

function audit_log_define(string $name): void {
    define("MSZ_AUDIT_{$name}", $name);
}

function audit_log(
    string $action,
    int $userId = 0,
    array $params = [],
    ?string $ipAddress = null
): void {
    $ipAddress = $ipAddress ?? ip_remote_address();

    for($i = 0; $i < count($params); $i++) {
        if(preg_match('#^(-?[0-9]+)$#', $params[$i])) {
            $params[$i] = (int)$params[$i];
        }
    }

    $addLog = db_prepare('
        INSERT INTO `msz_audit_log`
            (`log_action`, `user_id`, `log_params`, `log_ip`, `log_country`)
        VALUES
            (:action, :user, :params, INET6_ATON(:ip), :country)
    ');
    $addLog->bindValue('action', $action);
    $addLog->bindValue('user', $userId < 1 ? null : $userId);
    $addLog->bindValue('params', json_encode($params));
    $addLog->bindValue('ip', $ipAddress);
    $addLog->bindValue('country', ip_country_code($ipAddress));
    $addLog->execute();
}

function audit_log_count($userId = 0): int {
    $getCount = db_prepare(sprintf('
        SELECT COUNT(`log_id`)
        FROM `msz_audit_log`
        %s
    ', $userId < 1 ? '' : 'WHERE `user_id` = :user_id'));

    if($userId >= 1) {
        $getCount->bindValue('user_id', $userId);
    }

    return $getCount->execute() ? (int)$getCount->fetchColumn() : 0;
}

function audit_log_list(int $offset, int $take, int $userId = 0): array {
    $offset = max(0, $offset);
    $take = max(1, $take);
    $isGlobal = $userId < 1;

    $getLogs = db_prepare(sprintf(
        '
            SELECT
                l.`log_id`, l.`log_action`, l.`log_params`, l.`log_created`, l.`log_country`,
                INET6_NTOA(l.`log_ip`) as `log_ip`
                %2$s
            FROM `msz_audit_log` as l
            %1$s
            ORDER BY l.`log_id` DESC
            LIMIT :offset, :take
        ',
        $isGlobal
            ? 'LEFT JOIN `msz_users` as u ON u.`user_id` = l.`user_id` LEFT JOIN `msz_roles` as r ON r.`role_id` = u.`display_role`'
            : 'WHERE l.`user_id` = :user_id',
        $isGlobal
            ? ', u.`user_id`, u.`username`, COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`'
            : ''
    ));

    if(!$isGlobal) {
        $getLogs->bindValue('user_id', $userId);
    }

    $getLogs->bindValue('offset', $offset);
    $getLogs->bindValue('take', $take);
    return db_fetch_all($getLogs);
}
