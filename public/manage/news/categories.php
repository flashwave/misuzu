<?php
namespace Misuzu;

require_once '../../../misuzu.php';

if(!perms_check_user(MSZ_PERMS_NEWS, user_session_current('user_id'), MSZ_PERM_NEWS_MANAGE_CATEGORIES)) {
    echo render_error(403);
    return;
}

$categoriesPagination = new Pagination(news_categories_count(true), 15);

if(!$categoriesPagination->hasValidOffset()) {
    echo render_error(404);
    return;
}

$categories = news_categories_get($categoriesPagination->getOffset(), $categoriesPagination->getRange(), true, false, true);

Template::render('manage.news.categories', [
    'news_categories' => $categories,
    'categories_pagination' => $categoriesPagination,
]);
