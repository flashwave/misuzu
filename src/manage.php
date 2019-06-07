<?php
function manage_get_menu(int $userId): array
{
    $perms = perms_get_user($userId);

    if(!perms_check($perms[MSZ_PERMS_GENERAL], MSZ_PERM_GENERAL_CAN_MANAGE)) {
        return [];
    }

    $menu = [];
    $menu['General']['Overview'] = url('manage-general-overview');

    if(perms_check($perms[MSZ_PERMS_GENERAL], MSZ_PERM_GENERAL_VIEW_LOGS)) {
        $menu['General']['Logs'] = url('manage-general-logs');
    }

    if(perms_check($perms[MSZ_PERMS_GENERAL], MSZ_PERM_GENERAL_MANAGE_EMOTICONS)) {
        $menu['General']['Emoticons'] = url('manage-general-emoticons');
    }

    if(perms_check($perms[MSZ_PERMS_GENERAL], MSZ_PERM_GENERAL_MANAGE_SETTINGS)) {
        $menu['General']['Settings'] = url('manage-general-settings');
    }

    if(perms_check($perms[MSZ_PERMS_GENERAL], MSZ_PERM_GENERAL_MANAGE_BLACKLIST)) {
        $menu['General']['IP Blacklist'] = url('manage-general-blacklist');
    }

    if(perms_check($perms[MSZ_PERMS_USER], MSZ_PERM_USER_MANAGE_USERS | MSZ_PERM_USER_MANAGE_PERMS)) {
        $menu['Users']['Listing'] = '/manage/users.php?v=listing';
    }

    if(perms_check($perms[MSZ_PERMS_USER], MSZ_PERM_USER_MANAGE_ROLES | MSZ_PERM_USER_MANAGE_PERMS)) {
        $menu['Users']['Roles'] = '/manage/users.php?v=roles';
    }

    if(perms_check($perms[MSZ_PERMS_USER], MSZ_PERM_USER_MANAGE_REPORTS)) {
        $menu['Users']['Reports'] = '/manage/users.php?v=reports';
    }

    if(perms_check($perms[MSZ_PERMS_USER], MSZ_PERM_USER_MANAGE_WARNINGS)) {
        $menu['Users']['Warnings'] = '/manage/users.php?v=warnings';
    }

    if(perms_check($perms[MSZ_PERMS_NEWS], MSZ_PERM_NEWS_MANAGE_POSTS)) {
        $menu['News']['Posts'] = '/manage/news.php?v=posts';
    }

    if(perms_check($perms[MSZ_PERMS_NEWS], MSZ_PERM_NEWS_MANAGE_CATEGORIES)) {
        $menu['News']['Categories'] = '/manage/news.php?v=categories';
    }

    if(perms_check($perms[MSZ_PERMS_FORUM], MSZ_PERM_FORUM_MANAGE_FORUMS)) {
        $menu['Forum']['Categories'] = url('manage-forum-categories');
    }

    if(perms_check($perms[MSZ_PERMS_FORUM], 0)) {
        $menu['Forum']['Settings'] = '/manage/forum.php?v=settings';
    }

    if(perms_check($perms[MSZ_PERMS_CHANGELOG], MSZ_PERM_CHANGELOG_MANAGE_CHANGES)) {
        $menu['Changelog']['Changes'] = '/manage/changelog.php?v=changes';
    }

    if(perms_check($perms[MSZ_PERMS_CHANGELOG], MSZ_PERM_CHANGELOG_MANAGE_TAGS)) {
        $menu['Changelog']['Tags'] = '/manage/changelog.php?v=tags';
    }

    return $menu;
}

define('MSZ_MANAGE_PERM_YES', 'yes');
define('MSZ_MANAGE_PERM_NO', 'no');
define('MSZ_MANAGE_PERM_NEVER', 'never');

