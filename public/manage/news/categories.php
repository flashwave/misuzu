<?php
require_once '../../../misuzu.php';

if(!perms_check_user(MSZ_PERMS_NEWS, user_session_current('user_id'), MSZ_PERM_NEWS_MANAGE_CATEGORIES)) {
    echo render_error(403);
    return;
}

$categoriesPagination = pagination_create(news_categories_count(true), 15);
$categoriesOffset = pagination_offset($categoriesPagination, pagination_param());

if(!pagination_is_valid_offset($categoriesOffset)) {
    echo render_error(404);
    return;
}

$categories = news_categories_get($categoriesOffset, $categoriesPagination['range'], true, false, true);

echo tpl_render('manage.news.categories', [
    'news_categories' => $categories,
    'categories_pagination' => $categoriesPagination,
]);
