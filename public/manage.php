<?php
use Misuzu\Application;
use Misuzu\Users\User;

require_once __DIR__ . '/../misuzu.php';

$manage_session = Application::getInstance()->getSession();

if ($manage_session === null) {
    header('Location: /');
    return;
}

$app->templating->addPath('manage', __DIR__ . '/../views/manage');

$manage_user = $manage_session->user;
$manage_modes = [
    'overview' => [
        'title' => 'Overview',
    ],
    'forums' => [
        'title' => 'Forums',
    ],
    'users' => [
        'title' => 'Users',
    ],
    'roles' => [
        'title' => 'Roles',
    ],
];
$manage_mode = $_GET['m'] ?? key($manage_modes);

$app->templating->vars(compact('manage_mode', 'manage_modes', 'manage_user', 'manage_session'));

if (!array_key_exists($manage_mode, $manage_modes)) {
    http_response_code(404);
    $app->templating->var('manage_title', 'Not Found');
    echo $app->templating->render('@manage.notfound');
    return;
}

$app->templating->var('title', $manage_modes[$manage_mode]['title']);

switch ($manage_mode) {
    case 'users':
        $users_page = (int)($_GET['p'] ?? 1);
        $manage_users = User::paginate(32, ['*'], 'p', $users_page);
        $app->templating->vars(compact('manage_users', 'users_page'));
        break;
}

echo $app->templating->render("@manage.{$manage_mode}");
