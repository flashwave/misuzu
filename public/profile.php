<?php
namespace Misuzu;

require_once '../misuzu.php';

$userId = !empty($_GET['u']) && is_string($_GET['u']) ? (int)$_GET['u'] : 0;
$profileMode = !empty($_GET['m']) && is_string($_GET['m']) ? (string)$_GET['m'] : '';
$isEditing = !empty($_GET['edit']) && is_string($_GET['edit']) ? (bool)$_GET['edit'] : !empty($_POST) && is_array($_POST);

$userId = user_find_for_profile($userId);

if($userId < 1) {
    http_response_code(404);
    echo tpl_render('profile.index');
    return;
}

$notices = [];

$currentUserId = user_session_current('user_id', 0);
$viewingAsGuest = $currentUserId === 0;
$viewingOwnProfile = $currentUserId === $userId;

$isBanned = user_warning_check_restriction($userId);
$userPerms = perms_get_user($currentUserId)[MSZ_PERMS_USER];
$canManageWarnings = perms_check($userPerms, MSZ_PERM_USER_MANAGE_WARNINGS);
$canEdit = !$isBanned
    && user_session_active()
    && (
        $viewingOwnProfile
        || user_check_super($currentUserId)
        || (
            perms_check($userPerms, MSZ_PERM_USER_MANAGE_USERS)
            && user_check_authority($currentUserId, $userId)
        )
    );

