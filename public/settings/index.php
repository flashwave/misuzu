<?php
namespace Misuzu;

require_once '../../misuzu.php';

if(!user_session_active()) {
    echo render_error(401);
    return;
}

// do something with this page

url_redirect('settings-account');