function manage_perms_value(int $perm, int $allow, int $deny): string
{
    if(perms_check($deny, $perm)) {
        return MSZ_MANAGE_PERM_NEVER;
    }

    if(perms_check($allow, $perm)) {
        return MSZ_MANAGE_PERM_YES;
    }

    return MSZ_MANAGE_PERM_NO;
}

function manage_perms_apply(array $list, array $post, ?array $raw = null): ?array
{
    $perms = $raw !== null ? $raw : perms_create();

    foreach($list as $section) {
        if(empty($post[$section['section']])
            || !is_array($post[$section['section']])) {
            continue;
        }

        $allowKey = perms_get_key($section['section'], MSZ_PERMS_ALLOW);
        $denyKey = perms_get_key($section['section'], MSZ_PERMS_DENY);

        foreach($section['perms'] as $perm) {
            if(empty($post[$section['section']][$perm['section']]['value'])) {
                continue;
            }

            switch($post[$section['section']][$perm['section']]['value']) {
                case MSZ_MANAGE_PERM_YES:
                    $perms[$allowKey] |= $perm['perm'];
                    $perms[$denyKey] &= ~$perm['perm'];
                    break;

                case MSZ_MANAGE_PERM_NEVER:
                    $perms[$allowKey] &= ~$perm['perm'];
                    $perms[$denyKey] |= $perm['perm'];
                    break;

                case MSZ_MANAGE_PERM_NO:
                default:
                    $perms[$allowKey] &= ~$perm['perm'];
                    $perms[$denyKey] &= ~$perm['perm'];
                    break;
            }
        }
    }

    $returnNothing = 0;

    foreach($perms as $perm) {
        $returnNothing |= $perm;
    }

    if($returnNothing === 0) {
        return null;
    }

    return $perms;
}

function manage_perms_calculate(array $rawPerms, array $perms): array
{
    for($i = 0; $i < count($perms); $i++) {
        $section = $perms[$i]['section'];
        $allowKey = perms_get_key($section, MSZ_PERMS_ALLOW);
        $denyKey = perms_get_key($section, MSZ_PERMS_DENY);

        for($j = 0; $j < count($perms[$i]['perms']); $j++) {
            $permission = $perms[$i]['perms'][$j]['perm'];
            $perms[$i]['perms'][$j]['value'] = manage_perms_value($permission, $rawPerms[$allowKey], $rawPerms[$denyKey]);
        }
    }

    return $perms;
}

