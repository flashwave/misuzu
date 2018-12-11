<?php
function manage_get_menu(int $userId): array
{
    $perms = [];

    foreach (MSZ_PERM_MODES as $mode) {
        $perms[$mode] = perms_get_user($mode, $userId);
    }

    if (!perms_check($perms['general'], MSZ_PERM_GENERAL_CAN_MANAGE)) {
        return [];
    }

    $menu = [];
    $menu['General']['Overview'] = '/manage/index.php?v=overview';
    $menu['General']['Quotes'] = '/manage/index.php?v=quotes';

    if (perms_check($perms['general'], MSZ_PERM_GENERAL_VIEW_LOGS)) {
        $menu['General']['Logs'] = '/manage/index.php?v=logs';
    }

    if (perms_check($perms['general'], MSZ_PERM_GENERAL_MANAGE_EMOTICONS)) {
        $menu['General']['Emoticons'] = '/manage/index.php?v=emoticons';
    }

    if (perms_check($perms['general'], MSZ_PERM_GENERAL_MANAGE_SETTINGS)) {
        $menu['General']['Settings'] = '/manage/index.php?v=settings';
    }

    if (perms_check($perms['user'], MSZ_PERM_USER_MANAGE_USERS | MSZ_PERM_USER_MANAGE_PERMS)) {
        $menu['Users']['Listing'] = '/manage/users.php?v=listing';
    }

    if (perms_check($perms['user'], MSZ_PERM_USER_MANAGE_ROLES | MSZ_PERM_USER_MANAGE_PERMS)) {
        $menu['Users']['Roles'] = '/manage/users.php?v=roles';
    }

    if (perms_check($perms['user'], MSZ_PERM_USER_MANAGE_REPORTS)) {
        $menu['Users']['Reports'] = '/manage/users.php?v=reports';
    }

    if (perms_check($perms['user'], MSZ_PERM_USER_MANAGE_RESTRICTIONS)) {
        $menu['Users']['Restrictions'] = '/manage/users.php?v=restrictions';
    }

    if (perms_check($perms['user'], MSZ_PERM_USER_MANAGE_BLACKLISTS)) {
        $menu['Users']['Blacklisting'] = '/manage/users.php?v=blacklisting';
    }

    if (perms_check($perms['news'], MSZ_PERM_NEWS_MANAGE_POSTS)) {
        $menu['News']['Posts'] = '/manage/news.php?v=posts';
    }

    if (perms_check($perms['news'], MSZ_PERM_NEWS_MANAGE_CATEGORIES)) {
        $menu['News']['Categories'] = '/manage/news.php?v=categories';
    }

    if (perms_check($perms['forum'], MSZ_PERM_FORUM_MANAGE_FORUMS)) {
        $menu['Forum']['Listing'] = '/manage/forum.php?v=listing';
    }

    if (perms_check($perms['forum'], 0)) {
        $menu['Forum']['Settings'] = '/manage/forum.php?v=settings';
    }

    if (perms_check($perms['changelog'], MSZ_PERM_CHANGELOG_MANAGE_CHANGES)) {
        $menu['Changelog']['Changes'] = '/manage/changelog.php?v=changes';
    }

    if (perms_check($perms['changelog'], MSZ_PERM_CHANGELOG_MANAGE_TAGS | MSZ_PERM_CHANGELOG_MANAGE_ACTIONS)) {
        $menu['Changelog']['Action & Tags'] = '/manage/changelog.php?v=tags';
    }

    return $menu;
}

function manage_perms_value(int $perm, int $allow, int $deny): string
{
    if (perms_check($deny, $perm)) {
        return 'never';
    }

    if (perms_check($allow, $perm)) {
        return 'yes';
    }

    return 'no';
}

