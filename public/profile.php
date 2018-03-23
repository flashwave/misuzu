<?php
use Misuzu\Users\User;

require_once __DIR__ . '/../misuzu.php';

$user_id = (int)($_GET['u'] ?? 0);

try {
    $app->templating->vars(['profile' => User::findOrFail($user_id)]);
} catch (Exception $ex) {
    http_response_code(404);
    echo $app->templating->render('user.notfound');
    return;
}

echo $app->templating->render('user.view');
