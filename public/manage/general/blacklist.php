<?php
namespace Misuzu;

use Misuzu\Net\IPAddressBlacklist;
use Misuzu\Users\User;

require_once '../../../misuzu.php';

if(!User::hasCurrent() || !perms_check_user(MSZ_PERMS_GENERAL, User::getCurrent()->getId(), MSZ_PERM_GENERAL_MANAGE_BLACKLIST)) {
    echo render_error(403);
    return;
}

$notices = [];

if(!empty($_POST)) {
    if(!CSRF::validateRequest()) {
        $notices[] = 'Verification failed.';
    } else {
        header(CSRF::header());

        if(!empty($_POST['blacklist']['remove']) && is_array($_POST['blacklist']['remove'])) {
            foreach($_POST['blacklist']['remove'] as $cidr) {
                if(!IPAddressBlacklist::remove($cidr)) {
                    $notices[] = sprintf('Failed to remove "%s" from the blacklist.', $cidr);
                }
            }
        }

        if(!empty($_POST['blacklist']['add']) && is_string($_POST['blacklist']['add'])) {
            $cidrs = explode("\n", $_POST['blacklist']['add']);

            foreach($cidrs as $cidr) {
                $cidr = trim($cidr);

                if(empty($cidr)) {
                    continue;
                }

                if(!IPAddressBlacklist::add($cidr)) {
                    $notices[] = sprintf('Failed to add "%s" to the blacklist.', $cidr);
                }
            }
        }
    }
}

Template::render('manage.general.blacklist', [
    'notices' => $notices,
    'blacklist' => IPAddressBlacklist::list(),
]);