function manage_perms_apply(array $list, array $post): ?array
{
    $perms = perms_create();

    foreach ($list as $section) {
        if (empty($post[$section['section']])
            || !is_array($post[$section['section']])) {
            continue;
        }

        $allowKey = perms_get_key($section['section'], MSZ_PERMS_ALLOW);
        $denyKey = perms_get_key($section['section'], MSZ_PERMS_DENY);
        $overrideKey = perms_get_key($section['section'], MSZ_PERMS_OVERRIDE);

        foreach ($section['perms'] as $perm) {
            if (empty($post[$section['section']][$perm['section']]['value'])) {
                continue;
            }

            switch ($post[$section['section']][$perm['section']]['value']) {
                case 'yes':
                    $perms[$allowKey] |= $perm['perm'];
                    $perms[$denyKey] &= ~$perm['perm'];
                    break;

                case 'never':
                    $perms[$allowKey] &= ~$perm['perm'];
                    $perms[$denyKey] |= $perm['perm'];
                    break;

                case 'no':
                default:
                    $perms[$allowKey] &= ~$perm['perm'];
                    $perms[$denyKey] &= ~$perm['perm'];
                    break;
            }

            if (!empty($post[$section['section']][$perm['section']]['override'])) {
                $perms[$overrideKey] |= $perm['perm'];
            } else {
                $perms[$overrideKey] &= ~$perm['perm'];
            }
        }
    }

    $returnNothing = 0;

    foreach ($perms as $perm) {
        $returnNothing |= $perm;
    }

    if ($returnNothing === 0) {
        return null;
    }

    return $perms;
}

function manage_perms_calculate(array $rawPerms, array $perms): array
{
    for ($i = 0; $i < count($perms); $i++) {
        $section = $perms[$i]['section'];
        $allowKey = perms_get_key($section, MSZ_PERMS_ALLOW);
        $denyKey = perms_get_key($section, MSZ_PERMS_DENY);
        $overrideKey = perms_get_key($section, MSZ_PERMS_OVERRIDE);

        for ($j = 0; $j < count($perms[$i]['perms']); $j++) {
            $permission = $perms[$i]['perms'][$j]['perm'];
            $perms[$i]['perms'][$j]['override'] = perms_check($rawPerms[$overrideKey], $permission);
            $perms[$i]['perms'][$j]['value'] = manage_perms_value($permission, $rawPerms[$allowKey], $rawPerms[$denyKey]);
        }
    }

    return $perms;
}

function manage_perms_list(array $rawPerms): array
{
    return manage_perms_calculate($rawPerms, [
        [
            'section' => 'general',
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
            ],
        ],
        [
            'section' => 'user',
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
                    'section' => 'manage-restrictions',
                    'title' => 'Can manage restrictions.',
                    'perm' => MSZ_PERM_USER_MANAGE_RESTRICTIONS,
                ],
                [
                    'section' => 'manage-blacklistings',
                    'title' => 'Can manage blacklistings.',
                    'perm' => MSZ_PERM_USER_MANAGE_BLACKLISTS,
                ],
            ],
        ],
        [
            'section' => 'news',
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
            'section' => 'forum',
            'title' => 'Forum',
            'perms' => [
                [
                    'section' => 'manage-forums',
                    'title' => 'Can manage forum sections.',
                    'perm' => MSZ_PERM_FORUM_MANAGE_FORUMS,
                ],
            ],
        ],
        [
            'section' => 'comments',
            'title' => 'Comments',
            'perms' => [
                [
                    'section' => 'create',
                    'title' => 'Can post comments.',
                    'perm' => MSZ_PERM_COMMENTS_CREATE,
                ],
                [
                    'section' => 'edit-own',
                    'title' => 'Can edit own comments.',
                    'perm' => MSZ_PERM_COMMENTS_EDIT_OWN,
                ],
                [
                    'section' => 'edit-any',
                    'title' => 'Can edit anyone\'s comments.',
                    'perm' => MSZ_PERM_COMMENTS_EDIT_ANY,
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
            'section' => 'changelog',
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
                [
                    'section' => 'manage-actions',
                    'title' => 'Can manage action types.',
                    'perm' => MSZ_PERM_CHANGELOG_MANAGE_ACTIONS,
                ],
            ],
        ],
    ]);
}

function manage_forum_perms_list(array $rawPerms): array
{
    return manage_perms_calculate($rawPerms, [
        [
            'section' => 'forum',
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
                    'section' => 'can-delete-topic',
                    'title' => 'Can delete topics (required a post delete permission).',
                    'perm' => MSZ_FORUM_PERM_DELETE_TOPIC,
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