if($isEditing) {
    if(!$canEdit) {
        echo render_error(403);
        return;
    }

    $perms = perms_check_bulk($userPerms, [
        'edit_profile' => MSZ_PERM_USER_EDIT_PROFILE,
        'edit_avatar' => MSZ_PERM_USER_CHANGE_AVATAR,
        'edit_background' => MSZ_PERM_USER_CHANGE_BACKGROUND,
        'edit_about' => MSZ_PERM_USER_EDIT_ABOUT,
        'edit_birthdate' => MSZ_PERM_USER_EDIT_BIRTHDATE,
        'edit_signature' => MSZ_PERM_USER_EDIT_SIGNATURE,
    ]);

    tpl_vars([
        'perms' => $perms,
        'guidelines' => [
            'avatar' => $avatarProps = user_avatar_default_options(),
            'background' => $backgroundProps = user_background_default_options(),
        ],
        'background_attachments' => MSZ_USER_BACKGROUND_ATTACHMENTS_NAMES,
    ]);

    if(!empty($_POST) && is_array($_POST)) {
        if(!csrf_verify_request()) {
            $notices[] = MSZ_TMP_USER_ERROR_STRINGS['csrf'];
        } else {
            if(!empty($_POST['profile']) && is_array($_POST['profile'])) {
                if(!$perms['edit_profile']) {
                    $notices[] = MSZ_TMP_USER_ERROR_STRINGS['profile']['not-allowed'];
                } else {
                    $setUserFieldErrors = user_profile_fields_set($userId, $_POST['profile']);

                    if(count($setUserFieldErrors) > 0) {
                        foreach($setUserFieldErrors as $name => $error) {
                            $notices[] = sprintf(
                                MSZ_TMP_USER_ERROR_STRINGS['profile'][$error] ?? MSZ_TMP_USER_ERROR_STRINGS['profile']['_'],
                                $name,
                                user_profile_field_get_display_name($name)
                            );
                        }
                    }
                }
            }

            if(!empty($_POST['about']) && is_array($_POST['about'])) {
                if(!$perms['edit_about']) {
                    $notices[] = MSZ_TMP_USER_ERROR_STRINGS['about']['not-allowed'];
                } else {
                    $setAboutError = user_set_about_page(
                        $userId,
                        $_POST['about']['text'] ?? '',
                        (int)($_POST['about']['parser'] ?? MSZ_PARSER_PLAIN)
                    );

                    if($setAboutError !== MSZ_E_USER_ABOUT_OK) {
                        $notices[] = sprintf(
                            MSZ_TMP_USER_ERROR_STRINGS['about'][$setAboutError] ?? MSZ_TMP_USER_ERROR_STRINGS['about']['_'],
                            MSZ_USER_ABOUT_MAX_LENGTH
                        );
                    }
                }
            }

            if(!empty($_POST['signature']) && is_array($_POST['signature'])) {
                if(!$perms['edit_signature']) {
                    $notices[] = MSZ_TMP_USER_ERROR_STRINGS['signature']['not-allowed'];
                } else {
                    $setSignatureError = user_set_signature(
                        $userId,
                        $_POST['signature']['text'] ?? '',
                        (int)($_POST['signature']['parser'] ?? MSZ_PARSER_PLAIN)
                    );

                    if($setSignatureError !== MSZ_E_USER_SIGNATURE_OK) {
                        $notices[] = sprintf(
                            MSZ_TMP_USER_ERROR_STRINGS['signature'][$setSignatureError] ?? MSZ_TMP_USER_ERROR_STRINGS['signature']['_'],
                            MSZ_USER_SIGNATURE_MAX_LENGTH
                        );
                    }
                }
            }

            if(!empty($_POST['birthdate']) && is_array($_POST['birthdate'])) {
                if(!$perms['edit_birthdate']) {
                    $notices[] = "You aren't allow to change your birthdate.";
                } else {
                    $setBirthdate = user_set_birthdate(
                        $userId,
                        (int)($_POST['birthdate']['day'] ?? 0),
                        (int)($_POST['birthdate']['month'] ?? 0),
                        (int)($_POST['birthdate']['year'] ?? 0)
                    );

                    switch($setBirthdate) {
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

            if(!empty($_FILES['avatar'])) {
                if(!empty($_POST['avatar']['delete'])) {
                    user_avatar_delete($userId);
                } else {
                    if(!$perms['edit_avatar']) {
                        $notices[] = MSZ_TMP_USER_ERROR_STRINGS['avatar']['not-allowed'];
                    } elseif(!empty($_FILES['avatar'])
                        && is_array($_FILES['avatar'])
                        && !empty($_FILES['avatar']['name']['file'])) {
                        if($_FILES['avatar']['error']['file'] !== UPLOAD_ERR_OK) {
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

                            if($setAvatar !== MSZ_USER_AVATAR_NO_ERRORS) {
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

            if(!empty($_FILES['background'])) {
                if((int)($_POST['background']['attach'] ?? -1) === 0) {
                    user_background_delete($userId);
                    user_background_set_settings($userId, MSZ_USER_BACKGROUND_ATTACHMENT_NONE);
                } else {
                    if(!$perms['edit_background']) {
                        $notices[] = MSZ_TMP_USER_ERROR_STRINGS['background']['not-allowed'];
                    } elseif(!empty($_FILES['background'])
                        && is_array($_FILES['background'])) {
                        if(!empty($_FILES['background']['name']['file'])) {
                            if($_FILES['background']['error']['file'] !== UPLOAD_ERR_OK) {
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

                                if($setBackground !== MSZ_USER_BACKGROUND_NO_ERRORS) {
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

                        if(!empty($_POST['background']['attr']['blend'])) {
                            $backgroundSettings |= MSZ_USER_BACKGROUND_ATTRIBUTE_BLEND;
                        }

                        if(!empty($_POST['background']['attr']['slide'])) {
                            $backgroundSettings |= MSZ_USER_BACKGROUND_ATTRIBUTE_SLIDE;
                        }

                        user_background_set_settings($userId, $backgroundSettings);
                    }
                }
            }
        }

        // Unset $isEditing and hope the user doesn't refresh their profile!
        if(empty($notices)) {
            $isEditing = false;
        }
    }
}

$profile = user_profile_get($userId);
$relationInfo = user_session_active()
    ? user_relation_info($currentUserId, $profile['user_id'])
    : [];

$backgroundPath = sprintf('%s/backgrounds/original/%d.msz', MSZ_STORAGE, $profile['user_id']);

if(is_file($backgroundPath)) {
    $backgroundInfo = getimagesize($backgroundPath);

    if($backgroundInfo) {
        tpl_var('site_background', [
            'url' => url('user-background', ['user' => $profile['user_id']]),
            'width' => $backgroundInfo[0],
            'height' => $backgroundInfo[1],
            'settings' => $profile['user_background_settings'],
        ]);
    }
}

switch($profileMode) {
    default:
        echo render_error(404);
        return;

    case 'following':
        $template = 'profile.relations';
        $followingCount = user_relation_count_from($userId, MSZ_USER_RELATION_FOLLOW);
        $followingPagination = pagination_create($followingCount, MSZ_USER_RELATION_FOLLOW_PER_PAGE);
        $followingOffset = pagination_offset($followingPagination, pagination_param());

        if(!pagination_is_valid_offset($followingOffset)) {
            echo render_error(404);
            return;
        }

        $following = user_relation_users_from($userId, MSZ_USER_RELATION_FOLLOW, $followingPagination['range'], $followingOffset, $currentUserId);

        tpl_vars([
            'title' => $profile['username'] . ' / following',
            'canonical_url' => url('user-profile-following', ['user' => $userId]),
            'profile_users' => $following,
            'profile_relation_pagination' => $followingPagination,
        ]);
        break;

    case 'followers':
        $template = 'profile.relations';
        $followerCount = user_relation_count_to($userId, MSZ_USER_RELATION_FOLLOW);
        $followerPagination = pagination_create($followerCount, MSZ_USER_RELATION_FOLLOW_PER_PAGE);
        $followerOffset = pagination_offset($followerPagination, pagination_param());

        if(!pagination_is_valid_offset($followerOffset)) {
            echo render_error(404);
            return;
        }

        $followers = user_relation_users_to($userId, MSZ_USER_RELATION_FOLLOW, $followerPagination['range'], $followerOffset, $currentUserId);

        tpl_vars([
            'title' => $profile['username'] . ' / followers',
            'canonical_url' => url('user-profile-followers', ['user' => $userId]),
            'profile_users' => $followers,
            'profile_relation_pagination' => $followerPagination,
        ]);
        break;

    case 'forum-topics':
        $template = 'profile.topics';
        $topicsCount = forum_topic_count_user($userId, $currentUserId);
        $topicsPagination = pagination_create($topicsCount, 20);
        $topicsOffset = pagination_offset($topicsPagination, pagination_param());

        if(!pagination_is_valid_offset($topicsOffset)) {
            echo render_error(404);
            return;
        }

        $topics = forum_topic_listing_user($userId, $currentUserId, $topicsOffset, $topicsPagination['range']);

        tpl_vars([
            'title' => $profile['username'] . ' / topics',
            'canonical_url' => url('user-profile-forum-topics', ['user' => $userId, 'page' => pagination_param()]),
            'profile_topics' => $topics,
            'profile_topics_pagination' => $topicsPagination,
        ]);
        break;

    case 'forum-posts':
        $template = 'profile.posts';
        $postsCount = forum_post_count_user($userId);
        $postsPagination = pagination_create($postsCount, 20);
        $postsOffset = pagination_offset($postsPagination, pagination_param());

        if(!pagination_is_valid_offset($postsOffset)) {
            echo render_error(404);
            return;
        }

        $posts = forum_post_listing($userId, $postsOffset, $postsPagination['range'], false, true);

        tpl_vars([
            'title' => $profile['username'] . ' / posts',
            'canonical_url' => url('user-profile-forum-posts', ['user' => $userId, 'page' => pagination_param()]),
            'profile_posts' => $posts,
            'profile_posts_pagination' => $postsPagination,
        ]);
        break;

    case '':
        $template = 'profile.index';
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

        tpl_vars([
            'profile_warnings' => $warnings,
            'profile_warnings_view_private' => $viewingOwnProfile,
            'profile_warnings_can_manage' => $canManageWarnings,
            'profile_fields' => user_session_active() ? user_profile_fields_display($profile, !$isEditing) : [],
        ]);
        break;
}

if(!empty($template)) {
    echo tpl_render($template, [
        'profile' => $profile,
        'profile_mode' => $profileMode,
        'profile_notices' => $notices,
        'profile_can_edit' => $canEdit,
        'profile_is_editing' => $isEditing,
        'profile_is_banned' => $isBanned,
        'profile_relation_info' => $relationInfo,
    ]);
}
