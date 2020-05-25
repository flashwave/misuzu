<?php
namespace Misuzu;

use Misuzu\AuditLog;
use Misuzu\News\NewsCategory;
use Misuzu\News\NewsCategoryNotFoundException;
use Misuzu\Users\User;

require_once '../../../misuzu.php';

if(!User::hasCurrent() || !perms_check_user(MSZ_PERMS_NEWS, User::getCurrent()->getId(), MSZ_PERM_NEWS_MANAGE_CATEGORIES)) {
    echo render_error(403);
    return;
}

$categoryId = (int)filter_input(INPUT_GET, 'c', FILTER_SANITIZE_NUMBER_INT);

if($categoryId > 0)
    try {
        $categoryInfo = NewsCategory::byId($categoryId);
        Template::set('category_info', $categoryInfo);
    } catch(NewsCategoryNotFoundException $ex) {
        echo render_error(404);
        return;
    }

if(!empty($_POST['category']) && CSRF::validateRequest()) {
    if(!isset($categoryInfo)) {
        $categoryInfo = new NewsCategory;
        $isNew = true;
    }

    $categoryInfo->setName($_POST['category']['name'])
        ->setDescription($_POST['category']['description'])
        ->setHidden(!empty($_POST['category']['hidden']))
        ->save();

    AuditLog::create(
        empty($isNew)
            ? AuditLog::NEWS_CATEGORY_EDIT
            : AuditLog::NEWS_CATEGORY_CREATE,
        [$categoryInfo->getId()]
    );

    if(!empty($isNew)) {
        header('Location: ' . url('manage-news-category', ['category' => $categoryInfo->getId()]));
        return;
    }
}

Template::render('manage.news.category');
