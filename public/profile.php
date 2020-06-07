<?php
namespace Misuzu;

use InvalidArgumentException;
use Misuzu\Parsers\Parser;
use Misuzu\Users\User;
use Misuzu\Users\UserNotFoundException;
use Misuzu\Users\UserSession;
use Misuzu\Users\Assets\UserBackgroundAsset;
use Misuzu\Users\Assets\UserImageAssetException;
use Misuzu\Users\Assets\UserImageAssetInvalidImageException;
use Misuzu\Users\Assets\UserImageAssetInvalidTypeException;
use Misuzu\Users\Assets\UserImageAssetInvalidDimensionsException;
use Misuzu\Users\Assets\UserImageAssetFileTooLargeException;

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
$isBanned = $profileUser->hasActiveWarning();
$userPerms = perms_get_user($currentUserId)[MSZ_PERMS_USER];
$canManageWarnings = perms_check($userPerms, MSZ_PERM_USER_MANAGE_WARNINGS);
$canEdit = !$isBanned
    && UserSession::hasCurrent()
    && ($viewingOwnProfile || $currentUser->isSuper() || (
        perms_check($userPerms, MSZ_PERM_USER_MANAGE_USERS)
        && $currentUser->hasAuthorityOver($profileUser)
    ));

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
        'background_attachments' => UserBackgroundAsset::getAttachmentStringOptions(),
    ]);

    if(!empty($_POST) && is_array($_POST)) {
        if(!CSRF::validateRequest()) {
            $notices[] = 'Couldn\'t verify you, please refresh the page and retry.';
        } else {
            if(!empty($_POST['profile']) && is_array($_POST['profile'])) {
                if(!$perms['edit_profile']) {
                    $notices[] = 'You\'re not allowed to edit your profile';
                } else {
                    $profileFields = $profileUser->profileFields(false);

                    foreach($profileFields as $profileField) {
                        if(isset($_POST['profile'][$profileField->field_key])
                            && $profileField->field_value !== $_POST['profile'][$profileField->field_key]
                            && !$profileField->setFieldValue($_POST['profile'][$profileField->field_key])) {
                            $notices[] = sprintf('%s was formatted incorrectly!', $profileField->field_title);
                        }
                    }
                }
            }

            if(!empty($_POST['about']) && is_array($_POST['about'])) {
                if(!$perms['edit_about']) {
                    $notices[] = 'You\'re not allowed to edit your about page.';
                } else {
                    $aboutText  = (string)($_POST['about']['text'] ?? '');
                    $aboutParse = (int)($_POST['about']['parser'] ?? Parser::PLAIN);
                    $aboutValid = User::validateProfileAbout($aboutParse, $aboutText, strlen($profileUser->getProfileAboutText()) > User::PROFILE_ABOUT_MAX_LENGTH);

                    if($aboutValid === '')
                        $currentUser->setProfileAboutText($aboutText)->setProfileAboutParser($aboutParse);
                    else switch($aboutValid) {
                        case 'parser':
                            $notices[] = 'The selected about section parser is invalid.';
                            break;
                        case 'long':
                            $notices[] = sprintf('Please keep the length of your about section below %d characters.', User::PROFILE_ABOUT_MAX_LENGTH);
                            break;
                        default:
                            $notices[] = 'Failed to update about section, contact an administator.';
                            break;
                    }
                }
            }

            if(!empty($_POST['signature']) && is_array($_POST['signature'])) {
                if(!$perms['edit_signature']) {
                    $notices[] = 'You\'re not allowed to edit your forum signature.';
                } else {
                    $sigText  = (string)($_POST['signature']['text'] ?? '');
                    $sigParse = (int)($_POST['signature']['parser'] ?? Parser::PLAIN);
                    $sigValid = User::validateForumSignature($sigParse, $sigText);

                    if($sigValid === '')
                        $currentUser->setForumSignatureText($sigText)->setForumSignatureParser($sigParse);
                    else switch($sigValid) {
                        case 'parser':
                            $notices[] = 'The selected forum signature parser is invalid.';
                            break;
                        case 'long':
                            $notices[] = sprintf('Please keep the length of your signature below %d characters.', User::FORUM_SIGNATURE_MAX_LENGTH);
                            break;
                        default:
                            $notices[] = 'Failed to update signature, contact an administator.';
                            break;
                    }
                }
            }

            if(!empty($_POST['birthdate']) && is_array($_POST['birthdate'])) {
                if(!$perms['edit_birthdate']) {
                    $notices[] = "You aren't allow to change your birthdate.";
                } else {
                    $birthYear  = (int)($_POST['birthdate']['year'] ?? 0);
                    $birthMonth = (int)($_POST['birthdate']['month'] ?? 0);
                    $birthDay   = (int)($_POST['birthdate']['day'] ?? 0);
                    $birthValid = User::validateBirthdate($birthYear, $birthMonth, $birthDay);

                    if($birthValid === '')
                        $currentUser->setBirthdate($birthYear, $birthMonth, $birthDay);
                    else switch($birthValid) {
                        case 'year':
                            $notices[] = 'The given birth year is invalid.';
                            break;
                        case 'date':
                            $notices[] = 'The given birthdate is invalid.';
                            break;
                        default:
                            $notices[] = 'Something unexpected happened while setting your birthdate.';
                            break;
                    }
                }
            }

            if(!empty($_FILES['avatar'])) {
                $avatarInfo = $profileUser->getAvatarInfo();

                if(!empty($_POST['avatar']['delete'])) {
                    $avatarInfo->delete();
                } else {
                    if(!$perms['edit_avatar']) {
                        $notices[] = 'You aren\'t allow to change your avatar.';
                    } elseif(!empty($_FILES['avatar'])
                        && is_array($_FILES['avatar'])
                        && !empty($_FILES['avatar']['name']['file'])) {
                        if($_FILES['avatar']['error']['file'] !== UPLOAD_ERR_OK) {
                            switch($_FILES['avatar']['error']['file']) {
                                case UPLOAD_ERR_NO_FILE:
                                    $notices[] = 'Select a file before hitting upload!';
                                    break;
                                case UPLOAD_ERR_PARTIAL:
                                    $notices[] = 'The upload was interrupted, please try again!';
                                    break;
                                case UPLOAD_ERR_INI_SIZE:
                                case UPLOAD_ERR_FORM_SIZE:
                                    $notices[] = sprintf('Your avatar is not allowed to be larger in file size than %2$s!', byte_symbol($avatarInfo->getMaxBytes(), true));
                                    break;
                                default:
                                    $notices[] = 'Unable to save your avatar, contact an administator!';
                                    break;
                            }
                        } else {
                            try {
                                $avatarInfo->setFromPath($_FILES['avatar']['tmp_name']['file']);
                            } catch(UserImageAssetInvalidImageException $ex) {
                                $notices[] = 'The file you uploaded was not an image!';
                            } catch(UserImageAssetInvalidTypeException $ex) {
                                $notices[] = 'This type of image is not supported, keep to PNG, JPG or GIF!';
                            } catch(UserImageAssetInvalidDimensionsException $ex) {
                                $notices[] = sprintf('Your avatar can\'t be larger than %dx%d!', $avatarInfo->getMaxWidth(), $avatarInfo->getMaxHeight());
                            } catch(UserImageAssetFileTooLargeException $ex) {
                                $notices[] = sprintf('Your avatar is not allowed to be larger in file size than %2$s!', byte_symbol($avatarInfo->getMaxBytes(), true));
                            } catch(UserImageAssetException $ex) {
                                $notices[] = 'Unable to save your avatar, contact an administator!';
                            }
                        }
                    }
                }
            }

            if(!empty($_FILES['background'])) {
                $backgroundInfo = $profileUser->getBackgroundInfo();

                if((int)($_POST['background']['attach'] ?? -1) === 0) {
                    $backgroundInfo->delete();
                } else {
                    if(!$perms['edit_background']) {
                        $notices[] = 'You aren\'t allow to change your background.';
                    } elseif(!empty($_FILES['background']) && is_array($_FILES['background'])) {
                        if(!empty($_FILES['background']['name']['file'])) {
                            if($_FILES['background']['error']['file'] !== UPLOAD_ERR_OK) {
                                switch($_FILES['background']['error']['file']) {
                                    case UPLOAD_ERR_NO_FILE:
                                        $notices[] = 'Select a file before hitting upload!';
                                        break;
                                    case UPLOAD_ERR_PARTIAL:
                                        $notices[] = 'The upload was interrupted, please try again!';
                                        break;
                                    case UPLOAD_ERR_INI_SIZE:
                                    case UPLOAD_ERR_FORM_SIZE:
                                        $notices[] = sprintf('Your background is not allowed to be larger in file size than %s!', byte_symbol($backgroundProps['max_size'], true));
                                        break;
                                    default:
                                        $notices[] = 'Unable to save your background, contact an administator!';
                                        break;
                                }
                            } else {
                                try {
                                    $backgroundInfo->setFromPath($_FILES['background']['tmp_name']['file']);
                                } catch(UserImageAssetInvalidImageException $ex) {
                                    $notices[] = 'The file you uploaded was not an image!';
                                } catch(UserImageAssetInvalidTypeException $ex) {
                                    $notices[] = 'This type of image is not supported, keep to PNG, JPG or GIF!';
                                } catch(UserImageAssetInvalidDimensionsException $ex) {
                                    $notices[] = sprintf('Your background can\'t be larger than %dx%d!', $backgroundInfo->getMaxWidth(), $backgroundInfo->getMaxHeight());
                                } catch(UserImageAssetFileTooLargeException $ex) {
                                    $notices[] = sprintf('Your background is not allowed to be larger in file size than %2$s!', byte_symbol($backgroundInfo->getMaxBytes(), true));
                                } catch(UserImageAssetException $ex) {
                                    $notices[] = 'Unable to save your background, contact an administator!';
                                }

                                try {
                                    $backgroundInfo->setAttachmentString($_POST['background']['attach'] ?? '')
                                        ->setBlend(!empty($_POST['background']['attr']['blend']))
                                        ->setSlide(!empty($_POST['background']['attr']['slide']));
                                } catch(InvalidArgumentException $ex) {}
                            }
                        }
                    }
                }
            }

            $profileUser->saveProfile();
        }

        // Unset $isEditing and hope the user doesn't refresh their profile!
        if(empty($notices))
            $isEditing = false;
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
        $warnings = $profileUser->getProfileWarnings($currentUser);

        Template::set([
            'profile_warnings' => $warnings,
            'profile_warnings_view_private' => $canManageWarnings,
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
