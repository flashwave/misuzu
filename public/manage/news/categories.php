<?php
namespace Misuzu;

use Misuzu\News\NewsCategory;

require_once '../../../misuzu.php';

if(!perms_check_user(MSZ_PERMS_NEWS, user_session_current('user_id'), MSZ_PERM_NEWS_MANAGE_CATEGORIES)) {
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