function manage_perms_list(array $rawPerms): array
{
    return manage_perms_calculate($rawPerms, [
        [
            'section' => MSZ_PERMS_GENERAL,
            'title' => 'General',
            'perms' => [
                [
                    'section' => 'can-manage',
                    'title' => 'Can access the management panel.',
                    'perm' => MSZ_PERM_GENERAL_CAN_MANAGE,
                ],
                [
                    'section' => 'view-logs',
                    'title' => 'Can view audit logs.',
                    'perm' => MSZ_PERM_GENERAL_VIEW_LOGS,
                ],
                [
                    'section' => 'manage-emotes',
                    'title' => 'Can manage emoticons.',
                    'perm' => MSZ_PERM_GENERAL_MANAGE_EMOTICONS,
                ],
                [
                    'section' => 'manage-settings',
                    'title' => 'Can manage general Misuzu settings.',
                    'perm' => MSZ_PERM_GENERAL_MANAGE_SETTINGS,
                ],
                [
                    'section' => 'tester',
                    'title' => 'Can use experimental features.',
                    'perm' => MSZ_PERM_GENERAL_TESTER,
                ],
                [
                    'section' => 'manage-blacklist',
                    'title' => 'Can manage blacklistings.',
                    'perm' => MSZ_PERM_GENERAL_MANAGE_BLACKLIST,
                ],
            ],
        ],
        [
            'section' => MSZ_PERMS_USER,
            'title' => 'User',
            'perms' => [
                [
                    'section' => 'edit-profile',
                    'title' => 'Can edit own profile.',
                    'perm' => MSZ_PERM_USER_EDIT_PROFILE,
                ],
                [
                    'section' => 'change-avatar',
                    'title' => 'Can change own avatar.',
                    'perm' => MSZ_PERM_USER_CHANGE_AVATAR,
                ],
                [
                    'section' => 'change-background',
                    'title' => 'Can change own background.',
                    'perm' => MSZ_PERM_USER_CHANGE_BACKGROUND,
                ],
                [
                    'section' => 'edit-about',
                    'title' => 'Can change own about section.',
                    'perm' => MSZ_PERM_USER_EDIT_ABOUT,
                ],
                [
                    'section' => 'edit-birthdate',
                    'title' => 'Can change own birthdate.',
                    'perm' => MSZ_PERM_USER_EDIT_BIRTHDATE,
                ],
                [
                    'section' => 'edit-signature',
                    'title' => 'Can change own signature.',
                    'perm' => MSZ_PERM_USER_EDIT_SIGNATURE,
                ],
                [
                    'section' => 'manage-users',
                    'title' => 'Can manage other users.',
                    'perm' => MSZ_PERM_USER_MANAGE_USERS,
                ],
                [
                    'section' => 'manage-roles',
                    'title' => 'Can manage roles.',
                    'perm' => MSZ_PERM_USER_MANAGE_ROLES,
                ],
                [
                    'section' => 'manage-perms',
                    'title' => 'Can manage permissions.',
                    'perm' => MSZ_PERM_USER_MANAGE_PERMS,
                ],
                [
                    'section' => 'manage-reports',
                    'title' => 'Can handle reports.',
                    'perm' => MSZ_PERM_USER_MANAGE_REPORTS,
                ],
                [
                    'section' => 'manage-warnings',
                    'title' => 'Can manage warnings, silences and bans.',
                    'perm' => MSZ_PERM_USER_MANAGE_WARNINGS,
                ],
            ],
        ],
        [
            'section' => MSZ_PERMS_NEWS,
            'title' => 'News',
            'perms' => [
                [
                    'section' => 'manage-posts',
                    'title' => 'Can manage posts.',
                    'perm' => MSZ_PERM_NEWS_MANAGE_POSTS,
                ],
                [
                    'section' => 'manage-cats',
                    'title' => 'Can manage catagories.',
                    'perm' => MSZ_PERM_NEWS_MANAGE_CATEGORIES,
                ],
            ],
        ],
        [
            'section' => MSZ_PERMS_FORUM,
            'title' => 'Forum',
            'perms' => [
                [
                    'section' => 'manage-forums',
                    'title' => 'Can manage forum sections.',
                    'perm' => MSZ_PERM_FORUM_MANAGE_FORUMS,
                ],
                [
                    'section' => 'view-leaderboard',
                    'title' => 'Can view the forum leaderboard live.',
                    'perm' => MSZ_PERM_FORUM_VIEW_LEADERBOARD,
                ],
            ],
        ],
        [
            'section' => MSZ_PERMS_COMMENTS,
            'title' => 'Comments',
            'perms' => [
                [
                    'section' => 'create',
                    'title' => 'Can post comments.',
                    'perm' => MSZ_PERM_COMMENTS_CREATE,
                ],
                [
                    'section' => 'delete-own',
                    'title' => 'Can delete own comments.',
                    'perm' => MSZ_PERM_COMMENTS_DELETE_OWN,
                ],
                [
                    'section' => 'delete-any',
                    'title' => 'Can delete anyone\'s comments.',
                    'perm' => MSZ_PERM_COMMENTS_DELETE_ANY,
                ],
                [
                    'section' => 'pin',
                    'title' => 'Can pin comments.',
                    'perm' => MSZ_PERM_COMMENTS_PIN,
                ],
                [
                    'section' => 'lock',
                    'title' => 'Can lock comment threads.',
                    'perm' => MSZ_PERM_COMMENTS_LOCK,
                ],
                [
                    'section' => 'vote',
                    'title' => 'Can like or dislike comments.',
                    'perm' => MSZ_PERM_COMMENTS_VOTE,
                ],
            ],
        ],
        [
            'section' => MSZ_PERMS_CHANGELOG,
            'title' => 'Changelog',
            'perms' => [
                [
                    'section' => 'manage-changes',
                    'title' => 'Can manage changes.',
                    'perm' => MSZ_PERM_CHANGELOG_MANAGE_CHANGES,
                ],
                [
                    'section' => 'manage-tags',
                    'title' => 'Can manage tags.',
                    'perm' => MSZ_PERM_CHANGELOG_MANAGE_TAGS,
                ],
            ],
        ],
    ]);
}

