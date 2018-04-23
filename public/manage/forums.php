<?php
require_once __DIR__ . '/../../misuzu.php';

switch ($_GET['v'] ?? null) {
    case 'listing':
        echo 'forum listing here';
        break;

    case 'permissions':
        echo 'permissions here, not even sure what this would do';
        break;

    case 'settings':
        echo 'overall forum settings here';
        break;
}
