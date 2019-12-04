<?php
namespace Misuzu;

require_once '../../../misuzu.php';

if(!perms_check_user(MSZ_PERMS_GENERAL, user_session_current('user_id'), MSZ_PERM_GENERAL_MANAGE_BLACKLIST)) {
    echo render_error(403);
    return;
}

$notices = [];

if(!empty($_POST)) {
    if(!csrf_verify_request()) {
        $notices[] = 'Verification failed.';
    } else {
        csrf_http_header();

        if(!empty($_POST['blacklist']['remove']) && is_array($_POST['blacklist']['remove'])) {
            foreach($_POST['blacklist']['remove'] as $cidr) {
                if(!ip_blacklist_remove($cidr)) {
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

                if(!ip_blacklist_add($cidr)) {
                    $notices[] = sprintf('Failed to add "%s" to the blacklist.', $cidr);
                }
            }
        }
    }
}

Template::render('manage.general.blacklist', [
    'notices' => $notices,
    'blacklist' => ip_blacklist_list(),
]);