function manage_forum_perms_list(array $rawPerms): array
{
    return manage_perms_calculate($rawPerms, [
        [
            'section' => MSZ_FORUM_PERMS_GENERAL,
            'title' => 'Forum',
            'perms' => [
                [
                    'section' => 'can-list',
                    'title' => 'Can see the forum listed, but not access it.',
                    'perm' => MSZ_FORUM_PERM_LIST_FORUM,
                ],
                [
                    'section' => 'can-view',
                    'title' => 'Can view and access the forum.',
                    'perm' => MSZ_FORUM_PERM_VIEW_FORUM,
                ],
                [
                    'section' => 'can-create-topic',
                    'title' => 'Can create topics.',
                    'perm' => MSZ_FORUM_PERM_CREATE_TOPIC,
                ],
                [
                    'section' => 'can-move-topic',
                    'title' => 'Can move topics between forums.',
                    'perm' => MSZ_FORUM_PERM_MOVE_TOPIC,
                ],
                [
                    'section' => 'can-lock-topic',
                    'title' => 'Can lock topics.',
                    'perm' => MSZ_FORUM_PERM_LOCK_TOPIC,
                ],
                [
                    'section' => 'can-sticky-topic',
                    'title' => 'Can make topics sticky.',
                    'perm' => MSZ_FORUM_PERM_STICKY_TOPIC,
                ],
                [
                    'section' => 'can-announce-topic',
                    'title' => 'Can make topics announcements.',
                    'perm' => MSZ_FORUM_PERM_ANNOUNCE_TOPIC,
                ],
                [
                    'section' => 'can-global-announce-topic',
                    'title' => 'Can make topics global announcements.',
                    'perm' => MSZ_FORUM_PERM_GLOBAL_ANNOUNCE_TOPIC,
                ],
                [
                    'section' => 'can-bump-topic',
                    'title' => 'Can bump topics without posting a reply.',
                    'perm' => MSZ_FORUM_PERM_BUMP_TOPIC,
                ],
                [
                    'section' => 'can-priority-vote',
                    'title' => 'Can vote on topic priority.',
                    'perm' => MSZ_FORUM_PERM_PRIORITY_VOTE,
                ],
                [
                    'section' => 'can-create-post',
                    'title' => 'Can make posts (reply only, if create topic is disallowed).',
                    'perm' => MSZ_FORUM_PERM_CREATE_POST,
                ],
                [
                    'section' => 'can-edit-post',
                    'title' => 'Can edit their own posts.',
                    'perm' => MSZ_FORUM_PERM_EDIT_POST,
                ],
                [
                    'section' => 'can-edit-any-post',
                    'title' => 'Can edit any posts.',
                    'perm' => MSZ_FORUM_PERM_EDIT_ANY_POST,
                ],
                [
                    'section' => 'can-delete-post',
                    'title' => 'Can delete own posts.',
                    'perm' => MSZ_FORUM_PERM_DELETE_POST,
                ],
                [
                    'section' => 'can-delete-any-post',
                    'title' => 'Can delete any posts.',
                    'perm' => MSZ_FORUM_PERM_DELETE_ANY_POST,
                ],
            ],
        ],
    ]);
}
