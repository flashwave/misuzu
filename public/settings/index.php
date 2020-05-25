<?php
namespace Misuzu;

use Misuzu\Users\UserSession;

require_once '../../misuzu.php';

if(!UserSession::hasCurrent()) {
    echo render_error(401);
    return;
}

url_redirect('settings-account');
