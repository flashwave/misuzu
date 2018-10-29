<?php
$mode = (string)($_GET['m'] ?? null);
$misuzuBypassLockdown = $mode === 'avatar';

require_once '../misuzu.php';

switch ($mode) {
    case 'avatar':
        $userId = (int)($_GET['u'] ?? 0);
        $avatar_filename = build_path(
            MSZ_ROOT,
            config_get_default('public/images/no-avatar.png', 'Avatar', 'default_path')
        );
        $user_avatar = "{$userId}.msz";
        $cropped_avatar = build_path(
            create_directory(build_path(MSZ_STORAGE, 'avatars/200x200')),
            $user_avatar
        );

        if (is_file($cropped_avatar)) {
            $avatar_filename = $cropped_avatar;
        } else {
            $original_avatar = build_path(MSZ_STORAGE, 'avatars/original', $user_avatar);

            if (is_file($original_avatar)) {
                try {
                    file_put_contents(
                        $cropped_avatar,
                        crop_image_centred_path($original_avatar, 200, 200)->getImagesBlob(),
                        LOCK_EX
                    );

                    $avatar_filename = $cropped_avatar;
                } catch (Exception $ex) {
                }
            }
        }

        header('Content-Type: ' . mime_content_type($avatar_filename));
        echo file_get_contents($avatar_filename);
        break;

    case 'background':
        $userId = (int)($_GET['u'] ?? 0);
        $user_background = build_path(
            create_directory(build_path(MSZ_STORAGE, 'backgrounds/original')),
            "{$userId}.msz"
        );

        if (!is_file($user_background)) {
            echo render_error(404);
            break;
        }

        header('Content-Type: ' . mime_content_type($user_background));
        echo file_get_contents($user_background);
        break;

    default:
        $getUserId = db_prepare('
            SELECT
                :user_id as `input_id`,
                (
                    SELECT `user_id`
                    FROM `msz_users`
                    WHERE `user_id` = `input_id`
                    OR LOWER(`username`) = LOWER(`input_id`)
                    LIMIT 1
                ) as `user_id`
        ');
        $getUserId->bindValue('user_id', $_GET['u'] ?? 0);
        $userId = $getUserId->execute() ? ($getUserId->fetch(PDO::FETCH_ASSOC)['user_id'] ?? 0) : 0;

        if ($userId < 1) {
            http_response_code(404);
            echo tpl_render('user.notfound');
            break;
        }

        $viewingOwnProfile = user_session_current('user_id', 0) === $userId;
        $userPerms = perms_get_user(MSZ_PERMS_USER, user_session_current('user_id', 0));
        $canEdit = user_session_active() && (
            $viewingOwnProfile || perms_check($userPerms, MSZ_PERM_USER_MANAGE_USERS)
        );
        $isEditing = $mode === 'edit';

        if (!$canEdit && $isEditing) {
            echo render_error(403);
            break;
        }

        $notices = [];

        if ($isEditing) {
            $perms = [
                'edit_profile' => perms_check($userPerms, MSZ_PERM_USER_EDIT_PROFILE),
                'edit_avatar' => perms_check($userPerms, MSZ_PERM_USER_CHANGE_AVATAR),
                'edit_background' => perms_check($userPerms, MSZ_PERM_USER_CHANGE_BACKGROUND),
                'edit_about' => perms_check($userPerms, MSZ_PERM_USER_EDIT_ABOUT),
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

                            if ($setAboutError !== MSZ_USER_ABOUT_OK) {
                                $notices[] = sprintf(
                                    MSZ_TMP_USER_ERROR_STRINGS['about'][$setAboutError] ?? MSZ_TMP_USER_ERROR_STRINGS['about']['_'],
                                    MSZ_USER_ABOUT_MAX_LENGTH
                                );
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
                    header("Location: /profile.php?u={$userId}");
                    return;
                }
            }
        } elseif ($viewingOwnProfile) {
            $notices[] = 'The profile pages are still under much construction, more things will eventually populate the area where this container current exists.';
        }

        $getProfile = db_prepare(
            sprintf(
                '
                    SELECT
                        u.`user_id`, u.`username`, u.`user_country`,
                        u.`user_created`, u.`user_active`,
                        u.`user_about_parser`, u.`user_about_content`, u.`user_background_settings`,
                        %1$s,
                        COALESCE(u.`user_title`, r.`role_title`) as `user_title`,
                        COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`,
                        `user_background_settings` & 0x0F as `user_background_attachment`,
                        (`user_background_settings` & %2$d) > 0 as `user_background_blend`,
                        (`user_background_settings` & %3$d) > 0 as `user_background_slide`,
                        (
                            SELECT COUNT(`topic_id`)
                            FROM `msz_forum_topics`
                            WHERE `user_id` = u.`user_id`
                        ) as `forum_topic_count`,
                        (
                            SELECT COUNT(`post_id`)
                            FROM `msz_forum_posts`
                            WHERE `user_id` = u.`user_id`
                        ) as `forum_post_count`,
                        (
                            SELECT COUNT(`change_id`)
                            FROM `msz_changelog_changes`
                            WHERE `user_id` = u.`user_id`
                        ) as `changelog_count`,
                        (
                            SELECT COUNT(`comment_id`)
                            FROM `msz_comments_posts`
                            WHERE `user_id` = u.`user_id`
                        ) as `comments_count`
                    FROM `msz_users` as u
                    LEFT JOIN `msz_roles` as r
                    ON r.`role_id` = u.`display_role`
                    WHERE `user_id` = :user_id
                    LIMIT 1
                ',
                pdo_prepare_array(user_profile_fields_get(), true, 'u.`user_%s`'),
                MSZ_USER_BACKGROUND_ATTRIBUTE_BLEND,
                MSZ_USER_BACKGROUND_ATTRIBUTE_SLIDE
            )
        );
        $getProfile->bindValue('user_id', $userId);
        $profile = $getProfile->execute() ? $getProfile->fetch(PDO::FETCH_ASSOC) : [];

        $backgroundPath = build_path(MSZ_STORAGE, 'backgrounds/original', "{$profile['user_id']}.msz");

        if (is_file($backgroundPath)) {
            $backgroundInfo = getimagesize($backgroundPath);

            if ($backgroundInfo) {
                tpl_var('site_background', [
                    'url' => "/profile.php?m=background&u={$userId}",
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
            'profile_fields' => user_session_active() ? user_profile_fields_display($profile, !$isEditing) : [],
            'friend_info' => user_session_active() ? user_relation_info(user_session_current('user_id', 0), $profile['user_id']) : [],
        ]);
        break;
}
