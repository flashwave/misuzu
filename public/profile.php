<?php
$mode = (string)($_GET['m'] ?? null);
$misuzuBypassLockdown = $mode === 'avatar';

require_once '../misuzu.php';

switch ($mode) {
    case 'following':
        $userId = (int)($_GET['u'] ?? 0);

        if (!user_exists($userId)) {
            http_response_code(404);
            echo tpl_render('user.notfound');
            break;
        }

        $followingIds = user_relation_users_from($userId, MSZ_USER_RELATION_FOLLOW);

        foreach ($followingIds as $user) {
            echo "{$user['user_id']}|{$user['relation_created']}<br>";
        }
        break;

    case 'followers':
        $userId = (int)($_GET['u'] ?? 0);

        if (!user_exists($userId)) {
            http_response_code(404);
            echo tpl_render('user.notfound');
            break;
        }

        $followerIds = user_relation_users_to($userId, MSZ_USER_RELATION_FOLLOW);

        foreach ($followerIds as $user) {
            echo "{$user['user_id']}|{$user['relation_created']}<br>";
        }
        break;

    default:
        $userId = user_find_for_profile($_GET['u'] ?? 0);

        if ($userId < 1) {
            http_response_code(404);
            echo tpl_render('user.notfound');
            break;
        }

        $viewingAsGuest = user_session_current('user_id', 0) === 0;
        $viewingOwnProfile = user_session_current('user_id', 0) === $userId;
        $isRestricted = user_warning_check_restriction($userId);
        $userPerms = perms_get_user(MSZ_PERMS_USER, user_session_current('user_id', 0));
        $canManageWarnings = perms_check($userPerms, MSZ_PERM_USER_MANAGE_WARNINGS);
        $canEdit = !$isRestricted && user_session_active() && (
            $viewingOwnProfile || user_check_super($userId) || (perms_check($userPerms, MSZ_PERM_USER_MANAGE_USERS) && user_check_authority(user_session_current('user_id', 0), $userId))
        );
        $isEditing = $mode === 'edit';

        if (!$canEdit && $isEditing) {
            echo render_error(403);
            break;
        }

        $warnings = $viewingAsGuest
            ? []
            : user_warning_fetch(
                $userId,
                90,
                $canManageWarnings
                    ? MSZ_WARN_TYPES_VISIBLE_TO_STAFF
                    : (
                        $viewingOwnProfile
                            ? MSZ_WARN_TYPES_VISIBLE_TO_USER
                            : MSZ_WARN_TYPES_VISIBLE_TO_PUBLIC
                    )
            );
        $notices = [];

        if ($isEditing) {
            $perms = [
                'edit_profile' => perms_check($userPerms, MSZ_PERM_USER_EDIT_PROFILE),
                'edit_avatar' => perms_check($userPerms, MSZ_PERM_USER_CHANGE_AVATAR),
                'edit_background' => perms_check($userPerms, MSZ_PERM_USER_CHANGE_BACKGROUND),
                'edit_about' => perms_check($userPerms, MSZ_PERM_USER_EDIT_ABOUT),
                'edit_birthdate' => perms_check($userPerms, MSZ_PERM_USER_EDIT_BIRTHDATE),
                'edit_signature' => perms_check($userPerms, MSZ_PERM_USER_EDIT_SIGNATURE),
            ];

            tpl_vars([
                'perms' => $perms,
                'guidelines' => [
                    'avatar' => $avatarProps = user_avatar_default_options(),
                    'background' => $backgroundProps = user_background_default_options(),
                ],
                'background_attachments' => MSZ_USER_BACKGROUND_ATTACHMENTS_NAMES,
            ]);

            if (!empty($_POST)) {
                if (!csrf_verify('profile', $_POST['csrf'] ?? '')) {
                    $notices[] = MSZ_TMP_USER_ERROR_STRINGS['csrf'];
                } else {
                    if (!empty($_POST['profile']) && is_array($_POST['profile'])) {
                        if (!$perms['edit_profile']) {
                            $notices[] = MSZ_TMP_USER_ERROR_STRINGS['profile']['not-allowed'];
                        } else {
                            $setUserFieldErrors = user_profile_fields_set($userId, $_POST['profile']);

                            if (count($setUserFieldErrors) > 0) {
                                foreach ($setUserFieldErrors as $name => $error) {
                                    $notices[] = sprintf(
                                        MSZ_TMP_USER_ERROR_STRINGS['profile'][$error] ?? MSZ_TMP_USER_ERROR_STRINGS['profile']['_'],
                                        $name,
                                        user_profile_field_get_display_name($name)
                                    );
                                }
                            }
                        }
                    }

                    if (!empty($_POST['about']) && is_array($_POST['about'])) {
                        if (!$perms['edit_about']) {
                            $notices[] = MSZ_TMP_USER_ERROR_STRINGS['about']['not-allowed'];
                        } else {
                            $setAboutError = user_set_about_page(
                                $userId,
                                $_POST['about']['text'] ?? '',
                                (int)($_POST['about']['parser'] ?? MSZ_PARSER_PLAIN)
                            );

                            if ($setAboutError !== MSZ_E_USER_ABOUT_OK) {
                                $notices[] = sprintf(
                                    MSZ_TMP_USER_ERROR_STRINGS['about'][$setAboutError] ?? MSZ_TMP_USER_ERROR_STRINGS['about']['_'],
                                    MSZ_USER_ABOUT_MAX_LENGTH
                                );
                            }
                        }
                    }

                    if (!empty($_POST['signature']) && is_array($_POST['signature'])) {
                        if (!$perms['edit_signature']) {
                            $notices[] = MSZ_TMP_USER_ERROR_STRINGS['signature']['not-allowed'];
                        } else {
                            $setSignatureError = user_set_signature(
                                $userId,
                                $_POST['signature']['text'] ?? '',
                                (int)($_POST['signature']['parser'] ?? MSZ_PARSER_PLAIN)
                            );

                            if ($setSignatureError !== MSZ_E_USER_SIGNATURE_OK) {
                                $notices[] = sprintf(
                                    MSZ_TMP_USER_ERROR_STRINGS['signature'][$setSignatureError] ?? MSZ_TMP_USER_ERROR_STRINGS['signature']['_'],
                                    MSZ_USER_SIGNATURE_MAX_LENGTH
                                );
                            }
                        }
                    }

                    if (!empty($_POST['birthdate']) && is_array($_POST['birthdate'])) {
                        if (!$perms['edit_birthdate']) {
                            $notices[] = "You aren't allow to change your birthdate.";
                        } else {
                            $setBirthdate = user_set_birthdate(
                                $userId,
                                (int)($_POST['birthdate']['day'] ?? 0),
                                (int)($_POST['birthdate']['month'] ?? 0),
                                (int)($_POST['birthdate']['year'] ?? 0)
                            );

                            switch ($setBirthdate) {
                                case MSZ_E_USER_BIRTHDATE_USER:
                                    $notices[] = 'Invalid user specified while setting birthdate?';
                                    break;
                                case MSZ_E_USER_BIRTHDATE_DATE:
                                    $notices[] = 'The given birthdate is invalid.';
                                    break;
                                case MSZ_E_USER_BIRTHDATE_FAIL:
                                    $notices[] = 'Failed to set birthdate.';
                                    break;
                                case MSZ_E_USER_BIRTHDATE_YEAR:
                                    $notices[] = 'The given birth year is invalid.';
                                    break;
                                case MSZ_E_USER_BIRTHDATE_OK:
                                    break;
                                default:
                                    $notices[] = 'Something unexpected happened while setting your birthdate.';
                            }
                        }
                    }

                    if (!empty($_FILES['avatar'])) {
                        if (!empty($_POST['avatar']['delete'])) {
                            user_avatar_delete($userId);
                        } else {
                            if (!$perms['edit_avatar']) {
                                $notices[] = MSZ_TMP_USER_ERROR_STRINGS['avatar']['not-allowed'];
                            } elseif (!empty($_FILES['avatar'])
                                && is_array($_FILES['avatar'])
                                && !empty($_FILES['avatar']['name']['file'])) {
                                if ($_FILES['avatar']['error']['file'] !== UPLOAD_ERR_OK) {
                                    $notices[] = sprintf(
                                        MSZ_TMP_USER_ERROR_STRINGS['avatar']['upload'][$_FILES['avatar']['error']['file']]
                                        ?? MSZ_TMP_USER_ERROR_STRINGS['avatar']['upload']['_'],
                                        $_FILES['avatar']['error']['file'],
                                        byte_symbol($avatarProps['max_size'], true),
                                        $avatarProps['max_width'],
                                        $avatarProps['max_height']
                                    );
                                } else {
                                    $setAvatar = user_avatar_set_from_path(
                                        $userId,
                                        $_FILES['avatar']['tmp_name']['file'],
                                        $avatarProps
                                    );

                                    if ($setAvatar !== MSZ_USER_AVATAR_NO_ERRORS) {
                                        $notices[] = sprintf(
                                            MSZ_TMP_USER_ERROR_STRINGS['avatar']['set'][$setAvatar]
                                            ?? MSZ_TMP_USER_ERROR_STRINGS['avatar']['set']['_'],
                                            $setAvatar,
                                            byte_symbol($avatarProps['max_size'], true),
                                            $avatarProps['max_width'],
                                            $avatarProps['max_height']
                                        );
                                    }
                                }
                            }
                        }
                    }

                    if (!empty($_FILES['background'])) {
                        if ((int)($_POST['background']['attach'] ?? -1) === 0) {
                            user_background_delete($userId);
                            user_background_set_settings($userId, MSZ_USER_BACKGROUND_ATTACHMENT_NONE);
                        } else {
                            if (!$perms['edit_background']) {
                                $notices[] = MSZ_TMP_USER_ERROR_STRINGS['background']['not-allowed'];
                            } elseif (!empty($_FILES['background'])
                                && is_array($_FILES['background'])) {
                                if (!empty($_FILES['background']['name']['file'])) {
                                    if ($_FILES['background']['error']['file'] !== UPLOAD_ERR_OK) {
                                        $notices[] = sprintf(
                                            MSZ_TMP_USER_ERROR_STRINGS['background']['upload'][$_FILES['background']['error']['file']]
                                            ?? MSZ_TMP_USER_ERROR_STRINGS['background']['upload']['_'],
                                            $_FILES['background']['error']['file'],
                                            byte_symbol($backgroundProps['max_size'], true),
                                            $backgroundProps['max_width'],
                                            $backgroundProps['max_height']
                                        );
                                    } else {
                                        $setBackground = user_background_set_from_path(
                                            $userId,
                                            $_FILES['background']['tmp_name']['file'],
                                            $backgroundProps
                                        );

                                        if ($setBackground !== MSZ_USER_BACKGROUND_NO_ERRORS) {
                                            $notices[] = sprintf(
                                                MSZ_TMP_USER_ERROR_STRINGS['background']['set'][$setBackground]
                                                ?? MSZ_TMP_USER_ERROR_STRINGS['background']['set']['_'],
                                                $setBackground,
                                                byte_symbol($backgroundProps['max_size'], true),
                                                $backgroundProps['max_width'],
                                                $backgroundProps['max_height']
                                            );
                                        }
                                    }
                                }

                                $backgroundSettings = in_array($_POST['background']['attach'] ?? '', MSZ_USER_BACKGROUND_ATTACHMENTS)
                                    ? (int)($_POST['background']['attach'])
                                    : MSZ_USER_BACKGROUND_ATTACHMENTS[0];

                                if (!empty($_POST['background']['attr']['blend'])) {
                                    $backgroundSettings |= MSZ_USER_BACKGROUND_ATTRIBUTE_BLEND;
                                }

                                if (!empty($_POST['background']['attr']['slide'])) {
                                    $backgroundSettings |= MSZ_USER_BACKGROUND_ATTRIBUTE_SLIDE;
                                }

                                user_background_set_settings($userId, $backgroundSettings);
                            }
                        }
                    }
                }

                // If there are no notices, redirect to regular profile.
                if (empty($notices)) {
                    header('Location: ' . url('user-profile', ['user' => $userId]));
                    return;
                }
            }
        }

        $profile = user_profile_get($userId);
        $backgroundPath = MSZ_STORAGE . "/backgrounds/original/{$profile['user_id']}.msz";

        if (is_file($backgroundPath)) {
            $backgroundInfo = getimagesize($backgroundPath);

            if ($backgroundInfo) {
                tpl_var('site_background', [
                    'url' => url('user-background', ['user' => $userId]),
                    'width' => $backgroundInfo[0],
                    'height' => $backgroundInfo[1],
                    'settings' => $profile['user_background_settings'],
                ]);
            }
        }

        echo tpl_render('user.profile', [
            'profile' => $profile,
            'profile_notices' => $notices,
            'can_edit' => $canEdit,
            'is_editing' => $isEditing,
            'warnings' => $warnings,
            'can_view_private_note' => $viewingOwnProfile,
            'can_manage_warnings' => $canManageWarnings,
            'is_restricted' => $isRestricted,
            'profile_fields' => user_session_active() ? user_profile_fields_display($profile, !$isEditing) : [],
            'friend_info' => user_session_active() ? user_relation_info(user_session_current('user_id', 0), $profile['user_id']) : [],
        ]);
        break;
}
