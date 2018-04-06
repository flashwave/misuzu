<?php
use Misuzu\Application;
use Misuzu\Users\User;

require_once __DIR__ . '/../../misuzu.php';

$users_page = (int)($_GET['p'] ?? 1);
$manage_users = User::paginate(32, ['*'], 'p', $users_page);
$app->templating->vars(compact('manage_users', 'users_page'));

echo $app->templating->render('@manage.users.listing');
