<?php
function manage_get_menu(int $userId): array
{
    $perms = [];

    foreach (MSZ_PERM_MODES as $mode) {
        $perms[$mode] = perms_get_user($mode, $userId);
    }

    if (!perms_check($perms['general'], MSZ_GENERAL_PERM_CAN_MANAGE)) {
        return [];
    }

    $menu = [];
    $menu['General']['Overview'] = '/manage/index.php?v=overview';

    if (perms_check($perms['general'], MSZ_GENERAL_PERM_VIEW_LOGS)) {
        $menu['General']['Logs'] = '/manage/index.php?v=logs';
    }

    if (perms_check($perms['general'], MSZ_GENERAL_PERM_MANAGE_EMOTICONS)) {
        $menu['General']['Emoticons'] = '/manage/index.php?v=emoticons';
    }

    if (perms_check($perms['general'], MSZ_GENERAL_PERM_MANAGE_SETTINGS)) {
        $menu['General']['Settings'] = '/manage/index.php?v=settings';
    }

    if (perms_check($perms['user'], MSZ_USER_PERM_MANAGE_USERS | MSZ_USER_PERM_MANAGE_PERMS)) {
        $menu['Users']['Listing'] = '/manage/users.php?v=listing';
    }

    if (perms_check($perms['user'], MSZ_USER_PERM_MANAGE_ROLES | MSZ_USER_PERM_MANAGE_PERMS)) {
        $menu['Users']['Roles'] = '/manage/users.php?v=roles';
    }

    if (perms_check($perms['user'], MSZ_USER_PERM_MANAGE_REPORTS)) {
        $menu['Users']['Reports'] = '/manage/users.php?v=reports';
    }

    if (perms_check($perms['user'], MSZ_USER_PERM_MANAGE_RESTRICTIONS)) {
        $menu['Users']['Restrictions'] = '/manage/users.php?v=restrictions';
    }

    if (perms_check($perms['user'], MSZ_USER_PERM_MANAGE_BLACKLISTS)) {
        $menu['Users']['Blacklisting'] = '/manage/users.php?v=blacklisting';
    }

    if (perms_check($perms['news'], MSZ_NEWS_PERM_MANAGE_POSTS)) {
        $menu['News']['Posts'] = '/manage/news.php?v=posts';
    }

    if (perms_check($perms['news'], MSZ_NEWS_PERM_MANAGE_CATEGORIES)) {
        $menu['News']['Categories'] = '/manage/news.php?v=categories';
    }

    if (perms_check($perms['forum'], MSZ_FORUM_PERM_MANAGE_FORUMS)) {
        $menu['Forums']['Listing'] = '/manage/forums.php?v=listing';
    }

    if (perms_check($perms['forum'], 0)) {
        $menu['Forums']['Permissions'] = '/manage/forums.php?v=permissions';
    }

    if (perms_check($perms['forum'], 0)) {
        $menu['Forums']['Settings'] = '/manage/forums.php?v=settings';
    }

    if (perms_check($perms['changelog'], MSZ_CHANGELOG_PERM_MANAGE_CHANGES)) {
        $menu['Changelog']['Changes'] = '/manage/changelog.php?v=changes';
    }

    if (perms_check($perms['changelog'], MSZ_CHANGELOG_PERM_MANAGE_TAGS)) {
        $menu['Changelog']['Tags'] = '/manage/changelog.php?v=tags';
    }

    if (perms_check($perms['changelog'], MSZ_CHANGELOG_PERM_MANAGE_ACTIONS)) {
        $menu['Changelog']['Actions'] = '/manage/changelog.php?v=actions';
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

        $allowKey = perms_get_key($section['section'], 'allow');
        $denyKey = perms_get_key($section['section'], 'deny');

        foreach ($section['perms'] as $perm) {
            if (empty($post[$section['section']][$perm['section']])) {
                continue;
            }

            switch ($post[$section['section']][$perm['section']]) {
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

function manage_perms_list(array $rawPerms): array
{
    return [
        [
            'section' => 'general',
            'title' => 'General',
            'perms' => [
                [
                    'section' => 'can-manage',
                    'title' => 'Can access the management panel.',
                    'perm' => MSZ_GENERAL_PERM_CAN_MANAGE,
                    'value' => manage_perms_value(
                        MSZ_GENERAL_PERM_CAN_MANAGE,
                        $rawPerms['general_perms_allow'],
                        $rawPerms['general_perms_deny']
                    ),
                ],
                [
                    'section' => 'view-logs',
                    'title' => 'Can view audit logs.',
                    'perm' => MSZ_GENERAL_PERM_VIEW_LOGS,
                    'value' => manage_perms_value(
                        MSZ_GENERAL_PERM_VIEW_LOGS,
                        $rawPerms['general_perms_allow'],
                        $rawPerms['general_perms_deny']
                    )
                ],
                [
                    'section' => 'manage-emotes',
                    'title' => 'Can manage emoticons.',
                    'perm' => MSZ_GENERAL_PERM_MANAGE_EMOTICONS,
                    'value' => manage_perms_value(
                        MSZ_GENERAL_PERM_MANAGE_EMOTICONS,
                        $rawPerms['general_perms_allow'],
                        $rawPerms['general_perms_deny']
                    )
                ],
                [
                    'section' => 'manage-settings',
                    'title' => 'Can manage general Misuzu settings.',
                    'perm' => MSZ_GENERAL_PERM_MANAGE_SETTINGS,
                    'value' => manage_perms_value(
                        MSZ_GENERAL_PERM_MANAGE_SETTINGS,
                        $rawPerms['general_perms_allow'],
                        $rawPerms['general_perms_deny']
                    )
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
                    'perm' => MSZ_USER_PERM_EDIT_PROFILE,
                    'value' => manage_perms_value(
                        MSZ_USER_PERM_EDIT_PROFILE,
                        $rawPerms['user_perms_allow'],
                        $rawPerms['user_perms_deny']
                    ),
                ],
                [
                    'section' => 'change-avatar',
                    'title' => 'Can change own avatar.',
                    'perm' => MSZ_USER_PERM_CHANGE_AVATAR,
                    'value' => manage_perms_value(
                        MSZ_USER_PERM_CHANGE_AVATAR,
                        $rawPerms['user_perms_allow'],
                        $rawPerms['user_perms_deny']
                    ),
                ],
                [
                    'section' => 'manage-users',
                    'title' => 'Can manage other users.',
                    'perm' => MSZ_USER_PERM_MANAGE_USERS,
                    'value' => manage_perms_value(
                        MSZ_USER_PERM_MANAGE_USERS,
                        $rawPerms['user_perms_allow'],
                        $rawPerms['user_perms_deny']
                    ),
                ],
                [
                    'section' => 'manage-roles',
                    'title' => 'Can manage roles.',
                    'perm' => MSZ_USER_PERM_MANAGE_ROLES,
                    'value' => manage_perms_value(
                        MSZ_USER_PERM_MANAGE_ROLES,
                        $rawPerms['user_perms_allow'],
                        $rawPerms['user_perms_deny']
                    ),
                ],
                [
                    'section' => 'manage-perms',
                    'title' => 'Can manage permissions.',
                    'perm' => MSZ_USER_PERM_MANAGE_PERMS,
                    'value' => manage_perms_value(
                        MSZ_USER_PERM_MANAGE_PERMS,
                        $rawPerms['user_perms_allow'],
                        $rawPerms['user_perms_deny']
                    ),
                ],
                [
                    'section' => 'manage-reports',
                    'title' => 'Can handle reports.',
                    'perm' => MSZ_USER_PERM_MANAGE_REPORTS,
                    'value' => manage_perms_value(
                        MSZ_USER_PERM_MANAGE_REPORTS,
                        $rawPerms['user_perms_allow'],
                        $rawPerms['user_perms_deny']
                    ),
                ],
                [
                    'section' => 'manage-restrictions',
                    'title' => 'Can manage restrictions.',
                    'perm' => MSZ_USER_PERM_MANAGE_RESTRICTIONS,
                    'value' => manage_perms_value(
                        MSZ_USER_PERM_MANAGE_RESTRICTIONS,
                        $rawPerms['user_perms_allow'],
                        $rawPerms['user_perms_deny']
                    ),
                ],
                [
                    'section' => 'manage-blacklistings',
                    'title' => 'Can manage blacklistings.',
                    'perm' => MSZ_USER_PERM_MANAGE_BLACKLISTS,
                    'value' => manage_perms_value(
                        MSZ_USER_PERM_MANAGE_BLACKLISTS,
                        $rawPerms['user_perms_allow'],
                        $rawPerms['user_perms_deny']
                    ),
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
                    'perm' => MSZ_NEWS_PERM_MANAGE_POSTS,
                    'value' => manage_perms_value(
                        MSZ_NEWS_PERM_MANAGE_POSTS,
                        $rawPerms['news_perms_allow'],
                        $rawPerms['news_perms_deny']
                    ),
                ],
                [
                    'section' => 'manage-cats',
                    'title' => 'Can manage catagories.',
                    'perm' => MSZ_NEWS_PERM_MANAGE_CATEGORIES,
                    'value' => manage_perms_value(
                        MSZ_NEWS_PERM_MANAGE_CATEGORIES,
                        $rawPerms['news_perms_allow'],
                        $rawPerms['news_perms_deny']
                    ),
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
                    'perm' => MSZ_FORUM_PERM_MANAGE_FORUMS,
                    'value' => manage_perms_value(
                        MSZ_FORUM_PERM_MANAGE_FORUMS,
                        $rawPerms['forum_perms_allow'],
                        $rawPerms['forum_perms_deny']
                    )
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
                    'perm' => MSZ_COMMENTS_PERM_CREATE,
                    'value' => manage_perms_value(
                        MSZ_COMMENTS_PERM_CREATE,
                        $rawPerms['comments_perms_allow'],
                        $rawPerms['comments_perms_deny']
                    ),
                ],
                [
                    'section' => 'edit-own',
                    'title' => 'Can edit own comments.',
                    'perm' => MSZ_COMMENTS_PERM_EDIT_OWN,
                    'value' => manage_perms_value(
                        MSZ_COMMENTS_PERM_EDIT_OWN,
                        $rawPerms['comments_perms_allow'],
                        $rawPerms['comments_perms_deny']
                    ),
                ],
                [
                    'section' => 'edit-any',
                    'title' => 'Can edit anyone\'s comments.',
                    'perm' => MSZ_COMMENTS_PERM_EDIT_ANY,
                    'value' => manage_perms_value(
                        MSZ_COMMENTS_PERM_EDIT_ANY,
                        $rawPerms['comments_perms_allow'],
                        $rawPerms['comments_perms_deny']
                    ),
                ],
                [
                    'section' => 'delete-own',
                    'title' => 'Can delete own comments.',
                    'perm' => MSZ_COMMENTS_PERM_DELETE_OWN,
                    'value' => manage_perms_value(
                        MSZ_COMMENTS_PERM_DELETE_OWN,
                        $rawPerms['comments_perms_allow'],
                        $rawPerms['comments_perms_deny']
                    ),
                ],
                [
                    'section' => 'delete-any',
                    'title' => 'Can delete anyone\'s comments.',
                    'perm' => MSZ_COMMENTS_PERM_DELETE_ANY,
                    'value' => manage_perms_value(
                        MSZ_COMMENTS_PERM_DELETE_ANY,
                        $rawPerms['comments_perms_allow'],
                        $rawPerms['comments_perms_deny']
                    ),
                ],
                [
                    'section' => 'pin',
                    'title' => 'Can pin comments.',
                    'perm' => MSZ_COMMENTS_PERM_PIN,
                    'value' => manage_perms_value(
                        MSZ_COMMENTS_PERM_PIN,
                        $rawPerms['comments_perms_allow'],
                        $rawPerms['comments_perms_deny']
                    ),
                ],
                [
                    'section' => 'lock',
                    'title' => 'Can lock comment threads.',
                    'perm' => MSZ_COMMENTS_PERM_LOCK,
                    'value' => manage_perms_value(
                        MSZ_COMMENTS_PERM_LOCK,
                        $rawPerms['comments_perms_allow'],
                        $rawPerms['comments_perms_deny']
                    ),
                ],
                [
                    'section' => 'vote',
                    'title' => 'Can like or dislike comments.',
                    'perm' => MSZ_COMMENTS_PERM_VOTE,
                    'value' => manage_perms_value(
                        MSZ_COMMENTS_PERM_VOTE,
                        $rawPerms['comments_perms_allow'],
                        $rawPerms['comments_perms_deny']
                    ),
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
                    'perm' => MSZ_CHANGELOG_PERM_MANAGE_CHANGES,
                    'value' => manage_perms_value(
                        MSZ_CHANGELOG_PERM_MANAGE_CHANGES,
                        $rawPerms['changelog_perms_allow'],
                        $rawPerms['changelog_perms_deny']
                    ),
                ],
                [
                    'section' => 'manage-tags',
                    'title' => 'Can manage tags.',
                    'perm' => MSZ_CHANGELOG_PERM_MANAGE_TAGS,
                    'value' => manage_perms_value(
                        MSZ_CHANGELOG_PERM_MANAGE_TAGS,
                        $rawPerms['changelog_perms_allow'],
                        $rawPerms['changelog_perms_deny']
                    ),
                ],
                [
                    'section' => 'manage-actions',
                    'title' => 'Can manage action types.',
                    'perm' => MSZ_CHANGELOG_PERM_MANAGE_ACTIONS,
                    'value' => manage_perms_value(
                        MSZ_CHANGELOG_PERM_MANAGE_ACTIONS,
                        $rawPerms['changelog_perms_allow'],
                        $rawPerms['changelog_perms_deny']
                    ),
                ],
            ],
        ],
    ];
}
