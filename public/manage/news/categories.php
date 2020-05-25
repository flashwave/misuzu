<?php
namespace Misuzu;

use Misuzu\News\NewsCategory;
use Misuzu\Users\User;

require_once '../../../misuzu.php';

if(!User::hasCurrent() || !perms_check_user(MSZ_PERMS_NEWS, User::getCurrent()->getId(), MSZ_PERM_NEWS_MANAGE_CATEGORIES)) {
    echo render_error(403);
    return;
}

$categoriesPagination = new Pagination(NewsCategory::countAll(true), 15);

if(!$categoriesPagination->hasValidOffset()) {
    echo render_error(404);
    return;
}

$categories = NewsCategory::all($categoriesPagination, true);

Template::render('manage.news.categories', [
    'news_categories' => $categories,
    'categories_pagination' => $categoriesPagination,
]);
