<?php
function manage_get_menu(int $userId): array
{
    $userPerms = perms_get_user(MSZ_PERMS_USER, $userId);

    if (!perms_check($userPerms, MSZ_USER_PERM_CAN_MANAGE)) {
        return [];
    }

    $changelogPerms = perms_get_user(MSZ_PERMS_CHANGELOG, $userId);

    $menu = [];

    $menu['General'] = [
        'Overview' => '/manage/index.php?v=overview',
        'Logs' => '/manage/index.php?v=logs',
        '_',
        'Emoticons' => '/manage/index.php?v=emoticons',
        'Settings' => '/manage/index.php?v=settings',
    ];

    $canUsers = perms_check($userPerms, MSZ_USER_PERM_MANAGE_USERS);
    $canRoles = perms_check($userPerms, MSZ_USER_PERM_MANAGE_ROLES);
    $canPerms = perms_check($userPerms, MSZ_USER_PERM_MANAGE_PERMS);
    $canReports = perms_check($userPerms, MSZ_USER_PERM_MANAGE_REPORTS);
    $canRestricts = perms_check($userPerms, MSZ_USER_PERM_MANAGE_RESTRICTIONS);
    $canBlacklists = perms_check($userPerms, MSZ_USER_PERM_MANAGE_BLACKLISTS);

    if ($canUsers || $canRoles || $canPerms
        || $canReports || $canRestricts || $canBlacklists) {
        $menu['Users'] = [];

        if ($canUsers || $canPerms) {
            $menu['Users']['Listing'] = '/manage/users.php?v=listing';
        }

        if ($canRoles || $canPerms) {
            $menu['Users']['Roles'] = '/manage/users.php?v=roles';
        }

        if ($canReports || $canRestricts || $canBlacklists) {
            $menu['Users'][] = '_';

            if ($canReports) {
                $menu['Users']['Reports'] = '/manage/users.php?v=reports';
            }

            if ($canRestricts) {
                $menu['Users']['Restrictions'] = '/manage/users.php?v=restrictions';
            }

            if ($canBlacklists) {
                $menu['Users']['Blacklisting'] = '/manage/users.php?v=blacklisting';
            }
        }
    }

    /*$menu['Forum'] = [
        'Listing' => '/manage/forums.php?v=listing',
        'Permisisons' => '/manage/forums.php?v=permissions',
        'Settings' => '/manage/forums.php?v=settings',
    ];*/

    $canChanges = perms_check($changelogPerms, MSZ_CHANGELOG_PERM_MANAGE_CHANGES);
    $canChangeTags = perms_check($changelogPerms, MSZ_CHANGELOG_PERM_MANAGE_TAGS);
    $canChangeActions = perms_check($changelogPerms, MSZ_CHANGELOG_PERM_MANAGE_ACTIONS);

    if ($canChanges || $canChangeTags || $canChangeActions) {
        $menu['Changelog'] = [];

        if ($canChanges) {
            $menu['Changelog']['Changes'] = '/manage/changelog.php?v=changes';
        }

        if ($canChangeTags) {
            $menu['Changelog']['Tags'] = '/manage/changelog.php?v=tags';
        }

        if ($canChangeActions) {
            $menu['Changelog']['Actions'] = '/manage/changelog.php?v=actions';
        }
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
                    'section' => 'can-manage',
                    'title' => 'Can access the management panel.',
                    'perm' => MSZ_USER_PERM_CAN_MANAGE,
                    'value' => manage_perms_value(
                        MSZ_USER_PERM_CAN_MANAGE,
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
                [
                    'section' => 'comments-delete',
                    'title' => 'Can delete comments from others.',
                    'perm' => MSZ_NEWS_PERM_DELETE_COMMENTS,
                    'value' => manage_perms_value(
                        MSZ_NEWS_PERM_DELETE_COMMENTS,
                        $rawPerms['news_perms_allow'],
                        $rawPerms['news_perms_deny']
                    ),
                ],
                [
                    'section' => 'comments-edit',
                    'title' => 'Can edit comments from others.',
                    'perm' => MSZ_NEWS_PERM_EDIT_COMMENTS,
                    'value' => manage_perms_value(
                        MSZ_NEWS_PERM_EDIT_COMMENTS,
                        $rawPerms['news_perms_allow'],
                        $rawPerms['news_perms_deny']
                    ),
                ],
                [
                    'section' => 'comments-pin',
                    'title' => 'Can pin comments.',
                    'perm' => MSZ_NEWS_PERM_PIN_COMMENTS,
                    'value' => manage_perms_value(
                        MSZ_NEWS_PERM_PIN_COMMENTS,
                        $rawPerms['news_perms_allow'],
                        $rawPerms['news_perms_deny']
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
                [
                    'section' => 'comments-delete',
                    'title' => 'Can delete comments from others.',
                    'perm' => MSZ_CHANGELOG_PERM_DELETE_COMMENTS,
                    'value' => manage_perms_value(
                        MSZ_CHANGELOG_PERM_DELETE_COMMENTS,
                        $rawPerms['changelog_perms_allow'],
                        $rawPerms['changelog_perms_deny']
                    ),
                ],
                [
                    'section' => 'comments-edit',
                    'title' => 'Can edit comments from others.',
                    'perm' => MSZ_CHANGELOG_PERM_EDIT_COMMENTS,
                    'value' => manage_perms_value(
                        MSZ_CHANGELOG_PERM_EDIT_COMMENTS,
                        $rawPerms['changelog_perms_allow'],
                        $rawPerms['changelog_perms_deny']
                    ),
                ],
                [
                    'section' => 'comments-pin',
                    'title' => 'Can pin comments.',
                    'perm' => MSZ_CHANGELOG_PERM_PIN_COMMENTS,
                    'value' => manage_perms_value(
                        MSZ_CHANGELOG_PERM_PIN_COMMENTS,
                        $rawPerms['changelog_perms_allow'],
                        $rawPerms['changelog_perms_deny']
                    ),
                ],
            ],
        ],
    ];
}
