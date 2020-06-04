<?php
namespace Misuzu;

use Misuzu\AuditLog;
use Misuzu\Config;
use Misuzu\Users\User;
use Misuzu\Users\UserRole;
use Misuzu\Users\UserRoleNotFoundException;
use Misuzu\Users\UserSession;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

require_once '../../misuzu.php';

if(!UserSession::hasCurrent()) {
    echo render_error(401);
    return;
}

$errors = [];
$currentUser = User::getCurrent();
$currentUserId = $currentUser->getId();
$isRestricted = $currentUser->hasActiveWarning();
$twoFactorInfo = user_totp_info($currentUserId);
$isVerifiedRequest = CSRF::validateRequest();

if(!$isRestricted && $isVerifiedRequest && !empty($_POST['role'])) {
    try {
        $roleInfo = UserRole::byId((int)($_POST['role']['id'] ?? 0));
    } catch(UserRoleNotFoundException $ex) {}

    if(empty($roleInfo) || !$currentUser->hasRole($roleInfo))
        $errors[] = "You're trying to modify a role that hasn't been assigned to you.";
    else {
        switch($_POST['role']['mode'] ?? '') {
            case 'display':
                $currentUser->setDisplayRole($roleInfo);
                break;

            case 'leave':
                if($roleInfo->getCanLeave())
                    $currentUser->removeRole($roleInfo);
                else
                    $errors[] = "You're not allow to leave this role, an administrator has to remove it for you.";
                break;
        }
    }
}

if($isVerifiedRequest && isset($_POST['tfa']['enable']) && (bool)$twoFactorInfo['totp_enabled'] !== (bool)$_POST['tfa']['enable']) {
    if((bool)$_POST['tfa']['enable']) {
        $tfaKey = TOTP::generateKey();
        $tfaIssuer = Config::get('site.name', Config::TYPE_STR, 'Misuzu');
        $tfaQrcode = (new QRCode(new QROptions([
            'version'    => 5,
            'outputType' => QRCode::OUTPUT_IMAGE_JPG,
            'eccLevel'   => QRCode::ECC_L,
        ])))->render(sprintf('otpauth://totp/%s:%s?%s', $tfaIssuer, $twoFactorInfo['username'], http_build_query([
            'secret' => $tfaKey,
            'issuer' => $tfaIssuer,
        ])));

        Template::set([
            'settings_2fa_code' => $tfaKey,
            'settings_2fa_image' => $tfaQrcode,
        ]);

        user_totp_update($currentUserId, $tfaKey);
    } else {
        user_totp_update($currentUserId, null);
    }

    $twoFactorInfo['totp_enabled'] = !$twoFactorInfo['totp_enabled'];
}

if($isVerifiedRequest && !empty($_POST['current_password'])) {
    if(!$currentUser->checkPassword($_POST['current_password'] ?? '')) {
        $errors[] = 'Your password was incorrect.';
    } else {
        // Changing e-mail
        if(!empty($_POST['email']['new'])) {
            if(empty($_POST['email']['confirm']) || $_POST['email']['new'] !== $_POST['email']['confirm']) {
                $errors[] = 'The addresses you entered did not match each other.';
            } elseif($currentUser->getEMailAddress() === mb_strtolower($_POST['email']['confirm'])) {
                $errors[] = 'This is already your e-mail address!';
            } else {
                $checkMail = User::validateEMailAddress($_POST['email']['new'], true);

                if($checkMail !== '') {
                    switch($checkMail) {
                        case 'dns':
                            $errors[] = 'No valid MX record exists for this domain.';
                            break;

                        case 'format':
                            $errors[] = 'The given e-mail address was incorrectly formatted.';
                            break;

                        case 'in-use':
                            $errors[] = 'This e-mail address is already in use.';
                            break;

                        default:
                            $errors[] = 'Unknown e-mail validation error.';
                    }
                } else {
                    user_email_set($currentUserId, $_POST['email']['new']);
                    AuditLog::create(AuditLog::PERSONAL_EMAIL_CHANGE, [
                        $_POST['email']['new'],
                    ]);
                }
            }
        }

        // Changing password
        if(!empty($_POST['password']['new'])) {
            if(empty($_POST['password']['confirm']) || $_POST['password']['new'] !== $_POST['password']['confirm']) {
                $errors[] = 'The new passwords you entered did not match each other.';
            } else {
                $checkPassword = User::validatePassword($_POST['password']['new']);

                if($checkPassword !== '') {
                    $errors[] = 'The given passwords was too weak.';
                } else {
                    $currentUser->setPassword($_POST['password']['new']);
                    AuditLog::create(AuditLog::PERSONAL_PASSWORD_CHANGE);
                }
            }
        }
    }
}

Template::render('settings.account', [
    'errors' => $errors,
    'settings_user' => $currentUser,
    'is_restricted' => $isRestricted,
    'settings_2fa_enabled' => $twoFactorInfo['totp_enabled'],
]);
