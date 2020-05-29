<?php
namespace Misuzu;

use Misuzu\Parsers\Parser;
use Misuzu\Users\User;
use Misuzu\Users\UserNotFoundException;
use Misuzu\Users\UserSession;

require_once '../misuzu.php';

$userId = !empty($_GET['u']) && is_string($_GET['u']) ? $_GET['u'] : 0;
$profileMode = !empty($_GET['m']) && is_string($_GET['m']) ? (string)$_GET['m'] : '';
$isEditing = !empty($_GET['edit']) && is_string($_GET['edit']) ? (bool)$_GET['edit'] : !empty($_POST) && is_array($_POST);

try {
    $profileUser = User::findForProfile($userId);
} catch(UserNotFoundException $ex) {
    http_response_code(404);
    Template::render('profile.index');
    return;
}

$notices = [];

$currentUser = User::getCurrent();
$viewingAsGuest = $currentUser === null;
$currentUserId = $viewingAsGuest ? 0 : $currentUser->getId();
$viewingOwnProfile = $currentUserId === $profileUser->getId();

$isBanned = user_warning_check_restriction($profileUser->getId());
$userPerms = perms_get_user($currentUserId)[MSZ_PERMS_USER];
$canManageWarnings = perms_check($userPerms, MSZ_PERM_USER_MANAGE_WARNINGS);
$canEdit = !$isBanned
    && UserSession::hasCurrent()
    && (
        $viewingOwnProfile
        || user_check_super($currentUserId)
        || (
            perms_check($userPerms, MSZ_PERM_USER_MANAGE_USERS)
            && user_check_authority($currentUserId, $profileUser->getId())
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

    Template::set([
        'perms' => $perms,
        'guidelines' => [
            'avatar' => $avatarProps = user_avatar_default_options(),
            'background' => $backgroundProps = user_background_default_options(),
        ],
        'background_attachments' => MSZ_USER_BACKGROUND_ATTACHMENTS_NAMES,
    ]);

    if(!empty($_POST) && is_array($_POST)) {
        if(!CSRF::validateRequest()) {
            $notices[] = MSZ_TMP_USER_ERROR_STRINGS['csrf'];
        } else {
            if(!empty($_POST['profile']) && is_array($_POST['profile'])) {
                if(!$perms['edit_profile']) {
                    $notices[] = MSZ_TMP_USER_ERROR_STRINGS['profile']['not-allowed'];
                } else {
                    $profileFields = $profileUser->profileFields(false);

                    foreach($profileFields as $profileField) {
                        if(isset($_POST['profile'][$profileField->field_key])
                            && $profileField->field_value !== $_POST['profile'][$profileField->field_key]
                            && !$profileField->setFieldValue($_POST['profile'][$profileField->field_key])) {
                            $notices[] = sprintf(MSZ_TMP_USER_ERROR_STRINGS['profile']['invalid'], $profileField->field_title);
                        }
                    }
                }
            }

            if(!empty($_POST['about']) && is_array($_POST['about'])) {
                if(!$perms['edit_about']) {
                    $notices[] = MSZ_TMP_USER_ERROR_STRINGS['about']['not-allowed'];
                } else {
                    $setAboutError = user_set_about_page(
                        $profileUser->getId(),
                        $_POST['about']['text'] ?? '',
                        (int)($_POST['about']['parser'] ?? Parser::PLAIN)
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
                        $profileUser->getId(),
                        $_POST['signature']['text'] ?? '',
                        (int)($_POST['signature']['parser'] ?? Parser::PLAIN)
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
                        $profileUser->getId(),
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
                    user_avatar_delete($profileUser->getId());
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
                                $profileUser->getId(),
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
                    user_background_delete($profileUser->getId());
                    user_background_set_settings($profileUser->getId(), MSZ_USER_BACKGROUND_ATTACHMENT_NONE);
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
                                    $profileUser->getId(),
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

                        user_background_set_settings($profileUser->getId(), $backgroundSettings);
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

$profileStats = DB::prepare(sprintf('
    SELECT (
        SELECT COUNT(`topic_id`)
        FROM `msz_forum_topics`
        WHERE `user_id` = u.`user_id`
        AND `topic_deleted` IS NULL
    ) AS `forum_topic_count`,
    (
        SELECT COUNT(`post_id`)
        FROM `msz_forum_posts`
        WHERE `user_id` = u.`user_id`
        AND `post_deleted` IS NULL
    ) AS `forum_post_count`,
    (
        SELECT COUNT(`change_id`)
        FROM `msz_changelog_changes`
        WHERE `user_id` = u.`user_id`
    ) AS `changelog_count`,
    (
        SELECT COUNT(`comment_id`)
        FROM `msz_comments_posts`
        WHERE `user_id` = u.`user_id`
        AND `comment_deleted` IS NULL
    ) AS `comments_count`,
    (
        SELECT COUNT(`user_id`)
        FROM `msz_user_relations`
        WHERE `subject_id` = u.`user_id`
        AND `relation_type` = %1$d
    ) AS `followers_count`,
    (
        SELECT COUNT(`subject_id`)
        FROM `msz_user_relations`
        WHERE `user_id` = u.`user_id`
        AND `relation_type` = %1$d
    ) AS `following_count`
    FROM `msz_users` AS u
    WHERE `user_id` = :user_id
', \Misuzu\Users\UserRelation::TYPE_FOLLOW))->bind('user_id', $profileUser->getId())->fetch();

$backgroundPath = sprintf('%s/backgrounds/original/%d.msz', MSZ_STORAGE, $profileUser->getId());

if(is_file($backgroundPath)) {
    $backgroundInfo = getimagesize($backgroundPath);

    if($backgroundInfo) {
        Template::set('site_background', [
            'url' => url('user-background', ['user' => $profileUser->getId()]),
            'width' => $backgroundInfo[0],
            'height' => $backgroundInfo[1],
            'settings' => $profileUser->getBackgroundSettings(),
        ]);
    }
}

switch($profileMode) {
    default:
        echo render_error(404);
        return;

    case 'following':
        $template = 'profile.relations';
        $pagination = new Pagination($profileUser->getFollowingCount(), 15);

        if(!$pagination->hasValidOffset()) {
            echo render_error(404);
            return;
        }

        Template::set([
            'title' => $profileUser->getUsername() . ' / following',
            'canonical_url' => url('user-profile-following', ['user' => $profileUser->getId()]),
            'profile_users' => $profileUser->getFollowing($pagination),
            'profile_relation_pagination' => $pagination,
            'relation_prop' => 'subject',
        ]);
        break;

    case 'followers':
        $template = 'profile.relations';
        $pagination = new Pagination($profileUser->getFollowersCount(), 15);

        if(!$pagination->hasValidOffset()) {
            echo render_error(404);
            return;
        }

        Template::set([
            'title' => $profileUser->getUsername() . ' / followers',
            'canonical_url' => url('user-profile-followers', ['user' => $profileUser->getId()]),
            'profile_users' => $profileUser->getFollowers($pagination),
            'profile_relation_pagination' => $pagination,
            'relation_prop' => 'user',
        ]);
        break;

    case 'forum-topics':
        $template = 'profile.topics';
        $topicsCount = forum_topic_count_user($profileUser->getId(), $currentUserId);
        $topicsPagination = new Pagination($topicsCount, 20);

        if(!$topicsPagination->hasValidOffset()) {
            echo render_error(404);
            return;
        }

        $topics = forum_topic_listing_user(
            $profileUser->getId(), $currentUserId,
            $topicsPagination->getOffset(), $topicsPagination->getRange()
        );

        Template::set([
            'title' => $profileUser->getUsername() . ' / topics',
            'canonical_url' => url('user-profile-forum-topics', ['user' => $profileUser->getId(), 'page' => Pagination::param()]),
            'profile_topics' => $topics,
            'profile_topics_pagination' => $topicsPagination,
        ]);
        break;

    case 'forum-posts':
        $template = 'profile.posts';
        $postsCount = forum_post_count_user($profileUser->getId());
        $postsPagination = new Pagination($postsCount, 20);

        if(!$postsPagination->hasValidOffset()) {
            echo render_error(404);
            return;
        }

        $posts = forum_post_listing(
            $profileUser->getId(),
            $postsPagination->getOffset(),
            $postsPagination->getRange(),
            false,
            true
        );

        Template::set([
            'title' => $profileUser->getUsername() . ' / posts',
            'canonical_url' => url('user-profile-forum-posts', ['user' => $profileUser->getId(), 'page' => Pagination::param()]),
            'profile_posts' => $posts,
            'profile_posts_pagination' => $postsPagination,
        ]);
        break;

    case '':
        $template = 'profile.index';
        $warnings = $viewingAsGuest
            ? []
            : user_warning_fetch(
                $profileUser->getId(),
                90,
                $canManageWarnings
                    ? MSZ_WARN_TYPES_VISIBLE_TO_STAFF
                    : (
                        $viewingOwnProfile
                            ? MSZ_WARN_TYPES_VISIBLE_TO_USER
                            : MSZ_WARN_TYPES_VISIBLE_TO_PUBLIC
                    )
            );

        Template::set([
            'profile_warnings' => $warnings,
            'profile_warnings_view_private' => $viewingOwnProfile,
            'profile_warnings_can_manage' => $canManageWarnings,
        ]);
        break;
}

if(!empty($template)) {
    Template::render($template, [
        'profile_viewer' => $currentUser,
        'profile_user' => $profileUser,
        'profile_stats' => $profileStats,
        'profile_mode' => $profileMode,
        'profile_notices' => $notices,
        'profile_can_edit' => $canEdit,
        'profile_is_editing' => $isEditing,
        'profile_is_banned' => $isBanned,
    ]);
}
