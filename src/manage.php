<?php
function manage_get_menu(int $userId): array
{
    $userPerms = perms_get_user(MSZ_PERMS_USER, $userId);

    if (!perms_check($userPerms, MSZ_PERM_MANAGE)) {
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

    $canUsers = perms_check($userPerms, MSZ_PERM_MANAGE_USERS);
    $canRoles = perms_check($userPerms, MSZ_PERM_MANAGE_ROLES);
    $canPerms = perms_check($userPerms, MSZ_PERM_MANAGE_PERMS);
    $canReports = perms_check($userPerms, MSZ_PERM_MANAGE_REPORTS);
    $canRestricts = perms_check($userPerms, MSZ_PERM_MANAGE_RESTRICTIONS);
    $canBlacklists = perms_check($userPerms, MSZ_PERM_MANAGE_BLACKLISTS);

    if ($canUsers || $canRoles || $canPerms
        || $canReports || $canRestricts || $canBlacklists) {
        $menu['Users'] = [];

        if ($canUsers) {
            $menu['Users']['Listing'] = '/manage/users.php?v=listing';
        }

        if ($canRoles || $canPerms) {
            $menu['Users'][] = '_';

            if ($canRoles) {
                $menu['Users']['Roles'] = '/manage/users.php?v=roles';
            }

            if ($canPerms) {
                $menu['Users']['Permissions'] = '/manage/users.php?v=permissions';
            }
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

    $canChanges = perms_check($changelogPerms, MSZ_CHANGELOG_MANAGE_CHANGES);
    $canChangeTags = perms_check($changelogPerms, MSZ_CHANGELOG_MANAGE_TAGS);
    $canChangeActions = perms_check($changelogPerms, MSZ_CHANGELOG_MANAGE_ACTIONS);

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
