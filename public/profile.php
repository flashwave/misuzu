<?php
use Misuzu\Users\User;

require_once __DIR__ . '/../misuzu.php';

$user_id = (int)$_GET['u'];

$app->templating->vars(['profile' => User::findOrFail($user_id)]);
echo $app->templating->render('user.view');
